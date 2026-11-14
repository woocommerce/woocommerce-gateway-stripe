/* global wc_add_to_cart_variation_params, wc_stripe_settings_params */
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
	} )
		.then( () => {
			// Update the localized params only on success so that stale
			// data doesn't cause dismissed banners to reappear after tab
			// navigation or other re-renders within the SPA.  We skip
			// this on failure to avoid a mismatch between client and
			// server state.
			// eslint-disable-next-line camelcase
			if ( typeof wc_stripe_settings_params !== 'undefined' ) {
				// Map the option key to the corresponding localized param key.
				const optionToParamMap = {
					wc_stripe_show_bnpl_promotion_banner:
						'show_bnpl_promotional_banner',
					wc_stripe_show_oc_promotion_banner:
						'show_oc_promotional_banner',
					wc_stripe_show_stripe_tax_banner: 'show_stripe_tax_banner',
					wc_stripe_show_customization_notice:
						'show_customization_notice',
					wc_stripe_show_optimized_checkout_notice:
						'show_optimized_checkout_notice',
					wc_stripe_show_stripe_first_method_notice:
						'show_stripe_first_method_notice',
				};
				const paramKey = optionToParamMap[ noticeKey ];
				if ( paramKey ) {
					// eslint-disable-next-line camelcase
					wc_stripe_settings_params[ paramKey ] = false;
				}
			}
		} )
		.catch( () => {
			// Swallow the rejection — dismissNotice is fire-and-forget.
			// The callback in .finally() handles caller cleanup.
		} )
		.finally( () => {
			if ( typeof callback === 'function' ) {
				callback();
			}
		} );
};

/**
 * Moves Stripe to the top of the WooCommerce payment gateway order.
 */
export const moveStripeToTop = () => {
	return apiFetch( {
		path: '/wc/v3/wc_stripe/settings/set_stripe_gateways_first',
		method: 'POST',
	} );
};
