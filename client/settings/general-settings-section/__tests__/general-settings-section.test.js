import React from 'react';
import { fireEvent, screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '..';
import UpeToggleContext from '../../upe-toggle/context';
import {
	useIsStripeEnabled,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useManualCapture,
	useIndividualPaymentMethodSettings,
} from 'wcstripe/data';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data', () => ( {
	useIsStripeEnabled: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useManualCapture: jest.fn(),
	useIndividualPaymentMethodSettings: jest.fn(),
} ) );
jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
	useGetCapabilities: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {} ),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
} ) );
jest.mock(
	'wcstripe/components/payment-method-capability-status-pill',
	() => () => null
);
jest.mock( '../../loadable-settings-section', () => ( { children } ) =>
	children
);

describe( 'GeneralSettingsSection', () => {
	beforeEach( () => {
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
			giropay_payments: 'active',
		} );
		useManualCapture.mockReturnValue( [ false ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card', 'link' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'link' ],
			jest.fn(),
		] );
		useAccount.mockReturnValue( { isRefreshing: false } );
		useIsStripeEnabled.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'should show information to screen readers about the payment methods being updated', () => {
		const refreshAccountMock = jest.fn();
		useAccount.mockReturnValue( {
			isRefreshing: true,
			refreshAccount: refreshAccountMock,
		} );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect( refreshAccountMock ).not.toHaveBeenCalled();

		expect(
			screen.queryByText(
				'Updating payment methods information, please wait.'
			)
		).toBeInTheDocument();

		userEvent.click(
			screen.getByRole( 'button', {
				name: 'Payment methods menu',
			} )
		);

		expect( refreshAccountMock ).not.toHaveBeenCalled();

		userEvent.click(
			screen.getByRole( 'menuitem', {
				name: 'Refresh payment methods',
			} )
		);
		expect( refreshAccountMock ).toHaveBeenCalled();
	} );

	it( 'should not render the opt-in banner if UPE is enabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'opt-in-banner' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Get more payment methods' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Payment methods menu',
			} )
		).toBeInTheDocument();
	} );

	it( 'should allow to enable a payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const giropayCheckbox = screen.getByRole( 'checkbox', {
			name: /giropay/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( giropayCheckbox ).not.toBeChecked();

		userEvent.click( giropayCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'giropay',
		] );
	} );

	it( 'should allow to enable a payment method when UPE is disabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const giropayCheckbox = screen.getByRole( 'checkbox', {
			name: /giropay/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( giropayCheckbox ).not.toBeChecked();

		userEvent.click( giropayCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'giropay',
		] );
	} );

	it( 'should show modal to disable a payment method', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( cardCheckbox ).toBeChecked();
		expect(
			screen.queryByRole( 'heading', {
				name: 'Remove Credit card / debit card from checkout',
			} )
		).not.toBeInTheDocument();

		userEvent.click( cardCheckbox );

		expect(
			screen.getByRole( 'heading', {
				name: 'Remove Credit card / debit card from checkout',
			} )
		).toBeInTheDocument();
	} );

	it( 'should not allow to disable a payment method when canceled via modal', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( cardCheckbox ).toBeChecked();

		userEvent.click( cardCheckbox );
		userEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
	} );

	it( 'should allow to disable a payment method when confirmed via modal', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( cardCheckbox ).toBeChecked();

		userEvent.click( cardCheckbox );
		userEvent.click( screen.getByRole( 'button', { name: 'Remove' } ) );

		expect( updateEnabledMethodsMock ).toHaveBeenCalled();
	} );

	it( 'should display a modal to allow to disable UPE', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( /Without the new payments experience/ )
		).not.toBeInTheDocument();

		userEvent.click(
			screen.getByRole( 'button', {
				name: 'Payment methods menu',
			} )
		);
		userEvent.click(
			screen.getByRole( 'menuitem', {
				name: 'Disable',
			} )
		);

		expect(
			screen.queryByText( /Without the new payments experience/ )
		).toBeInTheDocument();
	} );

	it( 'does not display the payment method checkbox when manual capture is enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
		] );
		useManualCapture.mockReturnValue( [ true ] );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByRole( 'checkbox', {
				name: /Credit card/,
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'checkbox', {
				name: 'giropay',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'display customization section in the payment method when UPE is disabled', () => {
		const setIndividualPaymentMethodSettingsMock = jest.fn();
		useIndividualPaymentMethodSettings.mockReturnValue( [
			{
				giropay: {
					name: 'Giropay',
					description: 'Pay with Giropay',
				},
				eps: {
					name: 'EPS',
					description: 'Pay with EPS',
				},
			},
			setIndividualPaymentMethodSettingsMock,
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'giropay' ] );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByRole( 'checkbox', {
				name: 'giropay',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Customize',
			} )
		).toBeInTheDocument();
		// Click on the customize button
		userEvent.click(
			screen.queryByRole( 'button', {
				name: 'Customize',
			} )
		);

		// Expect the customization section to be open
		expect( screen.getByLabelText( 'Name' ) ).toHaveValue( 'Giropay' );
		expect( screen.getByLabelText( 'Description' ) ).toHaveValue(
			'Pay with Giropay'
		);
		expect(
			screen.queryByRole( 'button', {
				name: 'Cancel',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Save changes',
			} )
		).toBeInTheDocument();

		// Change settings of this method
		fireEvent.change( screen.getByLabelText( 'Name' ), {
			target: { value: 'New Name' },
		} );
		fireEvent.change( screen.getByLabelText( 'Description' ), {
			target: { value: 'New Description' },
		} );

		userEvent.click(
			screen.queryByRole( 'button', {
				name: 'Save changes',
			} )
		);

		expect( setIndividualPaymentMethodSettingsMock ).toHaveBeenCalled();
	} );

	it( 'should not display customization section in the payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'giropay' ] );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByRole( 'checkbox', {
				name: 'giropay',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Customize',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'displays the payment method checkbox when manual capture is disabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
		] );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByRole( 'checkbox', {
				name: /Credit card/,
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'checkbox', {
				name: 'giropay',
			} )
		).toBeInTheDocument();
	} );

	it( 'should not render payment methods that are not part of the account capabilities', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
		] );
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
		} );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByRole( 'checkbox', {
				name: 'giropay',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'should render the list of missing payment methods if UPE is enabled', () => {
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
		} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sepa_debit',
			'sofort',
			'eps',
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'unavailable-payment-methods-list' )
		).toBeInTheDocument();

		expect(
			screen.queryByTestId( 'unavailable-payment-methods-more' )
		).toBeInTheDocument();
	} );

	it( 'should not render the list of missing payment methods if UPE is disabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'unavailable-payment-methods-list' )
		).not.toBeInTheDocument();
	} );
} );
