import jQuery from 'jquery';
import { debounce } from 'lodash';
import { addFilter, doAction } from '@wordpress/hooks';

jQuery( ( $ ) => {
	$( document.body ).on( 'woocommerce_variation_has_changed', async () => {
		doAction( 'wcstripe.express-checkout.update-button-data' );
	} );
} );

// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
// when the customer clicks on the button before the debounced event is processed.
jQuery( ( $ ) => {
	const $quantityInput = $( '.quantity' );
	const handleQuantityChange = () => {
		// check if element isn't already blocked before calling block() to avoid blinking overlay issues
		// blockUI.isBlocked is either undefined or 0 when element is not blocked
		if (
			$( '#wc-stripe-express-checkout-element' ).data(
				'blockUI.isBlocked'
			)
		) {
			return;
		}

		$( '#wc-stripe-express-checkout-element' ).block( {
			message: null,
		} );
	};
	$quantityInput.on( 'input', '.qty', handleQuantityChange );
	$quantityInput.on(
		'input',
		'.qty',
		debounce( async () => {
			doAction( 'wcstripe.express-checkout.update-button-data' );
		}, 250 )
	);
} );

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
addFilter(
	'wcstripe.express-checkout.cart-add-item',
	'automattic/wcstripe/express-checkout',
	( productData ) => {
		const $variationsForm = jQuery( '.variations_form' );
		if ( ! $variationsForm.length ) {
			return productData;
		}

		const attributes = [];
		const $variationSelectElements = $variationsForm.find(
			'.variations select'
		);
		$variationSelectElements.each( function () {
			const $select = jQuery( this );
			const attributeName =
				$select.data( 'attribute_name' ) || $select.attr( 'name' );

			attributes.push( {
				// The Store API accepts the variable attribute's label, rather than an internal identifier:
				// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/src/StoreApi/docs/cart.md#add-item
				// It's an unfortunate hack that doesn't work when labels have special characters in them.
				attribute: document.querySelector(
					`label[for="${ attributeName.replace(
						'attribute_',
						''
					) }"]`
				).innerHTML,
				value: $select.val() || '',
			} );
		} );

		return {
			...productData,
			variation: [ ...productData.variation, ...attributes ],
		};
	}
);
