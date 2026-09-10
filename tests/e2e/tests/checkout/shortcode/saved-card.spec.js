import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

let username, userEmail;

test.beforeAll( async () => {
	// This allow multiple tests to run in parallel.
	const randomString = randomUUID();
	userEmail = randomString + '+' + config.get( 'users.customer.email' );
	username = randomString + '.' + config.get( 'users.customer.username' );

	const user = {
		...config.get( 'users.customer' ),
		...config.get( 'addresses.customer' ),
		email: userEmail,
		username,
	};

	await api.create.customer( user );
} );

test( 'customer can checkout with a saved card @smoke', async ( {
	page,
	browser,
} ) => {
	// Disable Link so the store-level save checkbox is visible.
	// When Link is enabled, the store checkbox is hidden and Link handles save consent.
	await admin.togglePaymentMethod( browser, 'Link by Stripe', false );

	try {
		await test.step( 'customer login', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
		} );

		await test.step( 'checkout and choose to save the card', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupShortcodeCheckout( page );
			await fillCreditCardDetailsShortcode(
				page,
				config.get( 'cards.basic' )
			);

			// The fieldset wrapping the save checkbox must be visible while the
			// checkbox row is shown (Link is disabled in this test).
			await expect(
				page.locator(
					'.payment_box.payment_method_stripe fieldset:has(.woocommerce-SavedPaymentMethods-saveNew)'
				)
			).toBeVisible();

			// check box to save payment method.
			await page.locator( '#wc-stripe-new-payment-method' ).click();

			const expectedTotal = await getCartTotal( page );

			await page.locator( 'text=Place order' ).dispatchEvent( 'click' );

			await waitForOrderReceivedPageAndConfirmExpectedTotal(
				browser,
				page,
				expectedTotal
			);
		} );

		await test.step( 'checkout and pay with the saved card', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupShortcodeCheckout( page, null, true );

			// check that there are saved payment methods.
			await expect(
				page.locator(
					'.woocommerce-SavedPaymentMethods-token input[id^="wc-stripe-payment-token-"]'
				)
			).toHaveCount( 1 );

			// With the saved card selected the save-checkbox row is hidden, and the
			// fieldset wrapping it must be hidden too — a bare fieldset renders as
			// an empty box on themes that keep the browser's default fieldset border.
			const hiddenWrapper = page.locator(
				'.payment_box.payment_method_stripe fieldset:has(.woocommerce-SavedPaymentMethods-saveNew)'
			);
			await expect( hiddenWrapper ).toBeAttached();
			await expect( hiddenWrapper ).toBeHidden();

			const expectedTotal = await getCartTotal( page );

			await page.locator( 'text=Place order' ).dispatchEvent( 'click' );

			await waitForOrderReceivedPageAndConfirmExpectedTotal(
				browser,
				page,
				expectedTotal
			);
		} );
	} finally {
		// Re-enable Link after the test.
		await admin.togglePaymentMethod( browser, 'Link by Stripe', true );
	}
} );
