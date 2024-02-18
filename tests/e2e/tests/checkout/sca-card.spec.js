import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = payments;

test( 'customer can checkout with a SCA card @smoke', async ( { page } ) => {
	await emptyCart( page );

	await setupProductCheckout( page );
	await setupCheckout( page, config.get( 'addresses.customer.billing' ) );
	await fillCardDetails( page, config.get( 'cards.3ds' ) );
	await page.locator( 'text=Place order' ).click();

	// Wait until the SCA frame is available
	while (
		! page.frame( {
			name: 'stripe-challenge-frame',
		} )
	) {
		await page.waitForTimeout( 1000 );
	}

	await page
		.frame( {
			name: 'stripe-challenge-frame',
		} )
		.getByRole( 'button', { name: 'Complete' } )
		.click();

	await page.waitForNavigation();

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
