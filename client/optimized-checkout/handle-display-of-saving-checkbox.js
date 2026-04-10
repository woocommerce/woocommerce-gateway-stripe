import { NON_REUSABLE_METHODS } from 'wcstripe/stripe-utils/constants';
import { getStripeServerData, isLinkEnabled } from 'wcstripe/stripe-utils';

/**
 * Determines whether the store-level save checkbox should be hidden.
 *
 * For classic checkout (non-OC), this checks Link status client-side.
 * For block/OC checkout, prefer shouldHideSaveCheckboxFromConfig() which
 * reads the server-provided showSaveOptionByMethod map.
 *
 * @param {string} method The selected payment method type.
 * @return {boolean}      True if the checkbox should be hidden.
 */
const shouldHideSaveCheckbox = ( method ) => {
	if ( NON_REUSABLE_METHODS.includes( method ) ) {
		return true;
	}

	// Hide for both 'card' and 'link' when Link is enabled — the PE may
	// fire a change event with type 'link' when Link fields appear.
	if ( method === 'card' || method === 'link' ) {
		try {
			return isLinkEnabled();
		} catch ( e ) {
			return false;
		}
	}

	return false;
};

/**
 * Determines whether the save checkbox should be hidden using the
 * server-provided per-method map (OC / block checkout).
 *
 * showSaveOptionByMethod is populated in PHP by calling
 * should_upe_payment_method_show_save_option() for each original method
 * inside the OC container. This keeps the business rule in PHP (single
 * source of truth) and the frontend as a dumb lookup.
 *
 * @param {string} method               The selected payment method type.
 * @param {Object} paymentMethodsConfig The payment methods configuration.
 * @return {boolean}                    True if the checkbox should be hidden.
 */
const shouldHideSaveCheckboxFromConfig = ( method, paymentMethodsConfig ) => {
	if ( NON_REUSABLE_METHODS.includes( method ) ) {
		return true;
	}

	const byMethod = paymentMethodsConfig?.card?.showSaveOptionByMethod;
	if ( byMethod && method in byMethod ) {
		return ! byMethod[ method ];
	}

	// Fallback: method not in the map (e.g. newly added), show checkbox.
	return false;
};

/**
 * CSS class added to document.body to hide the blocks save checkbox.
 *
 * A body class + stylesheet rule is used instead of inline style.display
 * because WooCommerce Blocks can unmount/remount the checkbox element
 * (e.g. when a signed-in user toggles between saved tokens and a new
 * payment method), which would lose any inline style set directly on
 * the DOM node. A CSS rule targets by selector, so it applies regardless
 * of React re-renders. The matching rule lives in blocks/upe/styles.scss.
 */
const HIDE_SAVE_CHECKBOX_CLASS = 'wc-stripe-hide-save-checkbox';

export const handleDisplayOfSavingCheckbox = (
	method,
	paymentMethodsConfig
) => {
	// For block checkout — toggle a body class so the stylesheet rule in
	// blocks/upe/styles.scss hides the checkbox. Uses the PHP-provided
	// per-method map as the single source of truth.
	const isBlockCheckout = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info, .wc-block-checkout'
	);
	if ( isBlockCheckout ) {
		document.body.classList.toggle(
			HIDE_SAVE_CHECKBOX_CLASS,
			shouldHideSaveCheckboxFromConfig( method, paymentMethodsConfig )
		);
		return;
	}

	// For classic checkout with OC — use body class toggle (same as blocks)
	// when the per-method config map is available. The matching CSS rule
	// lives in classic/upe/style.scss.
	if ( paymentMethodsConfig?.card?.showSaveOptionByMethod ) {
		document.body.classList.toggle(
			HIDE_SAVE_CHECKBOX_CLASS,
			shouldHideSaveCheckboxFromConfig( method, paymentMethodsConfig )
		);
		return;
	}

	// For classic checkout without OC — inline style toggle with
	// client-side Link detection as fallback.
	const saveCardInfoContainerClassic = document.querySelector(
		'.woocommerce-SavedPaymentMethods-saveNew'
	);
	if ( saveCardInfoContainerClassic ) {
		const createAccountCheckbox =
			document.getElementById( 'createaccount' );
		const signupSelected =
			getStripeServerData()?.isSignupOnCheckoutAllowed &&
			createAccountCheckbox?.checked;
		const hasSavedPaymentMethodSelected =
			document.querySelector( 'input[name=wc-stripe-payment-token]' ) &&
			document.getElementById( 'wc-stripe-upe-form' )?.style.display ===
				'none';
		if (
			( getStripeServerData()?.isLoggedIn || signupSelected ) &&
			! hasSavedPaymentMethodSelected &&
			! shouldHideSaveCheckbox( method )
		) {
			saveCardInfoContainerClassic.style.display = 'block';
		} else {
			saveCardInfoContainerClassic.style.display = 'none';
		}
	}
};
