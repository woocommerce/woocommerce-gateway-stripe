import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api } from '../../../../utils';

const { emptyCart, setupCart, setupBlocksCheckout, fillBLIKDetails } = payments;

test.describe( 'BLIK payment tests @blocks @blik', () => {
	let username, userEmail;

	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Create test user.
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
		} );
	} );

	test( 'customer can pay with BLIK', async ( { page } ) => {
		await emptyCart( page );
		await setupCart( page );
		await setupBlocksCheckout(
			page,
			config.get( 'addresses.customer_poland.billing' )
		);
		await page.getByLabel( /blik/i ).check();
		await fillBLIKDetails( page );
		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
