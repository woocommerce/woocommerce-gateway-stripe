/* global wc_stripe_express_checkout_params */
import { getStripeServerData } from './get-stripe-server-data';
import { STRIPE_JS_OPTIONS_DISABLE_TESTING_ASSISTANT } from 'wcstripe/stripe-utils/constants';

/**
 * Gets the Stripe Developer Widget options.
 *
 * @return {Object} The Stripe Developer Widget options.
 */
export const getStripeDevWidgetOptions = () => {
	const options = STRIPE_JS_OPTIONS_DISABLE_TESTING_ASSISTANT;

	// Product and cart pages only localize the express checkout params.
	const showWidget =
		getStripeServerData()?.showStripeDeveloperWidget ||
		( typeof wc_stripe_express_checkout_params !== 'undefined' && // eslint-disable-line camelcase
			wc_stripe_express_checkout_params?.stripe?.show_developer_widget ); // eslint-disable-line camelcase

	if ( ! showWidget ) {
		return options;
	}

	return {
		...options,
		developerTools: {
			...options.developerTools,
			assistant: {
				...options.developerTools.assistant,
				enabled: true,
			},
		},
	};
};
