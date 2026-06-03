import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, payments, products, user } from '../../utils';

const {
	emptyCart,
	clickAddToCartButton,
	setupOptimizedCheckout,
	fillOCDetails,
	clickPlaceOrder,
} = payments;

let productId;

test.describe( 'Optimized Checkout subscription purchase tests @subscriptions', () => {
	test.beforeAll( async () => {
		productId = await api.create.product( products.subscriptionData() );
	} );

	test.afterAll( async () => {
		await api.deletePost.product( productId );
	} );

	/**
	 * Create a fresh customer and purchase the subscription product through
	 * the Optimized Checkout element.
	 *
	 * A unique customer per test is required: purchasing a subscription
	 * auto-saves a payment token, and a customer with a saved token sees the
	 * "Use a new payment method" selector instead of the expanded new-card
	 * OCS element, which would hide the card iframe. Logging in (rather than
	 * guest checkout) is needed because setupOptimizedCheckout uses a fixed
	 * billing email that would collide across the two tests.
	 *
	 * @param {import('@playwright/test').Page} page         Playwright page fixture.
	 * @param {string}                          checkoutType 'blocks' or 'shortcode'.
	 */
	async function purchaseSubscriptionWithOC( page, checkoutType ) {
		const randomString = randomUUID();
		const username =
			randomString + '.' + config.get( 'users.customer.username' );

		await api.create.customer( {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username,
		} );

		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);

		// Add the subscription product to the cart, then set up the checkout
		// without letting the helper reset the cart to the default product.
		await emptyCart( page );
		await page.goto( `?p=${ productId }` );
		await clickAddToCartButton( page );

		await setupOptimizedCheckout( page, checkoutType, {
			skipCartSetup: true,
		} );
		await fillOCDetails( page, config.get( 'cards.basic' ), checkoutType );

		await clickPlaceOrder( page );
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	}

	test( 'customer can purchase a subscription with Optimized Checkout @smoke @blocks', async ( {
		page,
	} ) => {
		await purchaseSubscriptionWithOC( page, 'blocks' );
	} );

	test( 'customer can purchase a subscription with Optimized Checkout @smoke @shortcode', async ( {
		page,
	} ) => {
		await purchaseSubscriptionWithOC( page, 'shortcode' );
	} );
} );
