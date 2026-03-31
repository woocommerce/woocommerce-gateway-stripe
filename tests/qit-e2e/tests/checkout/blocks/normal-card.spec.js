import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	fillCreditCardDetails,
	setupBlocksCheckout,
	clickPlaceOrder,
} = payments;

test( 'customer can checkout with a normal credit card @smoke @blocks', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);

	await fillCreditCardDetails( page, config.get( 'cards.basic' ) );
	await clickPlaceOrder( page );
	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
