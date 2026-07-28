import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const { emptyCart, setupCart, setupBlocksCheckout, fillCreditCardDetails } =
	payments;

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

test( 'customer can checkout with a saved card @smoke @blocks', async ( {
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
			await setupBlocksCheckout( page );
			await fillCreditCardDetails( page, config.get( 'cards.basic' ) );

			// check box to save payment method.
			await page
				.locator(
					'.wc-block-components-payment-methods__save-card-info'
				)
				.click();

			await page.locator( 'text=Place order' ).click();

			await page.waitForNavigation();
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );

		await test.step( 'checkout and pay with the saved card', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupBlocksCheckout( page, null, true );

			// check that there are saved payment methods.
			await expect(
				page.locator(
					'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
				)
			).toHaveCount( 1 );

			await page
				.locator(
					'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
				)
				.click();

			await page.locator( 'text=Place order' ).click();

			await page.waitForNavigation();
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );
	} finally {
		// Re-enable Link after the test.
		await admin.togglePaymentMethod( browser, 'Link by Stripe', true );
	}
} );
