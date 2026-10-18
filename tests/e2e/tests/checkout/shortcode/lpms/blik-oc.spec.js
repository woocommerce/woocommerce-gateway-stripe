import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillBLIKDetails,
	waitForOrderReceivedPage,
} = payments;

// BLIK does not support deferred-intent creation, so it cannot render inside the
// Optimized Checkout Payment Element. With OCS enabled it must still appear as its
// own payment option and remain payable. This guards a regression where the method
// was hidden and its standalone intent excluded the very method being confirmed.
test.describe( 'BLIK payment tests under Optimized Checkout @shortcode @blik-oc', () => {
	test( 'customer can pay with BLIK when Optimized Checkout is enabled', async ( {
		page,
	} ) => {
		await emptyCart( page );
		await setupCart( page );
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer_poland.billing' )
		);

		// BLIK is a standalone option next to the OC Payment Element, not a row inside it.
		const blik = page.getByText( 'BLIK', { exact: true } );
		await expect( blik ).toBeVisible();
		await blik.click();

		await fillBLIKDetails( page );
		await page.locator( 'text=Place order' ).click();
		await waitForOrderReceivedPage( page );
	} );
} );
