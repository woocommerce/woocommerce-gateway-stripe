export const applySinglePaymentElementStyles = () => {
	// Add the single payment element class to the Stripe payment method elements.
	const stripePaymentMethodContent = document.getElementById(
		'radio-control-wc-payment-method-options-stripe__content'
	);
	stripePaymentMethodContent.classList.add( 'single-payment-element' );
	const stripePaymentMethodLabel = document.getElementById(
		'radio-control-wc-payment-method-options-stripe__label'
	);
	stripePaymentMethodLabel.classList.add( 'single-payment-element' );
	// Style the Stripe iframe to remove the margin.
	const stripeIFrame = document.querySelector(
		'.wcstripe-payment-element iframe'
	);
	stripeIFrame.style.margin = 0;
};
