import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../../utils';
import { admin } from '../../../../utils';

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
			const adminContext = await browser.newContext( {
				storageState: process.env.ADMINSTATE,
			} );
			const adminPage = await adminContext.newPage();
			await admin.toggleACHPaymentMethod( adminPage, true );
			await adminContext.close();
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await test.step( 'Cleanup test environment', async () => {
			const adminContext = await browser.newContext( {
				storageState: process.env.ADMINSTATE,
			} );
			const page = await adminContext.newPage();
			await admin.toggleACHPaymentMethod( page, false );
			await adminContext.close();
		} );
	} );

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

	test( 'customer can save ACH payment method for future use @smoke', async ( {
		page,
	} ) => {
		await test.step( 'Login and setup checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupACHCheckout( page, 'blocks' );
		} );

		await test.step( 'Connect bank account', async () => {
			await fillACHBankDetails( page );
			await page
				.locator(
					'.wc-block-components-payment-methods__save-card-info'
				)
				.click();
		} );

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
			await setupBlocksCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
		} );

		await test.step(
			'Complete order with saved payment method',
			async () => {
				await page
					.locator( 'label' )
					.filter( { hasText: 'Checking account ending in' } )
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
