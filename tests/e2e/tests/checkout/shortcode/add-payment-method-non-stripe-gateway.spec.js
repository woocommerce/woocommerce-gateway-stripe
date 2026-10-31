import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { api, user } from '../../../utils';

let username;

test.beforeAll( async () => {
	const randomString = randomUUID();
	username = randomString + '.' + config.get( 'users.customer.username' );

	await api.create.customer( {
		...config.get( 'users.customer' ),
		...config.get( 'addresses.customer' ),
		email: randomString + '+' + config.get( 'users.customer.email' ),
		username,
	} );
} );

// Stripe's submit handler must leave the shared form alone when another gateway is selected.
test( 'customer can add a payment method with a non-Stripe gateway @shortcode', async ( {
	page,
	context,
	baseURL,
} ) => {
	// The e2e mu-plugin registers the non-Stripe gateway only when this cookie is set.
	await context.addCookies( [
		{ name: 'wc_stripe_e2e_mock_gateway', value: '1', url: baseURL },
	] );

	await user.login( page, username, config.get( 'users.customer.password' ) );

	await page.goto( '/my-account/add-payment-method/' );
	await page.locator( 'label[for="payment_method_e2e_mock"]' ).click();
	await page.locator( '#place_order' ).click();

	await page.waitForURL( '**/my-account/payment-methods/**' );
	await expect( page.locator( '.woocommerce-message' ) ).toContainText(
		'Payment method successfully added.'
	);
} );
