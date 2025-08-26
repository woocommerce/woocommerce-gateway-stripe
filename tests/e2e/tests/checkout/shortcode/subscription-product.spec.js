import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, payments, products } from '../../../utils';

const { setupShortcodeCheckout, fillCreditCardDetailsShortcode } = payments;

let productId;

test.beforeAll( async () => {
	productId = await api.create.product( products.subscriptionData() );
} );

test.afterAll( async () => {
	await api.deletePost.product( productId );
} );

test( 'customer can purchase a subscription product @smoke @subscriptions', async ( {
	page,
} ) => {
	await page.goto( `?p=${ productId }` );
	await page.locator( 'button[name="add-to-cart"]' ).click();

	const randomString = randomUUID();
	// Subscriptions will create an account for this checkout, we need a random email.
	const customerData = {
		...config.get( 'addresses.customer.billing' ),
		email:
			randomString +
			'+' +
			config.get( 'addresses.customer.billing.email' ),
	};

	await setupShortcodeCheckout( page, customerData );
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.basic' ) );

	await page.locator( 'text=Add to cart' ).click();
	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
