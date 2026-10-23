import { test } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
	getGuestBillingDetails,
} = payments;

test( 'customer can checkout with a normal credit card @smoke', async ( {
	page,
	browser,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout( page, getGuestBillingDetails() );
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.basic' ) );

	const expectedTotal = await getCartTotal( page );

	await page.locator( 'text=Place order' ).dispatchEvent( 'click' );

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );
