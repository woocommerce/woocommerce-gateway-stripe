/**
 * Handle display of payment method instructions
 *
 * @param {string} method The payment method name.
 */
export const handleDisplayOfPaymentInstructions = ( method ) => {
	document
		.querySelectorAll( '.wc-stripe-payment-method-instruction' )
		?.forEach( ( element ) => {
			element.style.display = 'none';
		} );

	// Don't show instructions if we're not on the Stripe payment method.
	const selectedPaymentMethod = document.querySelector(
		'.woocommerce-checkout input[name="payment_method"]:checked'
	);
	if ( selectedPaymentMethod?.id !== 'payment_method_stripe' ) {
		return;
	}

	const currentInstructionsDiv = document.getElementById(
		'wc-stripe-payment-method-instructions-' + method
	);
	if ( currentInstructionsDiv ) {
		currentInstructionsDiv.style.display = 'block';
	}
};
