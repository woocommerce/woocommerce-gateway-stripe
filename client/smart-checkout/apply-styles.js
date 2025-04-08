const SINGLE_PAYMENT_ELEMENT_CLASS = 'single-payment-element';

export const applyStyles = () => {
	// Add the single payment element class to the Stripe payment method elements.
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__content'
		)
		.classList.add( SINGLE_PAYMENT_ELEMENT_CLASS );
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__label'
		)
		.classList.add( SINGLE_PAYMENT_ELEMENT_CLASS );

	// Style the Stripe iframe to remove the margin and set width to 100%.
	const stripeIframe = document.querySelector(
		'.wcstripe-payment-element iframe'
	);
	stripeIframe.style.margin = 0;
	stripeIframe.style.width = '100%';
};
