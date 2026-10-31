/* global wc_stripe_upe_params */
import { getSetting } from '@woocommerce/settings';

/**
 * @typedef {import('./type-defs').StripeServerData} StripeServerData
 */

/**
 * Stripe data comes form the server passed on a global object.
 *
 * @return  {StripeServerData|null} Stripe server data, or null when it isn't localized on the page.
 */
export const getStripeServerData = () => {
	let data = null;

	// eslint-disable-next-line camelcase
	if ( typeof wc_stripe_upe_params !== 'undefined' ) {
		data = wc_stripe_upe_params; // eslint-disable-line camelcase
	} else {
		// 'stripe_data' is available via wc-settings on block checkout only.
		data = getSetting( 'stripe_data', null );
	}

	return data || null;
};
