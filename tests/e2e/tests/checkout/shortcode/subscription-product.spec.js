import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, api, payments, products } from '../../../utils';

const {
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	clickAddToCartButton,
	getCartTotal,
} = payments;

let productId;

test.beforeAll( async () => {
	productId = await api.create.product( products.subscriptionData() );
} );

test.afterAll( async () => {
	await api.deletePost.product( productId );
} );

test( 'customer can purchase a subscription product @smoke @subscriptions', async ( {
	page,
	browser,
} ) => {
	await page.goto( `?p=${ productId }` );
	await clickAddToCartButton( page );

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

	const expectedTotal = await getCartTotal( page );

	await page.locator( 'text=Place order' ).click();
	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);

	// As the admin, confirm the order was charged the expected amount.
	const orderId = admin.getOrderIdFromOrderReceivedUrl( page.url() );
	await admin.verifyOrderChargedAmount( browser, orderId, expectedTotal );
} );
