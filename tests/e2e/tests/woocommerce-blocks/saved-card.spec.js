import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	setupBlocksCheckout,
	fillCardDetails,
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

test( 'customer can checkout with a saved card @smoke @blocks', async ( {
	page,
} ) => {
	await test.step( 'customer login', async () => {
		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);
	} );

	await test.step( 'checkout and choose to save the card', async () => {
		await emptyCart( page );

		await setupProductCheckout( page );
		await setupBlocksCheckout( page );
		await fillCardDetails( page, config.get( 'cards.basic' ) );

		// check box to save payment method.
		await page
			.locator( '.wc-block-components-payment-methods__save-card-info' )
			.click();

		await page.locator( 'text=Place order' ).click();

		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	await test.step( 'checkout and pay with the saved card', async () => {
		await emptyCart( page );
		await setupProductCheckout( page );
		await setupBlocksCheckout( page, null, true );

		// check that there are saved payment methods.
		await expect(
			page.locator( 'input[id^="wc-stripe-payment-token-"]' )
		).toHaveCount( 1 );

		await page.locator( 'input[id^="wc-stripe-payment-token-"]' ).click();

		await page.locator( 'text=Place order' ).click();

		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
