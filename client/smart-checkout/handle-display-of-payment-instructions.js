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
	const currentInstructionsDiv = document.getElementById(
		'wc-stripe-payment-method-instructions-' + method
	);
	if ( currentInstructionsDiv ) {
		currentInstructionsDiv.style.display = 'block';
	}
};
