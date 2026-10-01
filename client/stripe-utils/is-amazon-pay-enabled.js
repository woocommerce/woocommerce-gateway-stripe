import { getExpressCheckoutParams } from './get-express-checkout-params';

/**
 * Check whether Amazon Pay is enabled.
 *
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isAmazonPayEnabled = () => {
	return !! getExpressCheckoutParams()?.stripe?.is_amazon_pay_enabled;
};
