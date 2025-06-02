import { NON_REUSABLE_METHODS } from 'wcstripe/stripe-utils/constants';
import { getStripeServerData } from 'wcstripe/stripe-utils';

export const handleDisplayOfSavingCheckbox = ( method ) => {
	// For block checkout
	const saveCardInfoContainerBlocks = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info'
	);
	if ( saveCardInfoContainerBlocks ) {
		saveCardInfoContainerBlocks.style.display = NON_REUSABLE_METHODS.includes(
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
		const createAccountCheckbox = document.getElementById(
			'createaccount'
		);
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
			! NON_REUSABLE_METHODS.includes( method )
		) {
			saveCardInfoContainerClassic.style.display = 'block';
		} else {
			saveCardInfoContainerClassic.style.display = 'none';
		}
	}
};
