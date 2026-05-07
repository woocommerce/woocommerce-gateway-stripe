import { getStripeServerData } from './utils';
import { STRIPE_JS_OPTIONS_DISABLE_TESTING_ASSISTANT } from 'wcstripe/stripe-utils/constants';

/**
 * Gets the Stripe Developer Widget options.
 *
 * @return {Object} The Stripe Developer Widget options.
 */
export const getStripeDevWidgetOptions = () => {
	const options = STRIPE_JS_OPTIONS_DISABLE_TESTING_ASSISTANT;

	const stripeServerData = getStripeServerData();

	if ( ! stripeServerData?.showStripeDeveloperWidget ) {
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
