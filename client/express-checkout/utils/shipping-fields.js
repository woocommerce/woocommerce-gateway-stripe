/**
 * Internal dependencies
 */
import { normalizeShippingAddress, getExpressCheckoutData } from '.';

/**
 * Checks if the intermediate address is redacted for the given country.
 * CA and GB addresses are redacted and are causing errors until WooCommerce is able to
 * handle redacted addresses.
 * https://developers.google.com/pay/api/web/reference/response-objects#IntermediateAddress
 *
 * @param {string} country - The country code.
 *
 * @return {boolean} True if the postcode is redacted for the country, false otherwise.
 */
const isPostcodeRedactedForCountry = ( country ) => {
	return [ 'CA', 'GB' ].includes( country );
};

/*
 * Updates a field in a form with a new value.
 *
 * @param {String} formSelector - The selector for the form containing the field.
 * @param {Object} fieldName - The name of the field to update.
 * @param {Object} value - The new value for the field.
 */
const updateShortcodeField = ( formSelector, fieldName, value ) => {
	const field = document.querySelector(
		`${ formSelector } [name="${ fieldName }"]`
	);

	if ( ! field ) return;

	// Check if the field is a dropdown (country/state).
	if ( field.tagName === 'SELECT' && /country|state/.test( fieldName ) ) {
		const options = Array.from( field.options );
		const match = options.find(
			( opt ) =>
				opt.value === value ||
				opt.textContent.trim().toLowerCase() === value.toLowerCase()
		);

		if ( match ) {
			field.value = match.value;
			jQuery( field ).trigger( 'change' ).trigger( 'close' );
		}
	} else {
		// Default behavior for text inputs.
		field.value = value;
		jQuery( field ).trigger( 'change' );
	}
};

/**
 * Updates the WooCommerce Blocks shipping UI to reflect a new shipping address.
 *
 * @param {Object} eventAddress - The shipping address returned by the payment event.
 */
const updateBlocksShippingUI = ( eventAddress ) => {
	wp?.data
		?.dispatch( 'wc/store/cart' )
		?.setShippingAddress( normalizeShippingAddress( eventAddress ) );
};

/**
 * Updates the WooCommerce shortcode cart/checkout shipping UI to reflect a new shipping address.
 *
 * @param {Object} eventAddress - The shipping address returned by the payment event.
 */
const updateShortcodeShippingUI = ( eventAddress ) => {
	const context = getExpressCheckoutData( 'button_context' );
	const address = normalizeShippingAddress( eventAddress );

	const keys = [ 'country', 'state', 'city', 'postcode' ];

	if ( context === 'cart' ) {
		keys.forEach( ( key ) => {
			if ( address[ key ] ) {
				updateShortcodeField(
					'form.woocommerce-shipping-calculator',
					`calc_shipping_${ key }`,
					address[ key ]
				);
			}
		} );
		document
			.querySelector(
				'form.woocommerce-shipping-calculator [name="calc_shipping"]'
			)
			?.click();
	} else if ( context === 'checkout' ) {
		keys.forEach( ( key ) => {
			if ( address[ key ] ) {
				updateShortcodeField(
					'form.woocommerce-checkout',
					`billing_${ key }`,
					address[ key ]
				);
			}
		} );
	}
};

/**
 * Updates the WooCommerce shipping UI to reflect a new shipping address.
 *
 * Determines the current context (cart or checkout) and updates either
 * WooCommerce Blocks or shortcode-based shipping forms, if applicable.
 *
 * @param {Object} newAddress - The new shipping address object returned by the payment event.
 * @param {string} newAddress.country - The country code of the shipping address.
 * @param {string} [newAddress.state] - The state/province of the shipping address.
 * @param {string} [newAddress.city] - The city of the shipping address.
 * @param {string} [newAddress.postcode] - The postal/ZIP code of the shipping address.
 */
export const updateShippingAddressUI = ( newAddress ) => {
	const context = getExpressCheckoutData( 'button_context' );
	const isBlocks = getExpressCheckoutData( 'has_block' );

	if (
		[ 'cart', 'checkout' ].includes( context ) &&
		! isPostcodeRedactedForCountry( newAddress.country )
	) {
		if ( isBlocks ) {
			updateBlocksShippingUI( newAddress );
		} else {
			updateShortcodeShippingUI( newAddress );
		}
	}
};
