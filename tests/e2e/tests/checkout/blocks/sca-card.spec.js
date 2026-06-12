import { test, expect } from '@playwright/test';
import config from 'config';
import { admin, payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetails,
	clickPlaceOrder,
	handleCheckout3DSChallenge,
	getCartTotal,
} = payments;

test( 'customer can checkout with a SCA card @smoke @blocks', async ( {
	page,
	browser,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetails( page, config.get( 'cards.3ds' ) );

	const expectedTotal = await getCartTotal( page );

	await clickPlaceOrder( page );

	await handleCheckout3DSChallenge( page );

	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);

	// As the admin, confirm the order was charged the expected amount.
	const orderId = admin.getOrderIdFromOrderReceivedUrl( page.url() );
	await admin.verifyOrderChargedAmount( browser, orderId, expectedTotal );
} );
