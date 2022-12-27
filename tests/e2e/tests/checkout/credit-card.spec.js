const { test, expect } = require( '@playwright/test' );
import config from 'config';
const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
} = require( '../../utils/payments' );

test.beforeEach( async ( { page } ) => {
	await emptyCart( page );
	await setupProductCheckout( page );
	await setupCheckout( page, config.get( 'addresses.customer.billing' ) );
} );

test( 'customer can checkout with a normal credit card @smoke', async ( {
	page,
} ) => {
	await page.pause();
} );
