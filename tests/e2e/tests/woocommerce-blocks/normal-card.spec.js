import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	fillCardDetails,
	setupBlocksCheckout,
} = payments;

test( 'customer can checkout with a normal credit card @smoke @blocks', async ( {
	page,
} ) => {
	await emptyCart( page );

	await setupProductCheckout( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);

	await fillCardDetails( page, config.get( 'cards.basic' ) );
	await page.locator( 'text=Place order' ).click();
	await page.waitForNavigation();

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
