import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { admin, payments, api, user } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillACHBankDetails,
	setupACHCheckout,
} = payments;

test.describe( 'ACH payment tests @blocks', () => {
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
		await setupACHCheckout( page, 'blocks' );
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
			await setupACHCheckout( page, 'blocks' );
			await fillACHBankDetails( page );
			await page
				.locator(
					'.wc-block-components-payment-methods__save-card-info'
				)
				.click();

			await page.locator( 'text=Place order' ).click();
			await page.waitForURL( '**/checkout/order-received/**' );
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );

		// Second order - Use saved payment method
		await test.step( 'Use saved payment method for second checkout', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupBlocksCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
			await page
				.locator( 'label' )
				.filter( { hasText: 'Checking account ending in' } )
				.click();

			await page.locator( 'text=Place order' ).click();
			await page.waitForURL( '**/checkout/order-received/**' );
			await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
				'Order received'
			);
		} );
	} );
} );
