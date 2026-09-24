import { getStripeDevWidgetOptions } from 'wcstripe/stripe-utils';
import { getSharedStripeInstance } from 'wcstripe/stripe-utils/shared-stripe-instance';

/**
 * @typedef {import('./core').WCStripeApiClient} WCStripeApiClient
 */

/**
 * Returns the page's Stripe instance for the client's key and locale, shared across
 * every WooCommerce Stripe bundle rather than created per client.
 *
 * @param {WCStripeApiClient} client The API client.
 * @return {Object} The Stripe Object.
 */
export const getStripe = ( client ) => {
	const { key, locale } = client.options;

	return getSharedStripeInstance( key, {
		locale,
		...getStripeDevWidgetOptions(),
	} );
};

/**
 * Load Stripe for payment request button.
 *
 * @param {WCStripeApiClient} client The API client.
 * @return {Promise} Promise with the Stripe object or an error.
 */
export const loadStripe = ( client ) =>
	new Promise( ( resolve ) => {
		try {
			resolve( getStripe( client ) );
		} catch ( error ) {
			// In order to avoid showing console error publicly to users,
			// we resolve instead of rejecting when there is an error.
			resolve( { error } );
		}
	} );
