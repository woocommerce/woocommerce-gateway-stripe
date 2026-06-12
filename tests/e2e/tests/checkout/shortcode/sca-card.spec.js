import { test, expect } from '@playwright/test';
import config from 'config';
import { admin, payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	handleCheckout3DSChallenge,
	getCartTotal,
} = payments;

test( 'customer can checkout with a SCA card @smoke', async ( {
	page,
	browser,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.3ds' ) );

	const expectedTotal = await getCartTotal( page );

	await page.locator( 'text=Place order' ).dispatchEvent( 'click' );

	// Complete the 3DS challenge
	await handleCheckout3DSChallenge( page );

	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);

	// As the admin, confirm the order was charged the expected amount.
	const orderId = admin.getOrderIdFromOrderReceivedUrl( page.url() );
	await admin.verifyOrderChargedAmount( browser, orderId, expectedTotal );
} );
