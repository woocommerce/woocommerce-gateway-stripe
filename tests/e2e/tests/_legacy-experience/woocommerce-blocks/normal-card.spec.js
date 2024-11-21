import { test, expect } from '@playwright/test';
import { payments, config } from '../../../utils';

const {
	emptyCart,
	setupCart,
	fillCreditCardDetailsLegacy,
	setupBlocksCheckout,
} = payments;
test.slow();
test( 'customer can checkout with a normal credit card @smoke @blocks @legacy', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);

	await fillCreditCardDetailsLegacy( page, config.get( 'cards.basic' ) );
	await page.locator( 'text=Place order' ).click();
	await page.waitForURL( '**/checkout/order-received/**', {
		timeout: 20000,
	} ); // Allow some extra time for the redirect to complete.

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
