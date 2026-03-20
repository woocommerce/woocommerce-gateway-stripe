import {
	NON_REUSABLE_METHODS,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';
import { getStripeServerData, isLinkEnabled } from 'wcstripe/stripe-utils';

/**
 * Checks if Link is among the enabled payment methods.
 *
 * On OC, paymentMethodsConfig uses 'card' as key for the OC container and
 * doesn't have a 'link' key. But enabledPaymentMethods inside the config
 * lists the original method IDs. On non-OC Blocks, both 'card' and 'link'
 * keys exist directly in paymentMethodsConfig.
 *
 * @param {Object} [paymentMethodsConfig] The payment methods configuration.
 * @return {boolean} True if Link is enabled.
 */
const checkLinkEnabled = ( paymentMethodsConfig ) => {
	if ( ! paymentMethodsConfig ) {
		try {
			return isLinkEnabled();
		} catch ( e ) {
			return false;
		}
	}

	// Direct check (non-OC Blocks where both 'card' and 'link' keys exist).
	if ( isLinkEnabled( paymentMethodsConfig ) ) {
		return true;
	}

	// OC check: look inside enabledPaymentMethods array.
	const ocConfig = paymentMethodsConfig?.card;
	if ( ocConfig?.enabledPaymentMethods ) {
		return ocConfig.enabledPaymentMethods.includes( 'link' );
	}

	return false;
};

/**
 * Determines whether the store-level save checkbox should be hidden.
 *
 * When Link is enabled and the selected method is card, the store-level
 * checkbox is hidden because Link handles save consent via the Payment Element.
 *
 * @param {string} method                 The selected payment method type.
 * @param {Object} [paymentMethodsConfig] Optional config for Link detection.
 * @return {boolean}                      True if the checkbox should be hidden.
 */
const shouldHideSaveCheckbox = ( method, paymentMethodsConfig ) => {
	if ( NON_REUSABLE_METHODS.includes( method ) ) {
		return true;
	}

	if (
		method === PAYMENT_METHOD_CARD &&
		checkLinkEnabled( paymentMethodsConfig )
	) {
		return true;
	}

	return false;
};

export const handleDisplayOfSavingCheckbox = (
	method,
	paymentMethodsConfig
) => {
	// For block checkout
	const saveCardInfoContainerBlocks = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info'
	);
	if ( saveCardInfoContainerBlocks ) {
		saveCardInfoContainerBlocks.style.display = shouldHideSaveCheckbox(
			method,
			paymentMethodsConfig
		)
			? 'none'
			: 'block';
		return;
	}

	// For classic checkout
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
