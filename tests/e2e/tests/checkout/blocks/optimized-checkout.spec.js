import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupOptimizedCheckout,
	setupBlocksCheckout,
	fillOCDetails,
	clickPlaceOrder,
} = payments;

test.describe( 'Optimized Checkout payment tests @blocks', () => {
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

	test( 'customer can pay with Optimized Checkout @smoke', async ( {
		page,
	} ) => {
		await setupOptimizedCheckout( page, 'blocks' );
		await fillOCDetails( page, config.get( 'cards.basic' ) );
		await clickPlaceOrder( page );
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save and reuse Optimized Checkout payment method @smoke', async ( {
		page,
		browser,
	} ) => {
		// Disable Link so the store-level save checkbox is visible for this test.
		// When Link is enabled, the store checkbox is hidden and Link handles save consent,
		// but these tests verify WC token creation which requires the store checkbox.
		await admin.togglePaymentMethod( browser, 'Link by Stripe', false );

		try {
			// First order - Save the payment method.
			await test.step( 'Save payment method during first checkout', async () => {
				await user.login(
					page,
					username,
					config.get( 'users.customer.password' )
				);
				await setupOptimizedCheckout( page, 'blocks' );
				await page.getByLabel( 'Save payment information' ).click();
				await fillOCDetails( page, config.get( 'cards.basic' ) );
				await clickPlaceOrder( page );
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			} );

			// Second order - Use saved payment method.
			await test.step( 'Use saved payment method for second checkout', async () => {
				await emptyCart( page );
				await setupCart( page );
				await setupBlocksCheckout(
					page,
					config.get( 'addresses.customer.billing' )
				);
				await page
					.locator( 'label' )
					.filter( { hasText: 'Visa ending in 4242 (expires' } )
					.click();
				await clickPlaceOrder( page );
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			} );
		} finally {
			// Re-enable Link after the test.
			await admin.togglePaymentMethod( browser, 'Link by Stripe', true );
		}
	} );
} );
