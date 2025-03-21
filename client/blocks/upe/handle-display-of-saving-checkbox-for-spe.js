import { NON_RECURRING_METHODS } from 'wcstripe/stripe-utils/constants';

export const handleDisplayOfSavingCheckboxForSpe = ( method ) => {
	const saveCardInfoContainer = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info'
	);
	if ( saveCardInfoContainer.length > 0 ) {
		saveCardInfoContainer.style.display = NON_RECURRING_METHODS.includes(
			method
		)
			? 'none'
			: 'block';
	}
};
