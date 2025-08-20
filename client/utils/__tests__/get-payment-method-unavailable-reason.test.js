import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_KLARNA,
	PAYMENT_METHOD_SEPA,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';
import { getPaymentMethodCurrencies } from 'utils/use-payment-method-currencies';

jest.mock( 'utils/use-payment-method-currencies', () => ( {
	getPaymentMethodCurrencies: jest.fn(),
} ) );

describe( 'getPaymentMethodUnavailableReason', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = {
			has_klarna_gateway_plugin: false,
			has_affirm_gateway_plugin: false,
		};
		getPaymentMethodCurrencies.mockImplementation( ( paymentMethodId ) => {
			if ( paymentMethodId === PAYMENT_METHOD_CARD ) {
				return [];
			}
			if ( paymentMethodId === PAYMENT_METHOD_SEPA ) {
				return [ 'EUR' ];
			}
			return [ 'USD' ];
		} );
	} );

	it( 'should return null if UPE is disabled', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_CARD,
				isUpeEnabled: false,
				storeCurrencyCode: 'USD',
			} )
		).toBeNull();
	} );

	it( 'should return null if the store currency is not set', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_CARD,
				storeCurrencyCode: null,
			} )
		).toBeNull();
	} );

	it( 'should return null when UPE is disabled and the store currency is not set', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_CARD,
				isUpeEnabled: false,
				storeCurrencyCode: null,
			} )
		).toBeNull();
	} );

	it( 'should return null for a payment method that supports all currencies when store is in USD', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_CARD,
				storeCurrencyCode: 'USD',
			} )
		).toBeNull();
	} );
	it( 'should return null for a payment method that supports all currencies when store is in EUR', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_CARD,
				storeCurrencyCode: 'EUR',
			} )
		).toBeNull();
	} );

	it( 'should return OFFICIAL_PLUGIN_CONFLICT when Klarna is unavailable due to a conflict with an official plugin', () => {
		global.wc_stripe_settings_params.has_klarna_gateway_plugin = true;
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_KLARNA,
				storeCurrencyCode: 'USD',
			} )
		).toBe( PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT );
	} );

	it( 'should return OFFICIAL_PLUGIN_CONFLICT when Affirm is unavailable due to a conflict with an official plugin', () => {
		global.wc_stripe_settings_params.has_affirm_gateway_plugin = true;
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_AFFIRM,
				storeCurrencyCode: 'USD',
			} )
		).toBe( PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT );
	} );

	it( 'should return UNSUPPORTED_CURRENCY when the payment method is unavailable due to an unsupported currency - EUR needed; store in USD', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_SEPA,
				storeCurrencyCode: 'USD',
			} )
		).toBe( PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY );
	} );

	it( 'should return UNSUPPORTED_CURRENCY when the payment method is unavailable due to an unsupported currency - USD needed; store in EUR', () => {
		expect(
			getPaymentMethodUnavailableReason( {
				paymentMethodId: PAYMENT_METHOD_AFFIRM,
				storeCurrencyCode: 'EUR',
			} )
		).toBe( PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY );
	} );
} );
