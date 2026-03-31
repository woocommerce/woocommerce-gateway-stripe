import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	handleCheckout3DSChallenge,
} = payments;

test( 'customer can checkout with a SCA card @smoke', async ( { page } ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.3ds' ) );
	await page.locator( 'text=Place order' ).dispatchEvent( 'click' );

	// Complete the 3DS challenge
	await handleCheckout3DSChallenge( page );

	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
