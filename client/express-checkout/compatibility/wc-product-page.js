import jQuery from 'jquery';
import { addFilter } from '@wordpress/hooks';

/**
 * Sets the product ID when using the BlocksAPI and single variation form is present.
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
