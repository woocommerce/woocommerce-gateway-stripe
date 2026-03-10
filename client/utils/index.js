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
	} ).finally( callback );
};
