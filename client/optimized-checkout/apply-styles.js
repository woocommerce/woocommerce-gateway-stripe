const OPTIMIZED_CHECKOUT_ELEMENT_CLASS = 'optimized-checkout-element';

export const applyStyles = () => {
	// Add the optimized checkout element class to the Stripe payment method elements.
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__content'
		)
		.classList.add( OPTIMIZED_CHECKOUT_ELEMENT_CLASS );
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__label'
		)
		.classList.add( OPTIMIZED_CHECKOUT_ELEMENT_CLASS );

	// Style the Stripe iframe to remove the margin and set width to 100%.
	const stripeIframe = document.querySelector(
		'.wcstripe-payment-element iframe'
	);
	stripeIframe.style.margin = 0;
	stripeIframe.style.width = '100%';
};
