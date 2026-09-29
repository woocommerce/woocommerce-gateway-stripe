import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetails,
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

			const expectedTotal = await getCartTotal( page );

			await page.locator( 'text=Place order' ).click();

			await waitForOrderReceivedPageAndConfirmExpectedTotal(
				browser,
				page,
				expectedTotal
			);
		} );

		await test.step( 'save a second card and make it the default', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupBlocksCheckout( page );
			await fillCreditCardDetails( page, config.get( 'cards.basic2' ) );
			await page
				.locator(
					'.wc-block-components-payment-methods__save-card-info'
				)
				.click();

			const expectedTotal = await getCartTotal( page );

			await page.locator( 'text=Place order' ).click();

			await waitForOrderReceivedPageAndConfirmExpectedTotal(
				browser,
				page,
				expectedTotal
			);

			await page.goto( '/my-account/payment-methods/' );
			const secondCardRow = page
				.locator( 'tr.payment-method' )
				.filter( { hasText: 'Visa ending in 1111' } );
			await secondCardRow
				.getByRole( 'link', { name: 'Make default' } )
				.click();
			await expect( secondCardRow ).toHaveClass(
				/default-payment-method/
			);
		} );

		await test.step( 'checkout with the default saved card', async () => {
			await emptyCart( page );
			await setupCart( page );
			await page.goto( '/checkout/' );

			await expect(
				page.locator(
					'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
				)
			).toHaveCount( 2 );

			await expect(
				page.getByLabel( /Visa ending in 1111/ )
			).toBeChecked();

			const expectedTotal = await getCartTotal( page );

			await page.locator( 'text=Place order' ).click();

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
