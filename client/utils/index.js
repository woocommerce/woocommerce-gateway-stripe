/* global wc_add_to_cart_variation_params */
export const getAddToCartVariationParams = ( key ) => {
	// eslint-disable-next-line camelcase
	const wcAddToCartVariationParams = wc_add_to_cart_variation_params;
	if ( ! wcAddToCartVariationParams || ! wcAddToCartVariationParams[ key ] ) {
		return null;
	}

	return wcAddToCartVariationParams[ key ];
};
