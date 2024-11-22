import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	getStripeServerData,
	isPaymentMethodRestrictedToLocation,
	isUsingSavedPaymentMethod,
	togglePaymentMethodForCountry,
} from '../../stripe-utils';
import './style.scss';
import {
	confirmVoucherPayment,
	confirmWalletPayment,
	createAndConfirmSetupIntent,
	mountStripePaymentElement,
	processPayment,
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

	// Mount the Stripe Payment Elements onto the Add Payment Method page and Pay for Order page.
	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		maybeMountStripePaymentElement();
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

	// Pay for Order page submit.
	$( '#order_review' ).on( 'submit', () => {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment(
				api,
				$( '#order_review' ),
				paymentMethodType
			);
		}
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
				restrictPaymentMethodToLocation( upeElement );
			}
		}
	}

	function restrictPaymentMethodToLocation( upeElement ) {
		if ( isPaymentMethodRestrictedToLocation( upeElement ) ) {
			togglePaymentMethodForCountry( upeElement );

			// this event only applies to the checkout form, but not "place order" or "add payment method" pages.
			$( '#billing_country' ).on( 'change', function () {
				togglePaymentMethodForCountry( upeElement );
			} );
		}
	}

	/**
	 * Checks if the URL hash starts with #wc-stripe-voucher- or #wc-stripe-wallet- and whether we
	 * should display the relevant confirmation modal.
	 */
	function maybeConfirmVoucherOrWalletPayment() {
		if (
			getStripeServerData()?.isOrderPay ||
			getStripeServerData()?.isCheckout ||
			getStripeServerData()?.isChangingPayment
		) {
			if ( window.location.hash.startsWith( '#wc-stripe-voucher-' ) ) {
				confirmVoucherPayment(
					api,
					getStripeServerData()?.isOrderPay
						? $( '#order_review' )
						: $( 'form.checkout' )
				);
			} else if (
				window.location.hash.startsWith( '#wc-stripe-wallet-' )
			) {
				confirmWalletPayment(
					api,
					getStripeServerData()?.isOrderPay ||
						getStripeServerData()?.isChangingPayment
						? $( '#order_review' )
						: $( 'form.checkout' )
				);
			}
		}
	}

	// On every page load and on hash change, check to see whether we should display the Voucher (Boleto/Oxxo/Multibanco) or Wallet (CashApp/WeChat Pay) modal.
	// Every page load is needed for the Pay for Order page which doesn't trigger the hash change.
	maybeConfirmVoucherOrWalletPayment();
	$( window ).on( 'hashchange', () => {
		maybeConfirmVoucherOrWalletPayment();
	} );
} );
