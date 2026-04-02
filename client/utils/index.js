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

export const dismissNotice = ( noticeKey, callback ) => {
	apiFetch( {
		path: '/wc/v3/wc_stripe/settings/notice',
		method: 'POST',
		data: { [ noticeKey ]: 'no' },
	} ).finally( () => {
		// Update the localized params so that stale data doesn't
		// cause dismissed banners to reappear after tab navigation
		// or other re-renders within the SPA.
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
			};
			const paramKey = optionToParamMap[ noticeKey ];
			if ( paramKey ) {
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params[ paramKey ] = false;
			}
		}
		if ( callback ) {
			callback();
		}
	} );
};
