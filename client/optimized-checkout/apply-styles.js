const OPTIMIZED_CHECKOUT_ELEMENT_CLASS = 'optimized-checkout-element';

/**
 * Applies Optimized Checkout styles to the Stripe payment method elements.
 * Adds the optimized checkout element class and removes iframe margin.
 */
export const applyStyles = () => {
	// Add the optimized checkout element class to the Stripe payment method elements.
	// Non-deferred methods (BLIK, ACSS) render as their own payment option, so the OC
	// container's `stripe` nodes aren't in the DOM when their processor runs this; guard
	// against null instead of crashing the checkout React tree.
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__content'
		)
		?.classList.add( OPTIMIZED_CHECKOUT_ELEMENT_CLASS );
	document
		.getElementById(
			'radio-control-wc-payment-method-options-stripe__label'
		)
		?.classList.add( OPTIMIZED_CHECKOUT_ELEMENT_CLASS );

	// Style the Stripe iframe to remove the margin and set width to 100%.
	const stripeIframe = document.querySelector(
		'.wcstripe-payment-element iframe'
	);

	if ( stripeIframe ) {
		stripeIframe.style.margin = 0;
		stripeIframe.style.width = '100%';
	}
};
