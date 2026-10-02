/* global wc_stripe_express_checkout_params */
import { getStripeServerData } from './get-stripe-server-data';

/**
 * Check whether Stripe Link is enabled.
 *
 * @param {Object} paymentMethodsConfig Checkout payment methods configuration settings object.
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isLinkEnabled = ( paymentMethodsConfig ) => {
	paymentMethodsConfig =
		paymentMethodsConfig || getStripeServerData()?.paymentMethodsConfig;
	return (
		paymentMethodsConfig?.link !== undefined &&
		paymentMethodsConfig?.card !== undefined
	);
};

/**
 * Check whether Amazon Pay is enabled.
 *
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isAmazonPayEnabled = () => {
	// eslint-disable-next-line camelcase, no-undef
	return !! wc_stripe_express_checkout_params?.stripe?.is_amazon_pay_enabled;
};
