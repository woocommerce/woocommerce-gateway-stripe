import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, products, user } from '../../utils';

let username, customerId, productId, subscriptionId;

test.beforeAll( async () => {
	const randomString = randomUUID();
	username = randomString + '.' + config.get( 'users.customer.username' );

	customerId = await api.create.customer( {
		...config.get( 'users.customer' ),
		...config.get( 'addresses.customer' ),
		email: randomString + '+' + config.get( 'users.customer.email' ),
		username,
	} );
	productId = await api.create.product( products.subscriptionData() );

	// A manual-renewal subscription with a future next payment is the shape the
	// change-payment-method page accepts; no payment method means manual renewal.
	const nextPayment = new Date( Date.now() + 30 * 24 * 60 * 60 * 1000 );
	const subscription = await api.create.subscription( {
		customer_id: customerId,
		status: 'active',
		billing_period: 'month',
		billing_interval: 1,
		// Subscriptions validates dates as Y-m-d H:i:s, not ISO 8601.
		next_payment_date: nextPayment
			.toISOString()
			.slice( 0, 19 )
			.replace( 'T', ' ' ),
		line_items: [ { product_id: productId, quantity: 1 } ],
	} );
	subscriptionId = subscription.id;
} );

test.afterAll( async () => {
	if ( productId ) {
		await api.deletePost.product( productId );
	}
} );

// Subscriptions reuses the pay-for-order form; Stripe's submit handler must leave it alone when another gateway is selected.
test( 'customer can change a subscription payment method to a non-Stripe gateway @subscriptions', async ( {
	page,
	context,
	baseURL,
} ) => {
	// The e2e mu-plugin registers the non-Stripe gateway only when this cookie is set.
	await context.addCookies( [
		{ name: 'wc_stripe_e2e_mock_gateway', value: '1', url: baseURL },
	] );

	await user.login( page, username, config.get( 'users.customer.password' ) );

	await page.goto(
		`/my-account/subscription-payment-method/${ subscriptionId }/`
	);
	await page.locator( 'label[for="payment_method_e2e_mock"]' ).click();
	await page.locator( '#place_order' ).click();

	await page.waitForURL(
		`**/my-account/view-subscription/${ subscriptionId }/**`
	);
	await expect( page.locator( '.woocommerce-message' ) ).toContainText(
		'Payment method added.'
	);
} );
