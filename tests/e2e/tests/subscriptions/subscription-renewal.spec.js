import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../utils';

const { setupShortcodeCheckout, fillCreditCardDetailsShortcode } = payments;

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
		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);
	} );

	await test.step( 'customer purchase a subscription product', async () => {
		await page.goto( `?p=${ productId }` );
		await page.locator( 'button[name="add-to-cart"]' ).click();

		await setupShortcodeCheckout( page );
		await fillCreditCardDetailsShortcode(
			page,
			config.get( 'cards.basic' )
		);

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

		await page.click( 'text=Renew now' );
		await page.waitForURL( '**/checkout/' );
		await page.click(
			'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
		);
		await page.click( 'text=Renew subscription' );
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
