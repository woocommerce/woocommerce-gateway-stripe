import { test, expect } from '@playwright/test';
import config from 'config';
import { api, payments, products } from '../../../utils';

const { setupBlocksCheckout, fillCreditCardDetails } = payments;

let productId;

test.beforeAll( async () => {
	productId = await api.create.product( products.subscriptionData() );
} );

test.afterAll( async () => {
	await api.deletePost.product( productId );
} );

test( 'customer can purchase a subscription product @smoke @blocks @subscriptions', async ( {
	page,
} ) => {
	await page.goto( `?p=${ productId }` );
	await page.locator( 'button[name="add-to-cart"]' ).click();

	// Subscriptions will create an account for this checkout, we need a random email.
	const customerData = {
		...config.get( 'addresses.customer.billing' ),
		email:
			Date.now() + '+' + config.get( 'addresses.customer.billing.email' ),
	};

	await setupBlocksCheckout( page, customerData );
	await fillCreditCardDetails( page, config.get( 'cards.no-3ds' ) );

	await page.locator( 'text=Sign up now' ).click();
	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
