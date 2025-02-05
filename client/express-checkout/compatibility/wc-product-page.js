import { addFilter } from '@wordpress/hooks';

/**
 * Sets the product ID when using the BlocksAPI and variations are present.
 */
addFilter(
	'wcstripe.express-checkout.cart-add-item',
	'automattic/wcstripe/express-checkout',
	( productData ) => {
		const $variationInformation = jQuery( '.single_variation_wrap' );
		if ( ! $variationInformation.length ) {
			return productData;
		}

		const productId = $variationInformation
			.find( 'input[name="product_id"]' )
			.val();
		return {
			...productData,
			id: parseInt( productId, 10 ),
		};
	}
);

/**
 * Add extension data to the POST body.
 */
addFilter(
	'wcstripe.express-checkout.cart-add-item',
	'automattic/wcstripe/express-checkout',
	( productData ) => {
		const formData = jQuery( 'form.cart' ).serializeArray();
		const data = {};
		jQuery.each( formData, ( i, field ) => {
			if ( /^(addon-|wc_)/.test( field.name ) ) {
				if ( /\[\]$/.test( field.name ) ) {
					const fieldName = field.name.substring(
						0,
						field.name.length - 2
					);
					if ( data[ fieldName ] ) {
						data[ fieldName ].push( field.value );
					} else {
						data[ fieldName ] = [ field.value ];
					}
				} else {
					data[ field.name ] = field.value;
				}
			}
		} );
		return {
			...productData,
			...data,
		};
	}
);
