export const applySinglePaymentElementStyles = () => {
	// Add the single payment element class to the Stripe payment method elements.
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__content'
		)
		.classList.add( 'single-payment-element' );
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__label'
		)
		.classList.add( 'single-payment-element' );

	// Style the Stripe iframe to remove the margin.
	document.querySelector(
		'.wcstripe-payment-element iframe'
	).style.margin = 0;

	// Hide the Stripe radio button when it is the only available payment method.
	const paymentMethodOptions = document.querySelectorAll(
		'.wc-payment-method-options__option'
	);
	if ( paymentMethodOptions.length === 1 ) {
		document.querySelector(
			'.wc-payment-method-options__option input[type="radio"]'
		).style.display = 'none';
	}
};
