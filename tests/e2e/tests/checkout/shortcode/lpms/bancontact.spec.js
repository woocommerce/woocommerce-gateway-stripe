import { test } from '@playwright/test';
import config from 'config';
import { payments } from '../../../../utils';

const {
	clickPlaceOrder,
	setupEuroLPMCheckout,
	handleHostedTestPaymentPage,
	waitForOrderReceivedPage,
} = payments;

test.describe( 'Bancontact payment tests @shortcode @bancontact', () => {
	test( 'customer can pay with Bancontact', async ( { page } ) => {
		await setupEuroLPMCheckout(
			page,
			'Bancontact',
			config.get( 'addresses.customer_belgium.billing' ),
			'shortcode'
		);

		await clickPlaceOrder( page );
		await handleHostedTestPaymentPage( page );
		await waitForOrderReceivedPage( page );
	} );
} );
