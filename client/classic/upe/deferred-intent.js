import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	getStripeServerData,
	isPaymentMethodRestrictedToLocation,
	isUsingSavedPaymentMethod,
	paymentMethodSupportsDeferredIntent,
	togglePaymentMethodForCountry,
} from '../../stripe-utils';
import './style.scss';
import {
	confirmVoucherPayment,
	confirmWalletPayment,
	createAndConfirmSetupIntent,
	getMountedUPEComponent,
	initializeUPEComponents,
	mountStripePaymentElement,
	processPayment,
} from './payment-processing';

jQuery( function ( $ ) {
	const stripeServerData = getStripeServerData();

	// Create an API object, which will be used throughout the checkout.
	const api = new WCStripeAPI(
		stripeServerData,
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	// Initialize the list of Stripe Elements to be mounted when UPE is enabled.
	initializeUPEComponents();

	function restrictPaymentMethodToLocation( upeElement ) {
		if ( isPaymentMethodRestrictedToLocation( upeElement ) ) {
			togglePaymentMethodForCountry( upeElement );

			// This event only applies to the checkout form, but not "pay for order" or "add payment method" pages.
			$( '#billing_country' ).on( 'change', function () {
				togglePaymentMethodForCountry( upeElement );
			} );
		}
	}

	/**
	 * Maybe mounts the Stripe Payment Element on the page.
	 *
	 * @return {Promise<void>}
	 */
	async function maybeMountStripePaymentElement() {
		// If the card element selector doesn't exist, do nothing.
		// For example, when a 100% discount coupon is applied.
		if ( ! $( '.wc-stripe-upe-element' ).length ) {
			return;
		}

		const selectedMethod = getSelectedUPEGatewayPaymentMethod();
		for ( const upeElement of $( '.wc-stripe-upe-element' ).toArray() ) {
			// Maybe hide the payment method based on the billing country.
			restrictPaymentMethodToLocation( upeElement );

			// Don't mount if it's already mounted.
			if ( $( upeElement ).children().length ) {
				continue;
			}

			// Payment methods that don't support deferred intents don't need to be mounted unless they are selected.
			if (
				upeElement.dataset.paymentMethodType !== selectedMethod &&
				! paymentMethodSupportsDeferredIntent( upeElement )
			) {
				continue;
			}

			await mountStripePaymentElement( api, upeElement );
		}
	}

	// Only attempt to mount the card element once that section of the page has loaded.
	// We can use the updated_checkout event for this.
	$( document.body ).on( 'updated_checkout', () => {
		maybeMountStripePaymentElement();
	} );

	function processPaymentIfNotUsingSavedMethod( $form ) {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, $form, paymentMethodType );
		}
	}

	$( 'form.checkout' ).on( generateCheckoutEventNames(), function () {
		return processPaymentIfNotUsingSavedMethod( $( this ) );
	} );

	// Mount the Stripe Payment Elements onto the Add Payment Method page and Pay for Order page.
	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		maybeMountStripePaymentElement();

		// For payment methods that don't support deferred intents, we mount the Payment Element only when the PM is selected.
		$( 'input[name="payment_method"]' ).on( 'change', () => {
			maybeMountStripePaymentElement();
		} );
	}

	// For payment methods that don't support deferred intents, we mount the Payment Element only when the PM is selected.
	$( 'form.checkout' ).on( 'change', 'input[name="payment_method"]', () => {
		maybeMountStripePaymentElement();
	} );

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

	/**
	 * Checks if the URL hash starts with #wc-stripe-voucher- or #wc-stripe-wallet- and whether we
	 * should display the relevant confirmation modal.
	 */
	function maybeConfirmVoucherOrWalletPayment() {
		if (
			stripeServerData?.isOrderPay ||
			stripeServerData?.isCheckout ||
			stripeServerData?.isChangingPayment
		) {
			if ( window.location.hash.startsWith( '#wc-stripe-voucher-' ) ) {
				confirmVoucherPayment(
					api,
					stripeServerData?.isOrderPay
						? $( '#order_review' )
						: $( 'form.checkout' )
				);
			} else if (
				window.location.hash.startsWith( '#wc-stripe-wallet-' )
			) {
				confirmWalletPayment(
					api,
					stripeServerData?.isOrderPay ||
						stripeServerData?.isChangingPayment
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

	// Bind the handling of the setup future usage option to the saving checkbox when OC is enabled.
	if ( stripeServerData?.shouldShowOptimizedCheckout ) {
		$( document ).on( 'change', '#wc-stripe-new-payment-method', () => {
			const selectedMethod = getSelectedUPEGatewayPaymentMethod();
			const component = getMountedUPEComponent( selectedMethod );

			if ( component && component.elements ) {
				const isChecked = $( '#wc-stripe-new-payment-method' ).is(
					':checked'
				);
				const cartContainsSubscription =
					stripeServerData?.cartContainsSubscription;

				// Update only the setupFutureUsage on the Elements object and preserve user input.
				component.elements.update( {
					setupFutureUsage:
						cartContainsSubscription || isChecked
							? 'off_session'
							: null,
				} );
			}
		} );
	}
} );
