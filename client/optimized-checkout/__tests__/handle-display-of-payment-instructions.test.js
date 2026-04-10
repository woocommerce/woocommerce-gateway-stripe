import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';

describe( 'handleDisplayOfPaymentInstructions', () => {
	describe( 'classic checkout', () => {
		let cardInstructions, sepaDebitInstructions;

		beforeEach( () => {
			document.body.innerHTML = `
				<div class="woocommerce-checkout">
					<input type="radio" name="payment_method" id="payment_method_stripe" checked />
					<input type="radio" name="payment_method" id="payment_method_other" />
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

		describe( 'classic - when Stripe is the selected payment method', () => {
			it( 'classic - shows card instructions and hides others', () => {
				handleDisplayOfPaymentInstructions( 'card', 'classic' );

				expect( cardInstructions.style.display ).toBe( 'block' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );

			it( 'classic -shows SEPA Debit instructions and hides others', () => {
				handleDisplayOfPaymentInstructions( 'sepa_debit', 'classic' );

				expect( sepaDebitInstructions.style.display ).toBe( 'block' );
				expect( cardInstructions.style.display ).toBe( 'none' );
			} );

			it( 'classic - hides all instructions when there is no matching instruction div for the method', () => {
				expect( () =>
					handleDisplayOfPaymentInstructions( 'klarna', 'classic' )
				).not.toThrow();

				expect( cardInstructions.style.display ).toBe( 'none' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );
		} );

		describe( 'classic - when a non-Stripe payment method is selected', () => {
			beforeEach( () => {
				document.getElementById(
					'payment_method_stripe'
				).checked = false;
				document.getElementById(
					'payment_method_other'
				).checked = true;
			} );

			it( 'classic - hides all instructions', () => {
				handleDisplayOfPaymentInstructions( 'card', 'classic' );

				expect( cardInstructions.style.display ).toBe( 'none' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );

			it( 'classic -hides an instruction div that was previously visible', () => {
				cardInstructions.style.display = 'block';

				handleDisplayOfPaymentInstructions( 'card', 'classic' );

				expect( cardInstructions.style.display ).toBe( 'none' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );
		} );
	} );

	describe( 'blocks checkout', () => {
		let cardInstructions, sepaDebitInstructions;

		beforeEach( () => {
			document.body.innerHTML = `
				<div class="wc-block-checkout__payment-method">
					<input type="radio" name="radio-control-wc-payment-method-options" id="radio-control-wc-payment-method-options-stripe" value="stripe" checked />
					<input type="radio" name="radio-control-wc-payment-method-options" id="radio-control-wc-payment-method-options-other" value="other" />
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

		describe( 'blocks - when Stripe is the selected payment method', () => {
			it( 'blocks - shows card instructions and hides others', () => {
				handleDisplayOfPaymentInstructions( 'card', 'blocks' );

				expect( cardInstructions.style.display ).toBe( 'block' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );

			it( 'blocks - shows SEPA Debit instructions and hides others', () => {
				handleDisplayOfPaymentInstructions( 'sepa_debit', 'blocks' );

				expect( sepaDebitInstructions.style.display ).toBe( 'block' );
				expect( cardInstructions.style.display ).toBe( 'none' );
			} );

			it( 'blocks - hides all instructions when there is no matching instruction div for the method', () => {
				expect( () =>
					handleDisplayOfPaymentInstructions( 'klarna', 'blocks' )
				).not.toThrow();

				expect( cardInstructions.style.display ).toBe( 'none' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );
		} );

		describe( 'blocks - when a non-Stripe payment method is selected', () => {
			beforeEach( () => {
				document.getElementById(
					'radio-control-wc-payment-method-options-stripe'
				).checked = false;
				document.getElementById(
					'radio-control-wc-payment-method-options-other'
				).checked = true;
			} );

			it( 'blocks - hides all instructions', () => {
				handleDisplayOfPaymentInstructions( 'card', 'blocks' );

				expect( cardInstructions.style.display ).toBe( 'none' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );

			it( 'blocks - hides an instruction div that was previously visible', () => {
				cardInstructions.style.display = 'block';

				handleDisplayOfPaymentInstructions( 'card', 'blocks' );

				expect( cardInstructions.style.display ).toBe( 'none' );
				expect( sepaDebitInstructions.style.display ).toBe( 'none' );
			} );
		} );
	} );
} );
