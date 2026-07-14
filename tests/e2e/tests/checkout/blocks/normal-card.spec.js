import { test, expect } from '@playwright/test';
import config from 'config';
import { admin, payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	fillCreditCardDetails,
	setupBlocksCheckout,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

test( 'customer can checkout with a normal credit card @smoke @blocks', async ( {
	page,
	browser,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);

	await fillCreditCardDetails( page, config.get( 'cards.basic' ) );

	const expectedTotal = await getCartTotal( page );

	await clickPlaceOrder( page );

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );
