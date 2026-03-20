import {
	NON_REUSABLE_METHODS,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';
import { getStripeServerData, isLinkEnabled } from 'wcstripe/stripe-utils';

/**
 * Determines whether the store-level save checkbox should be hidden.
 *
 * When Link is enabled and the selected method is card, the store-level
 * checkbox is hidden because Link handles save consent via the Payment Element.
 *
 * @param {string} method The selected payment method type.
 * @return {boolean} True if the checkbox should be hidden.
 */
const shouldHideSaveCheckbox = ( method ) => {
	if ( NON_REUSABLE_METHODS.includes( method ) ) {
		return true;
	}

	if ( method === PAYMENT_METHOD_CARD && isLinkEnabled() ) {
		return true;
	}

	return false;
};

export const handleDisplayOfSavingCheckbox = ( method ) => {
	// For block checkout
	const saveCardInfoContainerBlocks = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info'
	);
	if ( saveCardInfoContainerBlocks ) {
		saveCardInfoContainerBlocks.style.display = shouldHideSaveCheckbox(
			method
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
