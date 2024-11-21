import { test, expect } from '@playwright/test';
import { payments, api, config } from '../../../utils';
import qit from '/qitHelpers';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetailsLegacy,
} = payments;

let username, userEmail;

test.beforeAll( async () => {
	// This allow multiple tests to run in parallel.
	const randomString = Date.now();
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
test.slow();
test( 'customer can checkout with a saved card @smoke @blocks @legacy', async ( {
	page,
} ) => {
	await test.step( 'customer login', async () => {
		await qit.loginAs(
			page,
			username,
			config.get( 'users.customer.password' )
		);
	} );

	await test.step( 'checkout and choose to save the card', async () => {
		await emptyCart( page );
		await setupCart( page );
		await setupBlocksCheckout( page );
		await fillCreditCardDetailsLegacy( page, config.get( 'cards.basic' ) );

		// check box to save payment method.
		await page
			.locator( '.wc-block-components-payment-methods__save-card-info' )
			.click();

		await page.locator( 'text=Place order' ).click();

		await page.waitForURL( '**/checkout/order-received/**', {
			timeout: 20000,
		} ); // Allow some extra time for the redirect to complete.
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

		await page.waitForURL( '**/checkout/order-received/**', {
			timeout: 20000,
		} ); // Allow some extra time for the redirect to complete.
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
