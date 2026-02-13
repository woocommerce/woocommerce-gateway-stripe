import { getSetting } from '@woocommerce/settings';
import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '..';
import {
	useIsStripeEnabled,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useManualCapture,
	useIsOCEnabled,
	useGetOrderedPaymentMethodIds,
	useIsPMCEnabled,
} from 'wcstripe/data';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_SEPA,
	PAYMENT_METHOD_SOFORT,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	useIsStripeEnabled: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useManualCapture: jest.fn(),
	useIndividualPaymentMethodSettings: jest.fn(),
	useCustomizePaymentMethodSettings: jest.fn(),
	useIsOCEnabled: jest.fn(),
	useGetOrderedPaymentMethodIds: jest.fn(),
	useIsPMCEnabled: jest.fn(),
} ) );
jest.mock( 'utils/get-payment-method-unavailable-reason' );
jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
	useGetCapabilities: jest.fn(),
} ) );
jest.mock( '@woocommerce/settings', () => ( {
	getSetting: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {} ),
	createSelector: jest.fn(),
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
	const globalValues = global.wcSettings;

	/**
	 * Helper to ensure that the wcSettings global and the getSetting() helper are in sync.
	 *
	 * @param {string} currencyCode Currency code to set.
	 */
	const mockCurrencyCode = ( currencyCode ) => {
		global.wcSettings = { currency: { code: currencyCode } };
		getSetting.mockReturnValue( { code: currencyCode } );
	};

	beforeEach( () => {
		mockCurrencyCode( 'EUR' );
		global.wc_stripe_settings_params = { are_apms_deprecated: false };
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
			alipay_payments: 'active',
		} );
		useManualCapture.mockReturnValue( [ false ] );
		getPaymentMethodUnavailableReason.mockReturnValue( null );
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
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_EPS,
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );
		useIsPMCEnabled.mockReturnValue( true );
	} );

	afterEach( () => {
		global.wcSettings = globalValues;
	} );

	it( 'should show information to screen readers about the payment methods being updated', async () => {
		const refreshAccountMock = jest.fn();
		useAccount.mockReturnValue( {
			isRefreshing: true,
			refreshAccount: refreshAccountMock,
			data: { testmode: false },
		} );
		render( <GeneralSettingsSection /> );

		expect( refreshAccountMock ).not.toHaveBeenCalled();

		expect(
			screen.queryByText(
				'Updating payment methods information, please wait.'
			)
		).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Payment methods menu',
			} )
		);

		expect( refreshAccountMock ).not.toHaveBeenCalled();

		await userEvent.click(
			screen.getByRole( 'menuitem', {
				name: 'Refresh payment methods',
			} )
		);
		expect( refreshAccountMock ).toHaveBeenCalled();
	} );

	it( 'should not render the opt-in banner if UPE is enabled', () => {
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByTestId( 'opt-in-banner' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Payment methods menu',
			} )
		).toBeInTheDocument();
	} );

	it( 'should allow to enable a payment method', async () => {
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

		render( <GeneralSettingsSection /> );

		const alipayCheckbox = screen.getByRole( 'checkbox', {
			name: /Alipay/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( alipayCheckbox ).not.toBeChecked();

		await userEvent.click( alipayCheckbox );

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
		] );
	} );

	it( 'should show modal to disable a payment method', async () => {
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

		render( <GeneralSettingsSection /> );

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( cardCheckbox ).toBeChecked();
		expect(
			screen.queryByRole( 'heading', {
				name: 'Remove Credit card / debit card from checkout',
			} )
		).not.toBeInTheDocument();

		await userEvent.click( cardCheckbox );

		expect(
			screen.getByRole( 'heading', {
				name: 'Remove Credit card / debit card from checkout',
			} )
		).toBeInTheDocument();
	} );

	it( 'should not allow to disable a payment method when canceled via modal', async () => {
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

		render( <GeneralSettingsSection /> );

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( cardCheckbox ).toBeChecked();

		await userEvent.click( cardCheckbox );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Cancel' } )
		);

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
	} );

	it( 'should allow to disable a payment method when confirmed via modal', async () => {
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

		render( <GeneralSettingsSection /> );

		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );

		expect( updateEnabledMethodsMock ).not.toHaveBeenCalled();
		expect( cardCheckbox ).toBeChecked();

		await userEvent.click( cardCheckbox );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Remove' } )
		);

		expect( updateEnabledMethodsMock ).toHaveBeenCalled();
	} );

	it( 'does not display the payment method checkbox when currency is not supported', () => {
		mockCurrencyCode( 'USD' );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
		] );
		render( <GeneralSettingsSection /> );

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
		render( <GeneralSettingsSection /> );

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

		render( <GeneralSettingsSection /> );

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

		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByRole( 'checkbox', {
				name: 'Alipay',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'should render the list of missing payment methods if UPE is enabled and PMC is disabled', () => {
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
		useIsPMCEnabled.mockReturnValue( false );
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByTestId( 'unavailable-payment-methods-list' )
		).toBeInTheDocument();

		expect(
			screen.queryByTestId( 'unavailable-payment-methods-more' )
		).toBeInTheDocument();
	} );

	it( 'should not render the list of missing payment methods if PMC is enabled', () => {
		useIsPMCEnabled.mockReturnValue( true );
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByTestId( 'unavailable-payment-methods-list' )
		).not.toBeInTheDocument();
	} );

	it( 'should not render "early access" pill if UPE is disabled', () => {
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByTestId( 'upe-early-access-pill' )
		).not.toBeInTheDocument();
	} );

	it( 'should disable the payment method checkbox and show the requires currency notice when currency is not supported', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
		] );
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
		getPaymentMethodUnavailableReason.mockImplementation(
			( { paymentMethodId } ) => {
				if ( paymentMethodId === PAYMENT_METHOD_ALIPAY ) {
					return PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY;
				}
				return null;
			}
		);
		mockCurrencyCode( 'EUR' );
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByRole( 'checkbox', {
				name: /Credit card/,
			} )
		).toBeEnabled();

		expect(
			screen.queryByRole( 'checkbox', {
				name: 'Alipay',
			} )
		).toBeDisabled();

		expect( screen.queryByText( 'Requires currency' ) ).toBeVisible();
	} );

	it( 'should enable the payment method checkbox and not show the requires currency notice when currency is supported', () => {
		mockCurrencyCode( 'USD' );
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByRole( 'checkbox', {
				name: /Credit card/,
			} )
		).toBeEnabled();

		expect(
			screen.queryByText( 'Requires currency' )
		).not.toBeInTheDocument();
	} );

	it( 'should show the payment method with supported currencies before plugin conflicts and unsupported currencies', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			PAYMENT_METHOD_CARD,
			PAYMENT_METHOD_ALIPAY,
			PAYMENT_METHOD_AFFIRM,
			PAYMENT_METHOD_SEPA,
		] );
		useGetOrderedPaymentMethodIds.mockReturnValue( {
			orderedPaymentMethodIds: [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_ALIPAY,
				PAYMENT_METHOD_AFFIRM,
				PAYMENT_METHOD_SEPA,
			],
			setOrderedPaymentMethodIds: jest.fn(),
			saveOrderedPaymentMethodIds: jest.fn(),
		} );

		getPaymentMethodUnavailableReason.mockImplementation(
			( { paymentMethodId } ) => {
				if ( paymentMethodId === PAYMENT_METHOD_ALIPAY ) {
					return PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY;
				}
				if ( paymentMethodId === PAYMENT_METHOD_AFFIRM ) {
					return PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT;
				}
				return null;
			}
		);
		mockCurrencyCode( 'EUR' );

		render( <GeneralSettingsSection /> );

		const cardElement = screen.getByRole( 'checkbox', {
			name: /Credit card/,
		} );
		const alipayElement = screen.getByRole( 'checkbox', {
			name: 'Alipay',
		} );
		const affirmElement = screen.getByRole( 'checkbox', {
			name: 'Affirm',
		} );
		const sepaElement = screen.getByRole( 'checkbox', {
			name: 'Direct debit payment',
		} );

		expect( cardElement ).toBeEnabled();
		expect( alipayElement ).not.toBeEnabled();
		expect( affirmElement ).not.toBeEnabled();
		expect( sepaElement ).toBeEnabled();

		// Card should be first
		expect( cardElement.compareDocumentPosition( alipayElement ) ).toBe(
			Node.DOCUMENT_POSITION_FOLLOWING
		);
		expect( cardElement.compareDocumentPosition( sepaElement ) ).toBe(
			Node.DOCUMENT_POSITION_FOLLOWING
		);

		// SEPA should be before AliPay and Affirm
		expect( sepaElement.compareDocumentPosition( alipayElement ) ).toBe(
			Node.DOCUMENT_POSITION_FOLLOWING
		);
		expect( sepaElement.compareDocumentPosition( affirmElement ) ).toBe(
			Node.DOCUMENT_POSITION_FOLLOWING
		);

		// Affirm should be before AliPay
		expect( affirmElement.compareDocumentPosition( alipayElement ) ).toBe(
			Node.DOCUMENT_POSITION_FOLLOWING
		);
	} );
} );
