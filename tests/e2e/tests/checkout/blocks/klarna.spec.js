import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, payments, api, user } from '../../../utils';

const { setupKlarnaCheckout, completeKlarnaPayment } = payments;

test.describe( 'Klarna payment tests @blocks', () => {
	let username, userEmail;

	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Create test user
			const randomString = randomUUID();
			userEmail =
				randomString + '+' + config.get( 'users.customer.email' );
			username =
				randomString + '.' + config.get( 'users.customer.username' );

			const testUser = {
				...config.get( 'users.customer' ),
				...config.get( 'addresses.customer' ),
				email: userEmail,
				username,
			};
			await api.create.customer( testUser );

			// Enable Klarna in admin
			await admin.togglePaymentMethod( browser, 'Klarna', true );
		} );
	} );

	test.describe.configure( { mode: 'parallel' } );

	test( 'customer can pay with Klarna @smoke', async ( { page } ) => {
		await setupKlarnaCheckout( page, 'blocks' );
		await page.locator( 'text=Place order' ).click();
		await completeKlarnaPayment( page );
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
