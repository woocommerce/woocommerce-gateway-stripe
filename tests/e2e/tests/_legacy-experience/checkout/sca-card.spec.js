import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcodeLegacy,
} = payments;

test( 'customer can checkout with a SCA card @smoke', async ( { page } ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcodeLegacy(
		page,
		config.get( 'cards.3ds' )
	);
	await page.locator( 'text=Place order' ).click();

	// Wait until the SCA frame is available
	while (
		! page.frame( {
			name: 'stripe-challenge-frame',
		} )
	) {
		await page.waitForTimeout( 1000 );
	}
	// Not ideal, but the iframe body gets repalced after load, so a waitFor does not work here.
	await page.waitForTimeout( 2000 );

	await page
		.frame( {
			name: 'stripe-challenge-frame',
		} )
		.getByRole( 'button', { name: 'Complete' } )
		.click();

	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
