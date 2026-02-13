import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetails,
	handleCheckout3DSChallenge,
} = payments;

test( 'customer can checkout with a SCA card @smoke @blocks', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetails( page, config.get( 'cards.3ds' ) );
	await page.locator( 'text=Place order' ).click();

	await handleCheckout3DSChallenge( page );

	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
