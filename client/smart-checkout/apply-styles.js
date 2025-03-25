const SINGLE_PAYMENT_ELEMENT_CLASS = 'single-payment-element';

/**
 * Applies styles to the Smart Checkout elements.
 *
 * @param {string} paymentMethodOptionsSelector The selector for the payment method options.
 * @param {string} stripeLabelSelector The selector for the Stripe label.
 * @param {boolean} addSmartCheckoutClass Whether to add the single payment element class.
 * @param {boolean} styleStripeIframe Whether to style the Stripe iframe.
 */
export const applyStyles = (
	paymentMethodOptionsSelector,
	stripeLabelSelector,
	addSmartCheckoutClass = false,
	styleStripeIframe = false
) => {
	// Hide the Stripe radio button when it is the only available payment method.
	const paymentMethodOptions = document.querySelectorAll(
		paymentMethodOptionsSelector
	);
	if ( paymentMethodOptions.length === 1 ) {
		document.querySelector( stripeLabelSelector ).style.display = 'none';
	}

	// Add the single payment element class to the Stripe payment method elements.
	if ( addSmartCheckoutClass ) {
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
	}

	// Style the Stripe iframe to remove the margin and set width to 100%.
	if ( styleStripeIframe ) {
		const stripeIframe = document.querySelector(
			'.wcstripe-payment-element iframe'
		);
		stripeIframe.style.margin = 0;
		stripeIframe.style.width = '100%';
	}
};
