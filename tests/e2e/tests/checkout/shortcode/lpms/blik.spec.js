import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillBLIKDetails,
} = payments;

test.describe( 'BLIK payment tests @shortcode @blik', () => {
	test( 'customer can pay with BLIK', async ( { page } ) => {
		await emptyCart( page );
		await setupCart( page );
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer_poland.billing' )
		);
		await page.getByText( 'BLIK', { exact: true } ).click();
		await fillBLIKDetails( page );
		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
