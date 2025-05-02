import { NON_REUSABLE_METHODS } from 'wcstripe/stripe-utils/constants';

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
		if (
			( ! createAccountCheckbox || createAccountCheckbox?.checked ) &&
			! NON_REUSABLE_METHODS.includes( method )
		) {
			saveCardInfoContainerClassic.style.display = 'block';
		} else {
			saveCardInfoContainerClassic.style.display = 'none';
		}
	}
};
