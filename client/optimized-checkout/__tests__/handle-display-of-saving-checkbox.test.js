import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';
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
		const globalValues = global.wc_stripe_upe_params;

		beforeEach( () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: true,
				isSignupOnCheckoutAllowed: true,
			};
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		it( 'User is logged in', () => {
			global.wc_stripe_upe_params = { isLoggedIn: true };

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

		it( 'User is logged out, but the signup feature is enabled and the option selected', () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: true,
			};

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

		it( 'User is logged out, the signup feature is enabled, the option selected, but saved payment method selected', () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: true,
			};

			document.body.innerHTML = `
				<input type="checkbox" id="createaccount" checked />
				<input type="hidden" name="wc-stripe-payment-token" value="token_123" />
				<div id="wc-stripe-upe-form" style="display: none;"></div>
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

		it( 'User is logged out, and the signup option is not available', () => {
			global.wc_stripe_upe_params = {
				isLoggedIn: false,
				isSignupOnCheckoutAllowed: false,
			};

			document.body.innerHTML = `
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
