/* global wc_stripe_express_checkout_params */

/**
 * Check whether Amazon Pay is enabled.
 *
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isAmazonPayEnabled = () => {
	// eslint-disable-next-line camelcase, no-undef
	return !! wc_stripe_express_checkout_params?.stripe?.is_amazon_pay_enabled;
};
