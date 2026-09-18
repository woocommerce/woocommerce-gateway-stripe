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
