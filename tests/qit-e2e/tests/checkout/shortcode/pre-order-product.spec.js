import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, payments, products } from '../../../utils';
import { isPluginInstalled } from '../../../utils/plugin-utils';

const {
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	clickAddToCartButton,
} = payments;

let productId;

test.skip( async () => {
	return ! ( await isPluginInstalled( 'woocommerce-pre-orders' ) );
}, 'Woo Pre-Orders plugin is not active. Skipping tests.' );

test.beforeAll( async () => {
	productId = await api.create.product( products.preOrderData() );
} );

test.afterAll( async () => {
	if ( ! productId ) {
		return;
	}

	await api.deletePost.product( productId );
} );

test( 'customer can purchase a pre-order product @pre-orders', async ( {
	page,
} ) => {
	await page.goto( `?p=${ productId }` );
	await clickAddToCartButton( page, 'Pre-order now' );

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

	const placePreOrderButton = page.getByRole( 'button', {
		name: 'Place pre-order now',
	} );
	await expect( placePreOrderButton ).toBeEnabled();
	await placePreOrderButton.dispatchEvent( 'click' );
	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
