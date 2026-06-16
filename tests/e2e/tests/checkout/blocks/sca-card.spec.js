import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetails,
	clickPlaceOrder,
	handleCheckout3DSChallenge,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
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

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );
