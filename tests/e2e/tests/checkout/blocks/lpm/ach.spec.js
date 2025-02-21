import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../../utils';

const { emptyCart, setupCart, setupBlocksCheckout } = payments;

let username, userEmail;

test.describe( 'ACH payment tests @blocks', () => {
	test.beforeAll( async ( { browser } ) => {
		// Enable ACH payments in admin settings
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

		await expect( adminPage.getByText( 'Settings saved.' ) ).toBeDefined();
		await expect(
			adminPage.getByRole( 'checkbox', { name: 'ACH Direct Debit' } )
		).toBeChecked();

		await adminContext.close();

		// Create test user for saved payment method test
		const randomString = Date.now();
		userEmail = randomString + '+' + config.get( 'users.customer.email' );
		username = randomString + '.' + config.get( 'users.customer.username' );

		const testUser = {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer' ),
			email: userEmail,
			username,
		};

		await api.create.customer( testUser );
	} );

	test.afterAll( async ( { browser } ) => {
		// Disable ACH payments in admin settings
		const adminContext = await browser.newContext( {
			storageState: process.env.ADMINSTATE,
		} );
		const page = await adminContext.newPage();

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
		);
		await page
			.getByRole( 'checkbox', { name: 'ACH Direct Debit' } )
			.uncheck();
		await page.click( 'text=Save changes' );

		await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
		await expect(
			page.getByRole( 'checkbox', { name: 'ACH Direct Debit' } )
		).not.toBeChecked();

		await adminContext.close();
	} );

	test.beforeEach( async ( { page } ) => {
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

		await page.pause();

		// Click "Enter bank details manually"
		await page
			.frameLocator(
				'#radio-control-wc-payment-method-options-stripe_us_bank_account__content iframe[title="Secure payment input frame"]'
			)
			.getByRole( 'button', { name: 'Enter bank details manually' } )
			.click();
	} );

	const fillBankDetails = async (
		page,
		accountNumber,
		routingNumber = '110000000'
	) => {
		const frame = page
			.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
			.first();

		// Fill account number
		await frame
			.locator( 'label' )
			.filter( { hasText: /^Account number$/ } )
			.fill( accountNumber );

		// Fill confirm account number
		await frame
			.locator( 'label' )
			.filter( { hasText: 'Confirm account number' } )
			.fill( accountNumber );

		// Fill routing number
		await frame
			.locator( 'label' )
			.filter( { hasText: 'Routing number' } )
			.fill( routingNumber );

		// Click submit button
		await frame
			.getByTestId( 'continue-button' )
			.filter( { hasText: 'Submit' } )
			.click();

		// Skip link registration
		await frame.getByTestId( 'link-not-now-button' ).click();

		// Click "Done" button.
		await frame.getByTestId( 'done-button' ).click();
	};

	test( 'customer can pay with ACH using valid bank details @smoke', async ( {
		page,
	} ) => {
		await page.pause();
		await fillBankDetails( page, '000123456789' );
		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save ACH payment method for future use @smoke', async ( {
		page,
	} ) => {
		// Login first
		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);
		await page.goto( '/checkout/' );

		await fillBankDetails( page, '000123456789' );

		// Save payment method
		await page
			.locator( '.wc-block-components-payment-methods__save-card-info' )
			.click();

		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
