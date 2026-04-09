/* global wc_add_to_cart_variation_params */
import apiFetch from '@wordpress/api-fetch';

export const getAddToCartVariationParams = ( key ) => {
	// eslint-disable-next-line camelcase
	const wcAddToCartVariationParams = wc_add_to_cart_variation_params;
	if ( ! wcAddToCartVariationParams || ! wcAddToCartVariationParams[ key ] ) {
		return null;
	}

	return wcAddToCartVariationParams[ key ];
};

/**
 * Dismisses a notice by making an API request to the server.
 *
 * @param {string}   noticeKey The key of the notice to dismiss.
 * @param {Function} callback  The callback to call when the request is complete.
 */
export const dismissNotice = ( noticeKey, callback ) => {
	apiFetch( {
		path: '/wc/v3/wc_stripe/settings/notice',
		method: 'POST',
		data: { [ noticeKey ]: 'no' },
	} ).finally( callback );
};

/**
 * Moves Stripe to the top of the WooCommerce payment gateway order.
 */
export const moveStripeToTop = () => {
	apiFetch( {
		path: '/wc/v3/wc_stripe/settings/set_stripe_gateways_first',
		method: 'POST',
	} );
};
