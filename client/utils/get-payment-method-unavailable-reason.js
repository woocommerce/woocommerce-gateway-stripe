import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY,
	PAYMENT_METHOD_KLARNA,
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';
import { getPaymentMethodCurrencies } from 'utils/use-payment-method-currencies';

/**
 * Returns the reason why a payment method is unavailable, or null if it is available.
 * Intentionally outside of a React hook to support looping over payment methods.
 *
 * @param {Object}      context
 * @param {string}      context.paymentMethodId   The payment method ID.
 * @param {boolean}     context.isUpeEnabled      Whether UPE is enabled. If false, the payment method is available.
 * @param {string|null} context.storeCurrencyCode The store currency code. If null, the payment method is available.
 * @return {string|null} The reason why the payment method is unavailable, or null if it is available. See `PAYMENT_METHOD_UNAVAILABLE_REASONS` for possible values.
 */
const getPaymentMethodUnavailableReason = ( {
	paymentMethodId,
	isUpeEnabled = true,
	storeCurrencyCode,
} ) => {
	if (
		paymentMethodId === PAYMENT_METHOD_KLARNA &&
		window?.wc_stripe_settings_params?.has_klarna_gateway_plugin
	) {
		return PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT;
	}

	if (
		paymentMethodId === PAYMENT_METHOD_AFFIRM &&
		window?.wc_stripe_settings_params?.has_affirm_gateway_plugin
	) {
		return PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT;
	}

	if (
		paymentMethodId === PAYMENT_METHOD_AMAZON_PAY &&
		window?.wc_stripe_settings_params?.taxes_based_on_billing
	) {
		return PAYMENT_METHOD_UNAVAILABLE_REASONS.TAX_BASED_ON_BILLING_ADDRESS;
	}

	if (
		( paymentMethodId === PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY ||
			paymentMethodId === PAYMENT_METHOD_LINK ) &&
		! window?.wc_stripe_settings_params?.is_card_method_enabled
	) {
		return PAYMENT_METHOD_UNAVAILABLE_REASONS.REQUIRES_CARD_METHOD;
	}

	if ( ! storeCurrencyCode || ! isUpeEnabled ) {
		return null;
	}

	const paymentMethodCurrencies = getPaymentMethodCurrencies(
		paymentMethodId,
		true
	);

	// Note that getPaymentMethodCurrencies() returns [] when the payment method supports all currencies.
	if ( paymentMethodCurrencies.length === 0 ) {
		return null;
	}
	if ( paymentMethodCurrencies.includes( storeCurrencyCode ) ) {
		return null;
	}

	return PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY;
};

export default getPaymentMethodUnavailableReason;
