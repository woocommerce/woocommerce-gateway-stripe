/* global wc_stripe_upe_params, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}          The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	const config =
		'undefined' !== typeof wc_stripe_upe_params
			? wc_stripe_upe_params
			: wc.wcSettings.getSetting( 'stripe_data' );

	return config[ name ] || null;
};

/**
 * Construct WC AJAX endpoint URL.
 *
 * @param {string} ajaxURL AJAX URL.
 * @param {string} endpoint Request endpoint URL.
 * @param {string} prefix Optional prefix for endpoint action.
 * @return {string} URL with interpolated ednpoint.
 */
export const buildAjaxURL = ( ajaxURL, endpoint, prefix = 'wc_stripe_' ) =>
	ajaxURL.toString().replace( '%%endpoint%%', prefix + endpoint );
