import { NON_RECURRING_METHODS } from 'wcstripe/stripe-utils/constants';

export const handleDisplayOfSavingCheckbox = ( method ) => {
	const saveCardInfoContainer = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info'
	);
	if ( saveCardInfoContainer ) {
		saveCardInfoContainer.style.display = NON_RECURRING_METHODS.includes(
			method
		)
			? 'none'
			: 'block';
	}
};
