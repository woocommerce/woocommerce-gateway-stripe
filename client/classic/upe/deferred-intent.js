import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	getStripeServerData,
	isUsingSavedPaymentMethod,
} from '../../stripe-utils';
import './style.scss';
import {
	processPayment,
	mountStripePaymentElement,
	createAndConfirmSetupIntent,
} from './payment-processing';

jQuery( function ( $ ) {
	// Create an API object, which will be used throughout the checkout.
	const api = new WCStripeAPI(
		getStripeServerData(),
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	// Only attempt to mount the card element once that section of the page has loaded.
	// We can use the updated_checkout event for this.
	$( document.body ).on( 'updated_checkout', () => {
		maybeMountStripePaymentElement();
	} );

	$( 'form.checkout' ).on( generateCheckoutEventNames(), function () {
		return processPaymentIfNotUsingSavedMethod( $( this ) );
	} );

	function processPaymentIfNotUsingSavedMethod( $form ) {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, $form, paymentMethodType );
		}
	}

	// Mount the Stripe Payment Elements onto the Add Payment Method page and Pay for Order page..
	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		maybeMountStripePaymentElement();

		// This function runs before WooCommerce has attached its callbacks in tokenization-form.js so we need to add a slight delay and trigger the event again.
		setTimeout( () => {
			$( document.body ).trigger( 'wc-credit-card-form-init' );
		}, 0 );
	}

	// My Account > Payment Methods page submit.
	$( 'form#add_payment_method' ).on( 'submit', function () {
		return processPayment(
			api,
			$( 'form#add_payment_method' ),
			getSelectedUPEGatewayPaymentMethod(),
			createAndConfirmSetupIntent
		);
	} );

	// If the card element selector doesn't exist, then do nothing.
	// For example, when a 100% discount coupon is applied).
	// We also don't re-mount if already mounted in DOM.
	async function maybeMountStripePaymentElement() {
		if (
			$( '.wc-stripe-upe-element' ).length &&
			! $( '.wc-stripe-upe-element' ).children().length
		) {
			for ( const upeElement of $(
				'.wc-stripe-upe-element'
			).toArray() ) {
				await mountStripePaymentElement( api, upeElement );
			}
		}
	}
} );
