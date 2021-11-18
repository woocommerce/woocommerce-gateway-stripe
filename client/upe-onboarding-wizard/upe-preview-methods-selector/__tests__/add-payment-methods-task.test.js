import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import WizardTaskContext from '../../wizard/task/context';
import AddPaymentMethodsTask from '../add-payment-methods-task';
import WCPaySettingsContext from '../../../settings/wcpay-settings-context';
import {
	useGetAvailablePaymentMethodIds,
	useEnabledPaymentMethodIds,
	useSettings,
} from 'wcstripe/data';
import { useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useGetCapabilities: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock(
	'wcstripe/components/payment-method-capability-status-pill',
	() => () => null
);

const SettingsContextProvider = ( { children } ) => (
	<WCPaySettingsContext.Provider
		value={ { featureFlags: { multiCurrency: true }, accountFees: {} } }
	>
		{ children }
	</WCPaySettingsContext.Provider>
);

describe( 'AddPaymentMethodsTask', () => {
	beforeEach( () => {
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
			giropay_payments: 'active',
		} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'bancontact',
			'giropay',
			'p24',
			'ideal',
			'sepa_debit',
			'sofort',
		] );
		useSettings.mockReturnValue( {
			saveSettings: () => Promise.resolve( true ),
			isSaving: false,
			isLoading: false,
		} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			() => null,
		] );
	} );

	it( 'should not allow to move forward if no payment methods are selected', () => {
		const setCompletedMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [ [], () => null ] );
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect( screen.getByText( 'Add payment methods' ) ).not.toBeEnabled();
	} );

	it( 'should allow to select all payment methods', () => {
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'giropay' } )
		).not.toBeChecked();

		userEvent.click( screen.getByText( 'Select all' ) );

		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'giropay' } )
		).toBeChecked();
	} );

	it( 'should move forward when the payment methods are selected', async () => {
		const setCompletedMock = jest.fn();
		const updateEnabledPaymentMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledPaymentMethodsMock,
		] );
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect( screen.getByText( 'Add payment methods' ) ).toBeEnabled();
		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).toBeChecked();

		userEvent.click( screen.getByRole( 'checkbox', { name: 'giropay' } ) );

		userEvent.click( screen.getByText( 'Add payment methods' ) );

		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'giropay',
		] );
		await waitFor( () =>
			expect( setCompletedMock ).toHaveBeenCalledWith(
				{ initialMethods: [ 'card' ] },
				'setup-complete'
			)
		);
	} );

	it( 'should remove the un-checked payment methods, if they were present before', async () => {
		const setCompletedMock = jest.fn();
		const updateEnabledPaymentMethodsMock = jest.fn();
		const initialMethods = [ 'card', 'giropay' ];
		useEnabledPaymentMethodIds.mockReturnValue( [
			initialMethods,
			updateEnabledPaymentMethodsMock,
		] );
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect(
			screen.getByRole( 'checkbox', { name: 'giropay' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: /Credit/ } )
		).toBeChecked();

		// uncheck a method
		userEvent.click( screen.getByRole( 'checkbox', { name: 'giropay' } ) );

		userEvent.click( screen.getByText( 'Add payment methods' ) );

		// Methods are removed.
		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
		] );
		await waitFor( () =>
			expect( setCompletedMock ).toHaveBeenCalledWith(
				{
					initialMethods: [ 'card', 'giropay' ],
				},
				'setup-complete'
			)
		);
	} );
} );
