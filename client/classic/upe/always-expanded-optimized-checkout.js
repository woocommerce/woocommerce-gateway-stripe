import { getStripePaymentElement } from './payment-processing';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';

/**
 * Initialize always expanded optimized checkout for classic checkout.
 *
 * @param {jQuery} $ The jQuery object.
 */
export const initializeAlwaysExpandedOptimizedCheckout = ( $ ) => {
	// Check if we have any saved payment methods.
	const savedStripePaymentMethods = document.querySelectorAll(
		'.woocommerce-checkout-payment .payment_methods .payment_box.payment_method_stripe .wc-saved-payment-methods .woocommerce-SavedPaymentMethods-token'
	);

	// If we have any saved payment methods, disable the always expanded mode as
	// it does not work with saved payment methods.
	if ( savedStripePaymentMethods.length > 0 ) {
		return;
	}

	/**
	 * Get the Stripe payment box.
	 *
	 * @return {jQuery} The Stripe payment box.
	 */
	const getStripePaymentBox = () =>
		$(
			'.woocommerce-checkout-payment .payment_methods .payment_box.payment_method_stripe'
		);

	/**
	 * Keep the Stripe payment box visible.
	 */
	const keepPaymentBoxVisible = async () => {
		const $stripePaymentMethod = getStripePaymentBox();
		if ( ! $stripePaymentMethod.length ) {
			return;
		}
		// Stop animation - clear the animation queue and stop jumpToEnd.
		// Then immediately (re)show the element.
		$stripePaymentMethod.stop( true, false ).show();

		// Prevent any manual manipulation of the payment method box's size.
		// The animations seem to try, but that generates incorrect fixed sizes.
		const stripePaymentMethodBox = $stripePaymentMethod.get( 0 );
		if ( stripePaymentMethodBox ) {
			stripePaymentMethodBox.style.height = '';
			stripePaymentMethodBox.style.marginTop = '';
			stripePaymentMethodBox.style.marginBottom = '';
			stripePaymentMethodBox.style.paddingTop = '';
			stripePaymentMethodBox.style.paddingBottom = '';
		}

		// If we selected a different payment method, collapse the Stripe payment element.
		const selectedPaymentMethod = document.querySelector(
			'.woocommerce-checkout-payment input[name="payment_method"]:checked'
		);
		if ( selectedPaymentMethod?.id !== 'payment_method_stripe' ) {
			try {
				const paymentElement =
					await getStripePaymentElement( PAYMENT_METHOD_CARD );
				paymentElement?.collapse();
			} catch {
				// If we don't have a payment element, no need to collapse.
			}
		}
	};

	$( document.body ).on( 'payment_method_selected', keepPaymentBoxVisible );
	$( document.body ).on( 'updated_checkout', keepPaymentBoxVisible );
	$( document.body ).on( 'init_checkout', keepPaymentBoxVisible );

	/**
	 * Helper to contract all non-Stripe payment methods.
	 */
	const contractOtherPaymentMethods = () => {
		const paymentMethodsRoot = document.querySelector(
			'.woocommerce-checkout-payment .payment_methods'
		);
		const allPaymentMethodBoxes = Array.from(
			paymentMethodsRoot?.getElementsByClassName( 'payment_box' ) ?? []
		);
		const otherPaymentMethodBoxes = allPaymentMethodBoxes.filter(
			( paymentMethodBox ) =>
				! paymentMethodBox.classList.contains( 'payment_method_stripe' )
		);
		const $wrappedPaymentMethodBoxes = $( otherPaymentMethodBoxes );
		$wrappedPaymentMethodBoxes.filter( ':visible' ).slideUp( 230 );
	};

	/**
	 * Helper to trigger selection of the radio button for the Stripe payment method,
	 * and then contract the other payment methods.
	 */
	const selectStripePaymentMethod = () => {
		const stripeRadioButton = document.getElementById(
			'payment_method_stripe'
		);
		if ( stripeRadioButton && ! stripeRadioButton.checked ) {
			stripeRadioButton.click();
		}
		// Now contract the other payment methods.
		contractOtherPaymentMethods();
	};

	/**
	 * Ensure that the Stripe payment method is marked as selected when the following events occur:
	 * - User clicks on the Stripe payment box - this only includes content outside the Stripe iframe.
	 * - User focuses on the Stripe payment element within the iframe.
	 */
	const reconfigureStripePaymentSelection = () => {
		const stripePaymentBox = getStripePaymentBox();
		// Use a namespaced event to ensure we remove existing event listeners before (re)adding the listener.
		stripePaymentBox
			.off( 'click.wc-stripe-expanded-ocs' )
			.on( 'click.wc-stripe-expanded-ocs', selectStripePaymentMethod );

		getStripePaymentElement( PAYMENT_METHOD_CARD )
			.then( ( paymentElement ) => {
				if ( paymentElement ) {
					paymentElement.off( 'focus', selectStripePaymentMethod );
					paymentElement.on( 'focus', selectStripePaymentMethod );
				}
			} )
			.catch( () => {
				// If we fail to get the payment element, no further action needed.
			} );
	};

	$( document.body ).on( 'init_checkout', reconfigureStripePaymentSelection );
	$( document.body ).on(
		'updated_checkout',
		reconfigureStripePaymentSelection
	);

	/**
	 * Helper to add the 'wc-stripe-optimized-checkout' class to the Stripe payment method root element.
	 * This is used to enable specific CSS rules.
	 */
	const addOcsRootClass = () => {
		const stripePaymentMethodRoot = document.querySelector(
			'.woocommerce-checkout-payment .payment_methods .wc_payment_method.payment_method_stripe'
		);
		stripePaymentMethodRoot?.classList.add(
			'wc-stripe-optimized-checkout'
		);
	};

	$( document.body ).on( 'init_checkout', addOcsRootClass );
	$( document.body ).on( 'updated_checkout', addOcsRootClass );
};
