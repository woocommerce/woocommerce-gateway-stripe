import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '..';
import UpeToggleContext from '../../upe-toggle/context';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useManualCapture: jest.fn(),
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
jest.mock(
	'../../loadable-settings-section',
	() =>
		( { children } ) =>
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

	it( 'should show modal to disable a payment method when UPE is enabled', () => {
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

	it( 'does not display the payment method checkbox when UPE is disabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useManualCapture.mockReturnValue( [ true ] );
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByRole( 'checkbox', {
				name: /Credit card/,
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
