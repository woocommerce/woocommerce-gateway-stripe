/**
 * Handle display of payment method instructions
 *
 * @param {string} method       The payment method name.
 * @param {string} checkoutType The checkout type. Must be 'classic' or 'blocks'.
 */
export const handleDisplayOfPaymentInstructions = (
	method,
	checkoutType = 'classic'
) => {
	document
		.querySelectorAll( '.wc-stripe-payment-method-instruction' )
		?.forEach( ( element ) => {
			element.style.display = 'none';
		} );

	// Don't show instructions if we're not on the Stripe payment method.
	if ( ! isStripeSelectedPaymentMethod( checkoutType ) ) {
		return;
	}

	const currentInstructionsDiv = document.getElementById(
		'wc-stripe-payment-method-instructions-' + method
	);
	if ( currentInstructionsDiv ) {
		currentInstructionsDiv.style.display = 'block';
	}
};

/**
 * Helper method to determine if the Stripe OCS payment method is selected.
 *
 * @param {string} checkoutType Whether we are in 'classic' checkout or 'blocks' checkout.
 * @return {boolean} Whether the Stripe payment method is selected.
 */
const isStripeSelectedPaymentMethod = ( checkoutType = 'classic' ) => {
	if ( checkoutType === 'classic' ) {
		const selectedClassicPaymentMethod = document.querySelector(
			'.woocommerce-checkout input[name="payment_method"]:checked'
		);
		return selectedClassicPaymentMethod?.id === 'payment_method_stripe';
	}

	const selectedBlocksPaymentMethod = document.querySelector(
		'.wc-block-checkout__payment-method input[name="radio-control-wc-payment-method-options"]:checked'
	);
	return (
		selectedBlocksPaymentMethod?.value === 'stripe' &&
		selectedBlocksPaymentMethod?.id ===
			'radio-control-wc-payment-method-options-stripe'
	);
};
