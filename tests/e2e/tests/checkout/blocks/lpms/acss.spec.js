import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, payments, api, user } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	setupACSSCheckout,
	fillACSSDetails,
} = payments;

test.describe( 'ACSS payment tests @blocks @acss', () => {
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

	test( 'customer can pay with ACSS @smoke', async ( { page } ) => {
		await setupACSSCheckout( page, 'blocks' );
		await page.locator( 'text=Place order' ).click();
		await fillACSSDetails( page );
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save and reuse ACSS payment method @smoke', async ( {
		page,
	} ) => {
		// First order - Save the payment method.
		await test.step(
			'Save payment method during first checkout',
			async () => {
				await user.login(
					page,
					username,
					config.get( 'users.customer.password' )
				);
				await setupACSSCheckout( page, 'blocks' );
				await page.getByLabel( 'Save payment information' ).click();
				await page.locator( 'text=Place order' ).click();
				await fillACSSDetails( page );
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			}
		);

		// Second order - Use saved payment method.
		await test.step(
			'Use saved payment method for second checkout',
			async () => {
				await emptyCart( page );
				await setupCart( page );
				await setupBlocksCheckout(
					page,
					config.get( 'addresses.customer_canada.billing' )
				);
				await page
					.locator( 'label' )
					.filter( { hasText: 'STRIPE TEST BANK ending in' } )
					.click();
				await page.locator( 'text=Place order' ).click();
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			}
		);
	} );
} );
