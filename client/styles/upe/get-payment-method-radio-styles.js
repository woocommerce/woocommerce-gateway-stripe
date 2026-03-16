/**
 * @typedef {Object} PaymentMethodRadioStyles
 * @property {('input'|'label-before')} type    The type of radio element in use.
 * @property {HTMLElement|null}         element The DOM element of the radio element. Only applicable if type is 'input'.
 * @property {CSSStyleDeclaration}      styles  The computed styles for the radio element.
 * @property {boolean}                  checked Whether the radio element is checked.
 */

/**
 * Helper method to determine the type of radio icons in use, if any.
 *
 * @return {PaymentMethodRadioStyles|null} The type of radios in use or null if no radios are present.
 */
export const getPaymentMethodRadioStyles = () => {
	const allOtherPaymentMethods = document.querySelectorAll(
		'.woocommerce-checkout .wc_payment_methods .wc_payment_method:not(.payment_method_stripe)'
	);
	if ( allOtherPaymentMethods.length === 0 ) {
		return null;
	}

	// Find visible elements so we can compute widths correctly.
	const visibleOtherPaymentMethods = Array.from(
		allOtherPaymentMethods
	).filter( ( otherPaymentMethod ) => {
		return window.getComputedStyle( otherPaymentMethod ).display !== 'none';
	} );

	let otherPaymentMethodRadio = null;
	if ( visibleOtherPaymentMethods.length === 0 ) {
		otherPaymentMethodRadio = document.querySelector(
			'.woocommerce-checkout input[name="payment_method"][type="radio"]:not([id="payment_method_stripe"])'
		);
	} else {
		otherPaymentMethodRadio = visibleOtherPaymentMethods[ 0 ].querySelector(
			'input[name="payment_method"][type="radio"]'
		);
	}

	if ( ! otherPaymentMethodRadio ) {
		// No need to show radio icons if we don't have any other payment methods.
		return null;
	}

	const otherPaymentMethodRadioStyles = window.getComputedStyle(
		otherPaymentMethodRadio
	);

	const hiddenLengths = [ '0px', '1px' ];

	// If the radio inputs are not hidden and are not set to 0x0 or 1x1, show the radios.
	if (
		otherPaymentMethodRadioStyles.display !== 'none' &&
		otherPaymentMethodRadioStyles.appearance !== 'none' &&
		! hiddenLengths.includes( otherPaymentMethodRadioStyles.width ) &&
		! hiddenLengths.includes( otherPaymentMethodRadioStyles.height )
	) {
		return {
			type: 'input',
			element: otherPaymentMethodRadio,
			checked: otherPaymentMethodRadio.checked,
			styles: otherPaymentMethodRadioStyles,
		};
	}

	// Check if there is label::before content that provides a custom radio icon.
	const otherPaymentMethodLabel =
		otherPaymentMethodRadio.parentElement.querySelector( 'label' );

	if ( ! otherPaymentMethodLabel ) {
		return null;
	}

	const otherPaymentMethodLabelBeforeStyles = window.getComputedStyle(
		otherPaymentMethodLabel,
		'::before'
	);

	// If the ::before content is empty or the display is none, assume no radio icon.
	if (
		otherPaymentMethodLabelBeforeStyles.content === 'none' ||
		otherPaymentMethodLabelBeforeStyles.display === 'none'
	) {
		return null;
	}

	if (
		hiddenLengths.includes( otherPaymentMethodLabelBeforeStyles.width ) ||
		hiddenLengths.includes( otherPaymentMethodLabelBeforeStyles.height )
	) {
		return null;
	}

	// Otherwise assume we have a custom radio icon.
	return {
		type: 'label-before',
		element: null,
		checked: otherPaymentMethodRadio.checked,
		styles: otherPaymentMethodLabelBeforeStyles,
	};
};
