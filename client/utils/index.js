/* global wc_add_to_cart_variation_params, wc_stripe_settings_params */
export const getAddToCartVariationParams = ( key ) => {
	// eslint-disable-next-line camelcase
	const wcAddToCartVariationParams = wc_add_to_cart_variation_params;
	if ( ! wcAddToCartVariationParams || ! wcAddToCartVariationParams[ key ] ) {
		return null;
	}

	return wcAddToCartVariationParams[ key ];
};

/**
 * Check if APMs are deprecated. Meaning it is past Oct 31st, 2024 and the legacy checkout is enabled.
 *
 * @return {boolean} Whether APMs are deprecated.
 */
export const areAPMsDeprecated = () =>
	new Date() > new Date( '2024-10-31' ) &&
	// eslint-disable-next-line camelcase
	wc_stripe_settings_params.is_upe_checkout_enabled === '1';
