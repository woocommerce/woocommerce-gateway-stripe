import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, payments, products, user } from '../../utils';

const {
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	clickAddToCartButton,
} = payments;

let productId;
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

	productId = await api.create.product( products.subscriptionData() );
} );

test.afterAll( async () => {
	await api.deletePost.product( productId );
} );

test( 'customer can renew a subscription @smoke @subscriptions', async ( {
	page,
} ) => {
	await test.step( 'customer login', async () => {
		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);
	} );

	await test.step( 'customer purchase a subscription product', async () => {
		await page.goto( `?p=${ productId }` );
		await clickAddToCartButton( page );

		await setupShortcodeCheckout( page );
		await fillCreditCardDetailsShortcode(
			page,
			config.get( 'cards.basic' )
		);

		await page.locator( 'text=Place order' ).click();

		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	await test.step( 'customer renews the subscription', async () => {
		await page.goto( `/my-account` );
		await page.click( 'text=My Subscription' );

		// Expect only one related order.
		await expect(
			page.locator( '.woocommerce-orders-table--orders tbody tr' )
		).toHaveCount( 1 );

		await page.click( 'text=Renew now' );
		await page.waitForURL( '**/checkout/' );
		await page.click(
			'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
		);
		await page
			.locator( 'text=Renew subscription' )
			.dispatchEvent( 'click' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	await test.step( 'check for new entry in the related orders table', async () => {
		await page.goto( `/my-account` );
		await page.click( 'text=My Subscription' );

		// Expect only one related order.
		await expect(
			page.locator( '.woocommerce-orders-table--orders tbody tr' )
		).toHaveCount( 2 );
	} );
} );
