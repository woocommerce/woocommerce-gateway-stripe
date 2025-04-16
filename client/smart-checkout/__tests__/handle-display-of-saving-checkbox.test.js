import { handleDisplayOfSavingCheckbox } from 'wcstripe/smart-checkout/handle-display-of-saving-checkbox';
import {
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';

describe( 'handleDisplayOfSavingCheckbox', () => {
	describe( 'Block checkout', () => {
		it( 'Correctly toggle the display of the saving payment method checkbox', () => {
			document.body.innerHTML = `
			<div class="wc-block-components-payment-methods__save-card-info"></div>
		`;

			const saveCardInfoContainer = document.querySelector(
				'.wc-block-components-payment-methods__save-card-info'
			);

			expect( saveCardInfoContainer.style.display ).toBe( '' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
			expect( saveCardInfoContainer.style.display ).toBe( 'block' );

			handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
			expect( saveCardInfoContainer.style.display ).toBe( 'none' );
		} );
	} );

	describe( 'Classic checkout', () => {
		describe( 'Without create account checkbox', () => {
			it( 'Correctly toggle the display of the saving payment method checkbox', () => {
				document.body.innerHTML = `
				<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
			`;

				const saveCardInfoContainer = document.querySelector(
					'.woocommerce-SavedPaymentMethods-saveNew'
				);

				expect( saveCardInfoContainer.style.display ).toBe( '' );

				handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
				expect( saveCardInfoContainer.style.display ).toBe( 'block' );

				handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
				expect( saveCardInfoContainer.style.display ).toBe( 'none' );
			} );
		} );

		describe( 'With create account checkbox', () => {
			it( 'Correctly toggle the display of the saving payment method checkbox (account checkbox checked)', () => {
				document.body.innerHTML = `
					<input type="checkbox" id="createaccount" checked />
					<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
				`;

				const saveCardInfoContainer = document.querySelector(
					'.woocommerce-SavedPaymentMethods-saveNew'
				);

				expect( saveCardInfoContainer.style.display ).toBe( '' );

				handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
				expect( saveCardInfoContainer.style.display ).toBe( 'block' );

				handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
				expect( saveCardInfoContainer.style.display ).toBe( 'none' );
			} );

			it( 'Correctly toggle the display of the saving payment method checkbox (create account checkbox unchecked)', () => {
				document.body.innerHTML = `
					<input type="checkbox" id="createaccount" />
					<div class="woocommerce-SavedPaymentMethods-saveNew"></div>
				`;

				const saveCardInfoContainer = document.querySelector(
					'.woocommerce-SavedPaymentMethods-saveNew'
				);

				expect( saveCardInfoContainer.style.display ).toBe( '' );

				handleDisplayOfSavingCheckbox( PAYMENT_METHOD_CARD );
				expect( saveCardInfoContainer.style.display ).toBe( 'none' );

				handleDisplayOfSavingCheckbox( PAYMENT_METHOD_ALIPAY );
				expect( saveCardInfoContainer.style.display ).toBe( 'none' );
			} );
		} );
	} );
} );
