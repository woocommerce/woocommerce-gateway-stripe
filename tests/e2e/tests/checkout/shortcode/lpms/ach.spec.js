import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, payments, api, user } from '../../../../utils';

const {
	clickPlaceOrder,
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

			// Enable ACH in admin
			await admin.togglePaymentMethod(
				browser,
				'ACH Direct Debit',
				true
			);
		} );
	} );

	test.describe.configure( { mode: 'parallel' } );

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

	test( 'customer can save and reuse ACH payment method @smoke', async ( {
		page,
	} ) => {
		// First order - Save the payment method
		await test.step( 'Save payment method during first checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupACHCheckout( page, 'shortcode' );
			await fillACHBankDetails( page );
			await page
				.getByRole( 'checkbox', {
					name: 'Save payment information to',
				} )
				.click();
			await clickPlaceOrder( page );
			await page.waitForURL( '**/checkout/order-received/**' );
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );

		// Second order - Use saved payment method
		await test.step( 'Use saved payment method for second checkout', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupShortcodeCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
			await page.getByText( 'ACH Direct Debit' ).click();
			await expect(
				page.locator(
					'.woocommerce-SavedPaymentMethods-token input[id^="wc-stripe_us_bank_account-payment-token-"]'
				)
			).toHaveCount( 1 );
			await page
				.locator( '.woocommerce-SavedPaymentMethods-token' )
				.first()
				.click();
			await clickPlaceOrder( page );
			await page.waitForURL( '**/checkout/order-received/**' );
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );
	} );
} );
