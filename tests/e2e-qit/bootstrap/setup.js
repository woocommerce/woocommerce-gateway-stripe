import { test, expect } from '@playwright/test';
import { emptyCart, setupCart } from '../utils/payments';

/**
 * Isolated setup that runs before your plugin's tests.
 * Use it to:
 * - Set up UI settings that can't be done via CLI
 * - Prepare plugin-specific browser storage
 * - Create any UI-generated test data
 * - Set up plugin state via UI interactions
 */

/**
 * Test if Stripe Payment Gateway is vonfigured and visible on Checkout Page
 *
 * @param { import('@playwright/test').Page } page
 */
test( 'Verify Stripe Payment Gateway is Configured and Visible on Checkout Page', async ( {
	page,
} ) => {
	await setupCart( page, [ [ 'Simple Product', 1 ] ] );
	await page.goto( '/checkout/' );

	await expect(
		page.locator(
			'label[for="radio-control-wc-payment-method-options-stripe"]'
		)
	).toBeVisible();
	await emptyCart( page );
} );
