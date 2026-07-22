import { test } from '@playwright/test';
import config from 'config';
import { payments } from '../../../../utils';

const {
	clickPlaceOrder,
	setupEuroLPMCheckout,
	handleHostedTestPaymentPage,
	waitForOrderReceivedPage,
} = payments;

test.describe( 'iDEAL payment tests @blocks @ideal', () => {
	test( 'customer can pay with iDEAL', async ( { page } ) => {
		await setupEuroLPMCheckout(
			page,
			'iDEAL | Wero',
			config.get( 'addresses.customer_netherlands.billing' ),
			'blocks'
		);

		await clickPlaceOrder( page );
		await handleHostedTestPaymentPage( page );
		await waitForOrderReceivedPage( page );
	} );
} );
