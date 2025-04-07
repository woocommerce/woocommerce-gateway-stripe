import { NON_REUSABLE_METHODS } from 'wcstripe/stripe-utils/constants';

export const handleDisplayOfSavingCheckbox = ( method ) => {
	const saveCardInfoContainer = document.querySelector(
		'.woocommerce-SavedPaymentMethods-saveNew'
	);
	if ( ! saveCardInfoContainer ) {
		return;
	}

	const createAccountCheckbox = document.getElementById( 'createaccount' );
	if (
		( ! createAccountCheckbox || createAccountCheckbox?.checked ) &&
		! NON_REUSABLE_METHODS.includes( method )
	) {
		saveCardInfoContainer.style.display = 'block';
	} else {
		saveCardInfoContainer.style.display = 'none';
	}
};
