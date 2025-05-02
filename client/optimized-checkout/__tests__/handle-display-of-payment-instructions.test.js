import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';

describe( 'handleDisplayOfPaymentInstructions', () => {
	document.body.innerHTML = `
		<div class="wc-stripe-payment-method-instruction" id="wc-stripe-payment-method-instructions-card"></div>
		<div class="wc-stripe-payment-method-instruction" id="wc-stripe-payment-method-instructions-sepa_debit"></div>
	`;

	const cardInstructions = document.getElementById(
		'wc-stripe-payment-method-instructions-card'
	);
	const sepaDebitInstructions = document.getElementById(
		'wc-stripe-payment-method-instructions-sepa_debit'
	);

	it( 'Correctly handles the display of payment method instructions (Card)', () => {
		handleDisplayOfPaymentInstructions( 'card' );

		expect( cardInstructions.style.display ).toBe( 'block' );
		expect( sepaDebitInstructions.style.display ).toBe( 'none' );
	} );
	it( 'Correctly handles the display of payment method instructions (SEPA Debit)', () => {
		handleDisplayOfPaymentInstructions( 'sepa_debit' );

		expect( sepaDebitInstructions.style.display ).toBe( 'block' );
		expect( cardInstructions.style.display ).toBe( 'none' );
	} );
} );
