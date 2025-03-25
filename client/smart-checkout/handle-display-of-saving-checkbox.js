import { NON_REUSABLE_METHODS } from 'wcstripe/stripe-utils/constants';

/**
 * Handles the display of the saving checkbox based on the payment method.
 *
 * @param {string} method The payment method.
 * @param {string} containerClass The class of the container.
 */
export const handleDisplayOfSavingCheckbox = ( method, containerClass ) => {
	const saveCardInfoContainer = document.querySelector(
		'.' + containerClass
	);
	if ( saveCardInfoContainer ) {
		saveCardInfoContainer.style.display = NON_REUSABLE_METHODS.includes(
			method
		)
			? 'none'
			: 'block';
	}
};
