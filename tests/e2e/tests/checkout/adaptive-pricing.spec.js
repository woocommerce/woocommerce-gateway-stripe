/**
 * Adaptive Pricing flows. On a test-mode US account with working webhooks the
 * feature is available, and a same-currency shopper still checks out through
 * the Adaptive Pricing (checkout sessions) path — Stripe just presents the
 * store currency — so these flows exercise the real session code end-to-end.
 */
import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../utils';

const {
	setupOptimizedCheckout,
	fillOCDetails,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPage,
} = payments;

const SETTINGS_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings';

const AP_CHECKBOX_LABEL =
	'Let customers pay in their local currency with Adaptive Pricing';
const MANUAL_CAPTURE_LABEL =
	'Issue an authorization on checkout, and capture later';

/**
 * Completes an Adaptive Pricing card purchase through to the received page.
 *
 * @param {Page}   page         Playwright page fixture.
 * @param {string} checkoutType 'shortcode' or 'blocks'.
 */
const payWithAdaptivePricing = async ( page, checkoutType ) => {
	await setupOptimizedCheckout( page, checkoutType, {
		timeout: 10000,
		skipCartSetup: false,
		cardSelectionOptional: true,
	} );
	await fillOCDetails( page, config.get( 'cards.basic' ), checkoutType );

	const expectedTotal = await getCartTotal( page );

	await clickPlaceOrder( page );

	// Adaptive Pricing orders are only marked paid by the checkout session
	// webhooks, which cannot reach the tunnel-less CI site — so assert the
	// order-received page (the redirect handler verifies the live session)
	// and its total instead of the admin "Paid" row.
	await waitForOrderReceivedPage( page );
	await expect(
		page.locator( '.woocommerce-order-overview__total' )
	).toContainText( expectedTotal );
};

test.describe( 'Adaptive Pricing checkout', () => {
	test.describe.configure( { mode: 'serial' } );

	test( 'customer can pay through Adaptive Pricing on shortcode checkout @smoke', async ( {
		page,
	} ) => {
		// The "Paid by customer" presentment row is NOT asserted here: Stripe
		// only reports presentment details when a currency conversion actually
		// happened, which a same-currency CI shopper cannot produce. Its
		// rendering is covered by unit tests.
		await payWithAdaptivePricing( page, 'shortcode' );
	} );

	test( 'customer can pay through Adaptive Pricing on blocks checkout @smoke', async ( {
		page,
	} ) => {
		await payWithAdaptivePricing( page, 'blocks' );
	} );

	test( 'logged-in customer without a saved billing address can pay through Adaptive Pricing', async ( {
		page,
	} ) => {
		// A bare customer: no billing/shipping address saved on the account.
		const randomString = randomUUID();
		const username =
			randomString + '.' + config.get( 'users.customer.username' );
		// Empty billing/shipping: the helper maps their fields, and the
		// undefined values are dropped from the request, creating the
		// customer with no saved address.
		await api.create.customer( {
			...config.get( 'users.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username,
			billing: {},
			shipping: {},
		} );

		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);

		// Creating the checkout session for this buyer must not fail on the
		// missing address; the payment element renders and the purchase works.
		await payWithAdaptivePricing( page, 'shortcode' );
	} );

	test( 'manual capture blocks Adaptive Pricing in the settings with a clear reason', async ( {
		browser,
	} ) => {
		const { context, page } = await admin.getAdminPage( browser );

		const saveSettings = async () => {
			await page.click( 'text=Save changes' );
			await expect(
				page.locator(
					'.components-snackbar__content:has-text("Settings saved.")'
				)
			).toBeVisible();
		};

		try {
			await page.goto( SETTINGS_URL );

			// Enabling manual capture requires confirming a modal.
			await page.getByLabel( MANUAL_CAPTURE_LABEL ).click();
			await page
				.getByRole( 'button', { name: 'Enable', exact: true } )
				.click();
			await saveSettings();

			await page.goto( SETTINGS_URL );
			await expect( page.getByLabel( AP_CHECKBOX_LABEL ) ).toBeDisabled();
			await expect(
				page.locator( '.wc-stripe-adaptive-pricing-unavailable-reason' )
			).toContainText( 'Adaptive Pricing requires automatic capture' );
		} finally {
			// Restore automatic capture (unchecking shows no modal) and
			// confirm Adaptive Pricing becomes selectable again.
			await page.goto( SETTINGS_URL );
			const manualCapture = page.getByLabel( MANUAL_CAPTURE_LABEL );
			if ( await manualCapture.isChecked() ) {
				await manualCapture.click();
				await saveSettings();
			}

			await page.goto( SETTINGS_URL );
			await expect( page.getByLabel( AP_CHECKBOX_LABEL ) ).toBeEnabled();
			await context.close();
		}
	} );
} );
