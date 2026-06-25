import { test } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, payments, products } from '../../../utils';

const {
	setupBlocksCheckout,
	fillCreditCardDetails,
	clickAddToCartButton,
	selectSubscriptionOption,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

let productId;

test.beforeAll( async () => {
	productId = await api.create.product( products.subscriptionData() );
} );

test.afterAll( async () => {
	await api.deletePost.product( productId );
} );

test( 'customer can purchase a subscription product @smoke @blocks @subscriptions', async ( {
	page,
	browser,
} ) => {
	await page.goto( `?p=${ productId }` );
	await selectSubscriptionOption( page );
	await clickAddToCartButton( page, 'Sign up' );

	const randomString = randomUUID();
	// Subscriptions will create an account for this checkout, we need a random email.
	const customerData = {
		...config.get( 'addresses.customer.billing' ),
		email:
			randomString +
			'+' +
			config.get( 'addresses.customer.billing.email' ),
	};

	await setupBlocksCheckout( page, customerData );
	await fillCreditCardDetails( page, config.get( 'cards.no-3ds' ) );

	const expectedTotal = await getCartTotal( page );

	await page.locator( 'text=Place order' ).click();

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );
