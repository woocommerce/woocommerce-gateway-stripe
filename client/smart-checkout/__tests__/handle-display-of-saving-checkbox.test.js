import { handleDisplayOfSavingCheckbox } from 'wcstripe/smart-checkout/handle-display-of-saving-checkbox';
import {
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';

describe( 'handleDisplayOfSavingCheckbox', () => {
	it( 'Correctly toggle the display of the saving payment method checkbox', () => {
		document.body.innerHTML = `
			<div class="save-card-info-container"></div>
		`;

		const containerClass = 'save-card-info-container';
		const saveCardInfoContainer = document.querySelector(
			'.' + containerClass
		);

		expect( saveCardInfoContainer.style.display ).toBe( '' );

		handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD, containerClass );
		expect( saveCardInfoContainer.style.display ).toBe( 'block' );

		handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY, containerClass );
		expect( saveCardInfoContainer.style.display ).toBe( 'none' );
	} );
} );
