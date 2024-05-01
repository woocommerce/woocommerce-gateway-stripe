import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api } from '../../../utils';

const { setupBlocksCheckout, fillCreditCardDetailsLegacy } = payments;

let productId;

test.beforeAll( async () => {
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
	await fillCreditCardDetailsLegacy( page, config.get( 'cards.basic' ) );

	await page.locator( 'text=Sign up now' ).click();
	await page.waitForNavigation();

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
