/**
 * Handle display of payment method instructions
 *
 * @param {string} method The payment method name.
 */
export const handleDisplayOfPaymentMethodInstructions = ( method ) => {
	document
		.querySelectorAll( '.wc-stripe-payment-method-instruction' )
		?.forEach( ( element ) => {
			element.style.display = 'none';
		} );
	document.getElementById(
		'wc-stripe-payment-method-instructions-' + method
	).style.display = 'block';
};
