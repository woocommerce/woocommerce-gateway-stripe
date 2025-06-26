import { test, expect } from '@playwright/test';
import { admin, payments } from '../../../utils';

const { setupAffirmCheckout } = payments;

test.describe( 'Affirm payment tests @shortcode', () => {
	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Enable Affirm in admin
			await admin.togglePaymentMethod( browser, 'Affirm', true );
		} );
	} );

	test.describe.configure( { mode: 'parallel' } );

	test( 'customer can pay with Affirm @smoke', async ( { page } ) => {
		await setupAffirmCheckout( page, 'shortcode' );
		await page.locator( 'text=Place order' ).click();
		// Since we don't have control over the Affirm payment flow,
		// verifying the redirect to Stripe or Affirm is all we can do consistently
		// without introducing a flaky test.
		await expect( page ).toHaveURL( /.*(affirm\.com|stripe\.com)/ );
	} );
} );
