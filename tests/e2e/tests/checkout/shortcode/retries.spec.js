import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
} = payments;

test.beforeAll( 'enable Cash App Pay', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
	);
	await page.getByLabel( 'Cash App Pay' ).check();
	await page.click( 'text=Save changes' );

	await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
	await expect( page.getByLabel( 'Cash App Pay' ) ).toBeChecked();
} );

test( 'customer can retry payment @smoke', async ( { page } ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcode(
		page,
		config.get( 'cards.declined' )
	);
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );

	// Expect the order to fail
	await expect( page.locator( '.woocommerce-error' ) ).toBeVisible();

	// Change to a working card
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.basic' ) );
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );
	await page.waitForURL( '**/order-received/**' );

	// Expect the order to succeed
	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );

test( 'customer can retry payment, using a different payment method @smoke', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcode(
		page,
		config.get( 'cards.declined' )
	);
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );

	// Expect the order to fail
	await expect( page.locator( '.woocommerce-error' ) ).toBeVisible();

	// Change to Cash App Pay
	await page.getByText( 'Cash App Pay' ).click();
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );

	// Expect a modal to appear
	const simulateScanButton = await page
		.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
		.first()
		.frameLocator( 'iframe[title="QR Code Instructions"]' )
		.getByRole( 'button', { name: 'Simulate scan' } );

	const context = await page.context();
	const [ paymentPage ] = await Promise.all( [
		context.waitForEvent( 'page' ),
		simulateScanButton.dispatchEvent( 'click' ),
	] );

	await paymentPage.waitForLoadState();
	await paymentPage
		.getByRole( 'link', { name: 'Authorize Test Payment' } )
		.click();
	await paymentPage.waitForURL( '**/order-received/**' );

	// Expect the order to succeed
	await expect( paymentPage.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
