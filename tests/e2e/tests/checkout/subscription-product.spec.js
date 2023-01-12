import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = payments;

test( 'customer can purchase a subscription product @smoke @subscriptions', async ( {
	page,
} ) => {
	await emptyCart( page );

	await setupProductCheckout( page );
	await setupCheckout( page, config.get( 'addresses.customer.billing' ) );
	await fillCardDetails( page, config.get( 'cards.basic' ) );
	await page.locator( 'text=Place order' ).click();
	await page.waitForNavigation();

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
