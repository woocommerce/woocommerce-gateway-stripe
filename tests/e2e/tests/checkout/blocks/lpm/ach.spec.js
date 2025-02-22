import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../../utils';

const { emptyCart, setupCart, setupBlocksCheckout } = payments;

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

			await adminPage.goto(
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
			);
			await adminPage
				.getByRole( 'checkbox', { name: 'ACH Direct Debit' } )
				.check();
			await adminPage.click( 'text=Save changes' );

			await expect(
				adminPage.getByText( 'Settings saved.' )
			).toBeDefined();
			await adminContext.close();
		} );
	} );

	test.afterAll( async ( { browser } ) => {
		await test.step( 'Cleanup test environment', async () => {
			const adminContext = await browser.newContext( {
				storageState: process.env.ADMINSTATE,
			} );
			const page = await adminContext.newPage();

			await page.goto(
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
			);
			await page
				.getByRole( 'checkbox', { name: 'ACH Direct Debit' } )
				.click();
			await page.getByRole( 'button', { name: 'Remove' } ).click();
			await page.click( 'text=Save changes' );

			await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
			await adminContext.close();
		} );
	} );

	const fillBankDetails = async ( page ) => {
		const frame = page
			.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
			.first();

		// Agree and Continue
		await frame.getByTestId( 'agree-button' ).click();

		// Click "Success ••••" button
		await frame.getByRole( 'button', { name: 'Success ••••' } ).click();

		// Click "Connect Account" button.
		await frame.getByTestId( 'select-button' ).click();

		// Skip link registration
		await frame.getByTestId( 'link-not-now-button' ).click();

		// Click "Done" button.
		await frame.getByTestId( 'done-button' ).click();
	};

	const setupACHCheckout = async ( page ) => {
		await emptyCart( page );
		await setupCart( page );

		await setupBlocksCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);

		// Select ACH payment method
		await page
			.locator( 'label' )
			.filter( { hasText: 'ACH Direct Debit' } )
			.click();

		// Click "Test Institution"
		await page
			.frameLocator(
				'#radio-control-wc-payment-method-options-stripe_us_bank_account__content iframe[src*="elements-inner-payment"]'
			)
			.getByText( 'Test Institution' )
			.click();
	};

	test( 'customer can pay with ACH using valid bank details @smoke', async ( {
		page,
	} ) => {
		await setupACHCheckout( page );
		await fillBankDetails( page );
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
			await setupACHCheckout( page );
		} );

		await test.step( 'Connect bank account', async () => {
			await fillBankDetails( page );
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
