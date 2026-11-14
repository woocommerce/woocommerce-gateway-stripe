import { randomUUID } from 'crypto';
import { test } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	setupOptimizedCheckout,
	fillOCDetails,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

test.describe( 'Optimized Checkout payment tests @blocks', () => {
	test( 'customer can pay with Optimized Checkout @smoke', async ( {
		page,
		browser,
	} ) => {
		await setupOptimizedCheckout( page, 'blocks', {
			billingDetails: {
				...config.get( 'addresses.customer.billing' ),
				email: `stripe-e2e-${ randomUUID() }@example.com`,
			},
		} );
		await fillOCDetails( page, config.get( 'cards.basic' ) );

		const expectedTotal = await getCartTotal( page );

		await clickPlaceOrder( page );

		await waitForOrderReceivedPageAndConfirmExpectedTotal(
			browser,
			page,
			expectedTotal
		);
	} );
} );
