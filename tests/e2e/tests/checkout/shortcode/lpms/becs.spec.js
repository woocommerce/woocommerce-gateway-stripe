import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	setupBECSCheckout,
	fillBECSDetails,
} = payments;

test.describe( 'BECS payment tests @shortcode @becs', () => {
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

	test( 'customer can pay with BECS @smoke', async ( { page } ) => {
		await setupBECSCheckout( page, 'shortcode' );
		await fillBECSDetails( page, 'shortcode' );
		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save and reuse BECS payment method @smoke', async ( {
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
				await setupBECSCheckout( page, 'shortcode' );
				await page
					.getByRole( 'checkbox', {
						name: 'Save payment information to',
					} )
					.click();
				await fillBECSDetails( page, 'shortcode' );
				await page.locator( 'text=Place order' ).click();
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
				await setupShortcodeCheckout(
					page,
					config.get( 'addresses.customer_australia.billing' )
				);
				await page.getByText( 'BECS Direct Debit' ).first().click();
				await page.waitForTimeout( 1000 );
				await page
					.locator( '.woocommerce-SavedPaymentMethods-token' )
					.first()
					.click();
				await page.locator( 'text=Place order' ).click();
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			}
		);
	} );

	test( 'BECS is only available for Australian customers @smoke', async ( {
		page,
	} ) => {
		await emptyCart( page );
		await setupCart( page );

		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer_canada.billing' )
		);

		// Verify BECS is not available
		await expect( page.getByText( 'BECS Direct Debit' ) ).not.toBeVisible();

		// Change country to Australia
		await page.selectOption( '#billing_country', 'AU' );

		// Verify BECS is available
		await expect( page.getByText( 'BECS Direct Debit' ) ).toBeVisible();
	} );
} );
