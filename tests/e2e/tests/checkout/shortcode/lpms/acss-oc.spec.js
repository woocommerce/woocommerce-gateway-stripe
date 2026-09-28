import { test, expect } from '@playwright/test';
import { payments } from '../../../../utils';

const {
	clickPlaceOrder,
	setupACSSCheckout,
	fillACSSDetails,
	waitForOrderReceivedPage,
} = payments;

// ACSS does not support deferred-intent creation, so it cannot render inside the
// Optimized Checkout Payment Element. With OCS enabled it must still appear as its
// own payment option and remain payable. This guards a regression where the method
// was hidden and its standalone intent excluded the very method being confirmed.
test.describe( 'ACSS payment tests under Optimized Checkout @shortcode @acss-oc', () => {
	test( 'customer can pay with ACSS when Optimized Checkout is enabled', async ( {
		page,
	} ) => {
		// setupACSSCheckout selects the standalone "Pre-Authorized Debit" option,
		// which is where ACSS renders under OCS (outside the OC Payment Element).
		await setupACSSCheckout( page, 'shortcode' );
		await expect(
			page.getByText(
				'After submission, you will need to authorize the payment with your bank.'
			)
		).toBeVisible();
		await clickPlaceOrder( page );
		await fillACSSDetails( page );
		await waitForOrderReceivedPage( page );
	} );
} );
