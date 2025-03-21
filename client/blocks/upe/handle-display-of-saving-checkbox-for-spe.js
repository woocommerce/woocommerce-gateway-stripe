import { NON_RECURRING_METHODS } from 'wcstripe/stripe-utils/constants';

export const handleDisplayOfSavingCheckboxForSpe = ( method ) => {
	const saveCardInfoContainer = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info'
	);
	if ( NON_RECURRING_METHODS.includes( method ) ) {
		saveCardInfoContainer.style.display = 'none';
	}
};
