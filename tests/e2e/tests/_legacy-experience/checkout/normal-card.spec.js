import { test, expect } from '@playwright/test';
import { payments, config } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcodeLegacy,
} = payments;

test.slow();
test( 'customer can checkout with a normal credit card @smoke @legacy', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcodeLegacy(
		page,
		config.get( 'cards.basic' )
	);
	await page.locator( '#place_order' ).first().click();
	await page.waitForURL( '**/checkout/order-received/**', {
		timeout: 20000,
	} ); // Allow some extra time for the redirect to complete.

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
