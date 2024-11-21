import { test, expect } from '@playwright/test';
import { payments, config } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetailsLegacy,
} = payments;

test.slow();
test( 'customer can checkout with a SCA card @smoke @blocks @legacy', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
	await fillCreditCardDetailsLegacy( page, config.get( 'cards.3ds' ) );
	await page.locator( 'text=Place order' ).click();

	// Wait until the SCA frame is available
	while (
		! page.frame( {
			name: 'stripe-challenge-frame',
		} )
	) {
		await page.waitForTimeout( 1000 );
	}

	await page.waitForTimeout( 3000 );

	await page
		.frame( {
			name: 'stripe-challenge-frame',
		} )
		.getByRole( 'button', { name: 'Complete' } )
		.click();

	await page.waitForURL( '**/checkout/order-received/**' );

	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
