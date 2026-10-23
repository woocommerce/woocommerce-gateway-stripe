import { test } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	handleCheckout3DSChallenge,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
	getGuestBillingDetails,
} = payments;

test( 'customer can checkout with a SCA card @smoke', async ( {
	page,
	browser,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout( page, getGuestBillingDetails() );
	await fillCreditCardDetailsShortcode( page, config.get( 'cards.3ds' ) );

	const expectedTotal = await getCartTotal( page );

	await page.locator( 'text=Place order' ).dispatchEvent( 'click' );

	// Complete the 3DS challenge
	await handleCheckout3DSChallenge( page );

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );
