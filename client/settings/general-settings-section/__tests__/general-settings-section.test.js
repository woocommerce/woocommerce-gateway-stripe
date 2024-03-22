import React from 'react';
import { fireEvent, screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { act } from 'react-dom/test-utils';
import GeneralSettingsSection from '..';
import UpeToggleContext from '../../upe-toggle/context';
import {
	useIsStripeEnabled,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useManualCapture,
	useCustomizePaymentMethodSettings,
	useGetOrderedPaymentMethodIds,
} from 'wcstripe/data';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';
import { useAliPayCurrencies } from 'utils/use-alipay-currencies';

jest.mock( 'wcstripe/data', () => ( {
	useIsStripeEnabled: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useManualCapture: jest.fn(),
	useIndividualPaymentMethodSettings: jest.fn(),
	useCustomizePaymentMethodSettings: jest.fn(),
	useGetOrderedPaymentMethodIds: jest.fn(),
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
jest.mock( 'utils/use-alipay-currencies', () => ( {
	useAliPayCurrencies: jest.fn(),
} ) );

describe( 'GeneralSettingsSection', () => {
	const globalValues = global.wcSettings;

	beforeEach( () => {
		global.wcSettings = { currency: { code: 'EUR' } };
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
		useAccount.mockReturnValue( {
			isRefreshing: false,
			data: { testmode: false },
		} );
		useIsStripeEnabled.mockReturnValue( [ false, jest.fn() ] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ 'card', 'eps' ],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );
		useAliPayCurrencies.mockReturnValue( [ 'EUR', 'CNY' ] );
	} );

	afterEach( () => {
		global.wcSettings = globalValues;
	} );

	it( 'should show information to screen readers about the payment methods being updated', () => {
		const refreshAccountMock = jest.fn();
		useAccount.mockReturnValue( {
			isRefreshing: true,
			refreshAccount: refreshAccountMock,
			data: { testmode: false },
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
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				'card',
				'giropay',
				'sofort',
				'sepa_debit',
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );
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
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				'card',
				'giropay',
				'sofort',
				'sepa_debit',
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );

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

	it( 'does not display the payment method checkbox when currency is not supprted', () => {
		global.wcSettings = { currency: { code: 'USD' } };
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
				name: 'bancontact',
			} )
		).not.toBeInTheDocument();
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

	it( 'display customization section in the payment method when UPE is disabled', async () => {
		const PromiseMock = Promise.resolve();
		const customizePaymentMethodMock = jest
			.fn()
			.mockImplementation( () => PromiseMock );
		useCustomizePaymentMethodSettings.mockReturnValue( {
			individualPaymentMethodSettings: {
				card: {
					name: 'Card',
					description: 'Pay with Card',
				},
				giropay: {
					name: 'Giropay',
					description: 'Pay with Giropay',
				},
			},
			isCustomizing: false,
			customizePaymentMethod: customizePaymentMethodMock,
		} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'giropay' ] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ 'giropay' ],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection onSaveChanges={ jest.fn() } />
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

		fireEvent.click(
			screen.queryByRole( 'button', {
				name: 'Save changes',
			} )
		);

		expect( customizePaymentMethodMock ).toHaveBeenCalled();

		await act( async () => {
			await Promise.resolve();
		} );
	} );

	it( 'should not display customization section in the payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'giropay' ] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ 'giropay' ],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
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
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ 'card', 'giropay' ],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );

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

	it( 'should not render "early access" pill if UPE is disabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'upe-early-access-pill' )
		).not.toBeInTheDocument();
	} );

	it( 'should not render the expandable menu if UPE is disabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'upe-expandable-menu' )
		).not.toBeInTheDocument();
	} );
} );
