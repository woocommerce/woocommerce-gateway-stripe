export const applySinglePaymentElementStyles = () => {
	// Hide the Stripe radio button when it is the only available payment method.
	const paymentMethodOptions = document.querySelectorAll(
		'input[name=payment_method]'
	);
	if ( paymentMethodOptions.length === 1 ) {
		document.querySelector(
			'label[for=payment_method_stripe]'
		).style.display = 'none';
	}
};
