import { randomUUID } from 'crypto';
import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	setupOptimizedCheckout,
	selectOCCardMethod,
	fillOCDetails,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

// Runs against an account with Link off: with Link on, Link handles save
// consent and the store-level save checkbox these tests depend on is hidden.
test.describe( 'Optimized Checkout payment tests with Link off @shortcode', () => {
	let username;

	test.beforeAll( async () => {
		const randomString = randomUUID();
		username = randomString + '.' + config.get( 'users.customer.username' );
		await api.create.customer( {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username,
		} );
	} );

	test( 'customer can save and reuse Optimized Checkout payment method @smoke', async ( {
		page,
		browser,
	} ) => {
		// First order - Save the payment method.
		await test.step( 'Save payment method during first checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupOptimizedCheckout( page, 'shortcode' );
			// The save checkbox is card-only and the element's initial selection
			// follows Stripe's ranking, so pick Card before looking for it.
			await selectOCCardMethod( page, 'shortcode' );
			// Toggle via label to avoid flaky direct checkbox interactions.
			const savePaymentMethodCheckbox = page.locator(
				'#wc-stripe-new-payment-method'
			);
			await expect( savePaymentMethodCheckbox ).toBeAttached();
			// The fieldset wrapping the save checkbox must be visible while the
			// checkbox row is shown.
			await expect(
				page.locator(
					'.payment_box.payment_method_stripe fieldset:has(.woocommerce-SavedPaymentMethods-saveNew)'
				)
			).toBeVisible();
			await page
				.locator( "label[for='wc-stripe-new-payment-method']" )
				.click();
			await expect( savePaymentMethodCheckbox ).toBeChecked();
			// Then fill in the payment details.
			// This order is needed because, if we fill in the payment details and then click the checkbox, payment element will refresh and the fields will be cleared.
			await fillOCDetails(
				page,
				config.get( 'cards.basic' ),
				'shortcode'
			);

			const expectedTotal = await getCartTotal( page );

			await clickPlaceOrder( page );

			await waitForOrderReceivedPageAndConfirmExpectedTotal(
				browser,
				page,
				expectedTotal
			);
		} );

		// Second order - Use saved payment method.
		await test.step( 'Use saved payment method for second checkout', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupShortcodeCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
			const savedTokenRadio = page.locator(
				'.woocommerce-SavedPaymentMethods-token input[id^="wc-stripe-payment-token-"]'
			);
			await expect( savedTokenRadio ).toBeVisible();
			await savedTokenRadio.click();

			// With the saved method selected the save-checkbox row is hidden, and
			// the fieldset wrapping it must be hidden too — a bare fieldset renders
			// as an empty box on themes that keep the default fieldset border.
			const hiddenWrapper = page.locator(
				'.payment_box.payment_method_stripe fieldset:has(.woocommerce-SavedPaymentMethods-saveNew)'
			);
			await expect( hiddenWrapper ).toBeAttached();
			await expect( hiddenWrapper ).toBeHidden();

			const expectedTotal = await getCartTotal( page );

			await clickPlaceOrder( page );

			await waitForOrderReceivedPageAndConfirmExpectedTotal(
				browser,
				page,
				expectedTotal
			);
		} );
	} );
} );
