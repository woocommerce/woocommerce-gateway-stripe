import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';

describe( 'handleDisplayOfPaymentInstructions', () => {
	let cardInstructions, sepaDebitInstructions;

	beforeEach( () => {
		document.body.innerHTML = `
			<div class="woocommerce-checkout">
				<input type="radio" name="payment_method" id="payment_method_stripe" checked />
				<input type="radio" name="payment_method" id="payment_method_paypal" />
			</div>
			<div class="wc-stripe-payment-method-instruction" id="wc-stripe-payment-method-instructions-card"></div>
			<div class="wc-stripe-payment-method-instruction" id="wc-stripe-payment-method-instructions-sepa_debit"></div>
		`;

		cardInstructions = document.getElementById(
			'wc-stripe-payment-method-instructions-card'
		);
		sepaDebitInstructions = document.getElementById(
			'wc-stripe-payment-method-instructions-sepa_debit'
		);
	} );

	describe( 'when Stripe is the selected payment method', () => {
		it( 'shows card instructions and hides others', () => {
			handleDisplayOfPaymentInstructions( 'card' );

			expect( cardInstructions.style.display ).toBe( 'block' );
			expect( sepaDebitInstructions.style.display ).toBe( 'none' );
		} );

		it( 'shows SEPA Debit instructions and hides others', () => {
			handleDisplayOfPaymentInstructions( 'sepa_debit' );

			expect( sepaDebitInstructions.style.display ).toBe( 'block' );
			expect( cardInstructions.style.display ).toBe( 'none' );
		} );

		it( 'hides all instructions when there is no matching instruction div for the method', () => {
			expect( () =>
				handleDisplayOfPaymentInstructions( 'klarna' )
			).not.toThrow();

			expect( cardInstructions.style.display ).toBe( 'none' );
			expect( sepaDebitInstructions.style.display ).toBe( 'none' );
		} );
	} );

	describe( 'when a non-Stripe payment method is selected', () => {
		beforeEach( () => {
			document.getElementById( 'payment_method_stripe' ).checked = false;
			document.getElementById( 'payment_method_paypal' ).checked = true;
		} );

		it( 'hides all instructions', () => {
			handleDisplayOfPaymentInstructions( 'card' );

			expect( cardInstructions.style.display ).toBe( 'none' );
			expect( sepaDebitInstructions.style.display ).toBe( 'none' );
		} );

		it( 'hides an instruction div that was previously visible', () => {
			cardInstructions.style.display = 'block';

			handleDisplayOfPaymentInstructions( 'card' );

			expect( cardInstructions.style.display ).toBe( 'none' );
			expect( sepaDebitInstructions.style.display ).toBe( 'none' );
		} );
	} );
} );
