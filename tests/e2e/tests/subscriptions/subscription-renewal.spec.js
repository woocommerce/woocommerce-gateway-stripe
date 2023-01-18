import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api } from '../../utils';

const { setupCheckout, fillCardDetails } = payments;

let productId;
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

	const product = {
		...config.get( 'products.subscription' ),
		regular_price: '9.99',
		meta_data: [
			{
				key: '_subscription_period',
				value: 'month',
			},
			{
				key: '_subscription_period_interval',
				value: '1',
			},
		],
	};

	productId = await api.create.product( product );
} );

test.afterAll( async () => {
	await api.deletePost.product( productId );
} );

test( 'customer can renew a subscription @smoke @subscriptions', async ( {
	page,
} ) => {
	await test.step( 'customer login', async () => {
		await page.goto( `/wp-admin` );
		await page.fill( 'input[name="log"]', username );
		await page.fill(
			'input[name="pwd"]',
			config.get( 'users.customer.password' )
		);
		await page.click( 'text=Log In' );

		await expect( page.locator( 'body' ) ).toHaveClass( /logged-in/ );
	} );

	await test.step( 'customer purchase a subscription product', async () => {
		await page.goto( `?p=${ productId }` );
		await page.locator( 'button[name="add-to-cart"]' ).click();

		await setupCheckout( page );
		await fillCardDetails( page, config.get( 'cards.basic' ) );

		await page.locator( 'text=Sign up now' ).click();

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

		await page.locator( 'text=Renew now' ).click();
		await page.locator( 'text=Renew subscription' ).click();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	await test.step(
		'check for new entry in the related orders table',
		async () => {
			await page.goto( `/my-account` );
			await page.click( 'text=My Subscription' );

			// Expect only one related order.
			await expect(
				page.locator( '.woocommerce-orders-table--orders tbody tr' )
			).toHaveCount( 2 );
		}
	);
} );
