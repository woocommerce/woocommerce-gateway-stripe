import { test, expect } from '@playwright/test';
import config from 'config';
import { admin, payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	getCartTotal,
} = payments;

test( 'customer can checkout with a normal credit card @smoke', async ( {
	page,
	browser,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.basic' ) );

	const expectedTotal = await getCartTotal( page );

	await page.locator( 'text=Place order' ).dispatchEvent( 'click' );
	await page.waitForNavigation();

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);

	// As the admin, confirm the order was charged the expected amount.
	const orderId = admin.getOrderIdFromOrderReceivedUrl( page.url() );
	await admin.verifyOrderChargedAmount( browser, orderId, expectedTotal );
} );
