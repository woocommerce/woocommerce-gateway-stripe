import {
	getPaymentMethodRadioStyles,
	getElementBackgroundColor,
	isTransparentColor,
} from './utils.js';

/**
 * Build the full set of appearance rules for expanded Optimized Checkout.
 * NOTE: We do not overwrite the initial rules, we only add to them, as we don't want
 * to override any customisation specified by the merchant.
 *
 * @param {Object} initialRules The initial appearance rules that we need to build upon.
 * @return {Object} The full set of appearance rules for Optimized Checkout.
 */
export function getExpandedOptimizedCheckoutRules( initialRules = {} ) {
	const accordionItemRules = {};
	const accordionItemSelectedRules = {};
	const radioIconRules = {};
	const radioIconInnerCheckedRules = {};

	const paymentMethodRoot = document.querySelector(
		'.wc_payment_methods .payment_method_stripe'
	);
	if ( paymentMethodRoot ) {
		const paymentMethodRootStyles =
			window.getComputedStyle( paymentMethodRoot );

		if ( paymentMethodRootStyles.borderWidth === '0px' ) {
			accordionItemRules.borderWidth =
				paymentMethodRootStyles.borderWidth;
		} else {
			accordionItemRules.border = paymentMethodRootStyles.border;
		}

		accordionItemRules.borderRadius = paymentMethodRootStyles.borderRadius;
		accordionItemRules.boxShadow = paymentMethodRootStyles.boxShadow;
	}

	// Look at the payment method label to pick up styles that should specifically apply to the Stripe equivalents.
	const paymentMethodLabel = document.querySelector(
		'.wc_payment_methods .payment_method_stripe label[for="payment_method_stripe"]'
	);

	if ( paymentMethodLabel ) {
		const paymentMethodLabelStyles =
			window.getComputedStyle( paymentMethodLabel );

		accordionItemRules.fontFamily = paymentMethodLabelStyles.fontFamily;
		accordionItemRules.fontSize = paymentMethodLabelStyles.fontSize;
		accordionItemRules.fontVariant = paymentMethodLabelStyles.fontVariant;
		accordionItemRules.fontWeight = paymentMethodLabelStyles.fontWeight;
		accordionItemRules.fontSmooth = paymentMethodLabelStyles.fontSmooth;
		if ( paymentMethodLabelStyles[ '-webkit-font-smoothing' ] ) {
			accordionItemRules[ '-webkit-font-smoothing' ] =
				paymentMethodLabelStyles[ '-webkit-font-smoothing' ];
		}
		if ( paymentMethodLabelStyles[ '-moz-osx-font-smoothing' ] ) {
			accordionItemRules[ '-moz-osx-font-smoothing' ] =
				paymentMethodLabelStyles[ '-moz-osx-font-smoothing' ];
		}
		if ( paymentMethodLabelStyles.transition ) {
			accordionItemRules.transition = paymentMethodLabelStyles.transition;
		}

		// For left padding, add the left padding and margin, and then subtract 1px to account for the left border.
		const leftPaddingPx =
			parseFloat( paymentMethodLabelStyles.paddingLeft || '0' ) +
			parseFloat( paymentMethodLabelStyles.marginLeft || '0' );
		if ( leftPaddingPx > 1 ) {
			accordionItemRules.paddingLeft = `${ leftPaddingPx - 1 }px`;
		} else {
			accordionItemRules.paddingLeft = '0px';
		}

		// Check for a background color in the following elements:
		// 1. The payment method label
		// 2. The payment method <li> element
		// 3. The payment method <ul> element
		// 4. The overall #payment div
		const labelBackgroundColor = getElementBackgroundColor(
			paymentMethodLabel,
			{
				checkParent: true,
				maxDepth: 4,
			}
		);
		if ( labelBackgroundColor ) {
			accordionItemRules.backgroundColor = labelBackgroundColor;
		}

		if ( ! isTransparentColor( paymentMethodLabelStyles.color ) ) {
			accordionItemRules.color = paymentMethodLabelStyles.color;
			accordionItemSelectedRules.color = paymentMethodLabelStyles.color;
		}
	}

	const paymentMethodBox = document.querySelector(
		'.wc_payment_methods .payment_method_stripe .payment_box.payment_method_stripe'
	);
	const paymentMethodBoxStyles = paymentMethodBox
		? window.getComputedStyle( paymentMethodBox )
		: {};

	if (
		paymentMethodBoxStyles.backgroundColor &&
		! isTransparentColor( paymentMethodBoxStyles.backgroundColor )
	) {
		accordionItemSelectedRules.backgroundColor =
			paymentMethodBoxStyles.backgroundColor;
	}

	if ( ! accordionItemSelectedRules.color ) {
		accordionItemSelectedRules.color = 'inherit';
	}

	const paymentMethodRadioStyles = getPaymentMethodRadioStyles();
	if ( paymentMethodRadioStyles ) {
		// Multiply base size by 1.1 to account for the SVG image not filling the space completely.
		if ( paymentMethodRadioStyles.styles.width !== 'auto' ) {
			radioIconRules.width = `${
				parseFloat( paymentMethodRadioStyles.styles.width ) * 1.1
			}px`;
		} else if (
			paymentMethodRadioStyles.element &&
			paymentMethodRadioStyles.element.offsetWidth
		) {
			radioIconRules.width = `${
				paymentMethodRadioStyles.element.offsetWidth * 1.1
			}px`;
		} else if ( paymentMethodRadioStyles.type === 'label-before' ) {
			radioIconRules.width = paymentMethodRadioStyles.styles.fontSize;
		}
		// Try to replicate the color values
		if ( paymentMethodRadioStyles.checked ) {
			radioIconInnerCheckedRules.fill =
				paymentMethodRadioStyles.styles.color;
		} else {
			radioIconRules.color = paymentMethodRadioStyles.styles.color;
		}
	}

	// Merge the rules, always giving preference to the initial rules.
	return {
		...initialRules,
		'.AccordionItem': {
			...accordionItemRules,
			...( initialRules?.[ '.AccordionItem' ] || {} ),
		},
		'.AccordionItem--selected': {
			...accordionItemSelectedRules,
			...( initialRules?.[ '.AccordionItem--selected' ] || {} ),
		},
		'.RadioIcon': {
			...radioIconRules,
			...( initialRules?.[ '.RadioIcon' ] || {} ),
		},
		'.RadioIconInner--checked': {
			...radioIconInnerCheckedRules,
			...( initialRules?.[ '.RadioIconInner--checked' ] || {} ),
		},
	};
}
