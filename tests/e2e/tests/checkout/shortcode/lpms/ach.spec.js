import { test, expect } from '@playwright/test';
import config from 'config';
import { admin, payments, api, user } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillACHBankDetails,
	setupACHCheckout,
} = payments;

test.describe( 'ACH payment tests @shortcode', () => {
	let username, userEmail;

	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Create test user
			const randomString = Date.now();
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

			// Enable ACH in admin
			await admin.togglePaymentMethod(
				browser,
				'ACH Direct Debit',
				true
			);
		} );
	} );

	test( 'customer can pay with ACH using valid bank details @smoke', async ( {
		page,
	} ) => {
		await setupACHCheckout( page, 'shortcode' );
		await fillACHBankDetails( page );
		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save ACH payment method for future use @smoke', async ( {
		page,
	} ) => {
		await test.step( 'Login and setup checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupACHCheckout( page, 'shortcode' );
		} );

		await test.step(
			'Connect bank account and save payment information',
			async () => {
				await fillACHBankDetails( page );
				await page
					.getByRole( 'checkbox', {
						name: 'Save payment information to',
					} )
					.click();
			}
		);

		await test.step( 'Complete order', async () => {
			await page.locator( 'text=Place order' ).click();
			await page.waitForURL( '**/checkout/order-received/**' );
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );
	} );

	test( 'customer can reuse ACH payment method @smoke', async ( {
		page,
	} ) => {
		await test.step( 'Login and setup checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await emptyCart( page );
			await setupCart( page );
			await setupShortcodeCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
			// Select ACH in shortcode checkout
			await page.getByText( 'ACH Direct Debit' ).click();
		} );

		await test.step(
			'Complete order with saved payment method',
			async () => {
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
} );
