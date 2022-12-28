const { test, expect } = require( '@playwright/test' );
import config from 'config';
const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = require( '../../utils/payments' );

test.describe.configure( { mode: 'parallel' } );

test.describe( 'Shopper - Checkout', () => {
	test.beforeEach( async ( { page } ) => {
		await emptyCart( page );
		await setupProductCheckout( page );
		await setupCheckout( page, config.get( 'addresses.customer.billing' ) );
	} );

	test( 'customer can checkout with a normal credit card @smoke', async ( {
		page,
	} ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save card at the checkout @smoke', async ( {
		page,
	} ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can checkout with a saved card @smoke', async ( {
		page,
	} ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can checkout with a Strong Customer Authentication (SCA) card @smoke', async ( {
		page,
	} ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
	test( 'customer can save card at the checkout', async ( { page } ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can checkout with a saved card', async ( { page } ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can checkout with a Strong Customer Authentication (SCA) card', async ( {
		page,
	} ) => {
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await page.locator( 'text=Place order' ).click();
		await page.waitForNavigation();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
