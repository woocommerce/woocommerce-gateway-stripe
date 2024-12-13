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
import {
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_SEPA,
	PAYMENT_METHOD_SOFORT,
} from 'wcstripe/stripe-utils/constants';

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

describe( 'GeneralSettingsSection', () => {
	const globalValues = global.wcSettings;

	beforeEach( () => {
		global.wcSettings = { currency: { code: 'EUR' } };
		global.wc_stripe_settings_params = { are_apms_deprecated: false };
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
			alipay_payments: 'active',
		} );
		useManualCapture.mockReturnValue( [ false ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_LINK,
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_LINK ],
			jest.fn(),
		] );
		useAccount.mockReturnValue( {
			isRefreshing: false,
			data: { testmode: false },
		} );
		useIsStripeEnabled.mockReturnValue( [ false, jest.fn() ] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_EPS,
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );
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
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_SEPA,
		] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_ALIPAY,
				PAYMENT_METHOD_SEPA,
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			updateEnabledMethodsMock,
		] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const alipayCheckbox = screen.getByRole( 'checkbox', {
			name: /Alipay/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( alipayCheckbox ).not.toBeChecked();

		userEvent.click( alipayCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
		] );
	} );

	it( 'should allow to enable a payment method when UPE is disabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_SEPA,
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			updateEnabledMethodsMock,
		] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_ALIPAY,
				PAYMENT_METHOD_SEPA,
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const alipayCheckbox = screen.getByRole( 'checkbox', {
			name: /Alipay/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( alipayCheckbox ).not.toBeChecked();

		userEvent.click( alipayCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
		] );
	} );

	it( 'should show modal to disable a payment method', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_SEPA,
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
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
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_SEPA,
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
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
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_SEPA,
		] );
		const updateEnabledMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
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
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
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
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
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
				name: 'Alipay',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'display customization section in the payment method', async () => {
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
				alipay: {
					name: 'Alipay',
					description: 'Pay with Alipay',
				},
			},
			isCustomizing: false,
			customizePaymentMethod: customizePaymentMethodMock,
		} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_ALIPAY,
		] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ PAYMENT_METHOD_ALIPAY ],
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
				name: 'Alipay',
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
		expect( screen.getByLabelText( 'Name' ) ).toHaveValue( 'Alipay' );
		expect( screen.getByLabelText( 'Description' ) ).toHaveValue(
			'Pay with Alipay'
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

	it( 'should display customization section in the payment method when UPE is enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_ALIPAY,
		] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [ PAYMENT_METHOD_ALIPAY ],
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
				name: 'Alipay',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Customize',
			} )
		).toBeInTheDocument();
	} );

	it( 'displays the payment method checkbox when manual capture is disabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
		] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_ALIPAY,
			],
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
				name: 'Alipay',
			} )
		).toBeInTheDocument();
	} );

	it( 'should not render payment methods that are not part of the account capabilities', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
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
				name: 'Alipay',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'should render the list of missing payment methods if UPE is enabled', () => {
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
		} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_SEPA,
			PAYMENT_METHOD_SOFORT,
			PAYMENT_METHOD_EPS,
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
		] );

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
