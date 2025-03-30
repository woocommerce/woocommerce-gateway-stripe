export const applySinglePaymentElementStyles = () => {
	// Hide the Stripe radio button when it is the only available payment method.
	const paymentMethodOptions = document.querySelectorAll(
		'input[name=radio-control-wc-payment-method-options]'
	);
	if ( paymentMethodOptions.length === 1 ) {
		document.querySelector(
			'.wc-block-components-radio-control__option'
		).style.display = 'none';
	}

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

	// Style the Stripe iframe to remove the margin and set width to 100%.
	const stripeIframe = document.querySelector(
		'.wcstripe-payment-element iframe'
	);
	stripeIframe.style.margin = 0;
	stripeIframe.style.width = '100%';
};
