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
	getOrderIdFromOrderReceivedUrl,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

const SETTINGS_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings';

const AP_CHECKBOX_LABEL =
	'Let customers pay in their local currency with Adaptive Pricing';
const MANUAL_CAPTURE_LABEL =
	'Issue an authorization on checkout, and capture later';

/**
 * Completes an Adaptive Pricing card purchase and returns the order ID.
 *
 * @param {Page}    page         Playwright page fixture.
 * @param {Browser} browser      Playwright browser fixture.
 * @param {string}  checkoutType 'shortcode' or 'blocks'.
 * @return {Promise<string>} The WooCommerce order ID.
 */
const payWithAdaptivePricing = async ( page, browser, checkoutType ) => {
	await setupOptimizedCheckout( page, checkoutType );
	await fillOCDetails( page, config.get( 'cards.basic' ), checkoutType );

	const expectedTotal = await getCartTotal( page );

	await clickPlaceOrder( page );

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);

	return getOrderIdFromOrderReceivedUrl( page.url() );
};

test.describe( 'Adaptive Pricing checkout', () => {
	test.describe.configure( { mode: 'serial' } );

	test( 'customer can pay through Adaptive Pricing on shortcode checkout, and the order records the presentment amount @smoke', async ( {
		page,
		browser,
	} ) => {
		const orderId = await payWithAdaptivePricing(
			page,
			browser,
			'shortcode'
		);

		// The order edit page shows the "Paid by customer" row with the
		// presentment amount reported by the checkout session.
		const { context, page: adminPage } =
			await admin.getAdminPage( browser );
		try {
			await adminPage.goto(
				`/wp-admin/post.php?post=${ orderId }&action=edit`
			);
			const paidByCustomerRow = adminPage
				.locator( '.wc-order-totals tr' )
				.filter( { hasText: 'Paid by customer' } );
			await expect( paidByCustomerRow ).toBeVisible();
			// Same-currency purchase: the symbol clashes with the store's, so
			// the currency code is spelled out.
			await expect( paidByCustomerRow ).toContainText( 'USD' );
		} finally {
			await context.close();
		}
	} );

	test( 'customer can pay through Adaptive Pricing on blocks checkout @smoke', async ( {
		page,
		browser,
	} ) => {
		await payWithAdaptivePricing( page, browser, 'blocks' );
	} );

	test( 'logged-in customer without a saved billing address can pay through Adaptive Pricing', async ( {
		page,
		browser,
	} ) => {
		// A bare customer: no billing/shipping address saved on the account.
		const randomString = randomUUID();
		const username =
			randomString + '.' + config.get( 'users.customer.username' );
		await api.create.customer( {
			...config.get( 'users.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username,
		} );

		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);

		// Creating the checkout session for this buyer must not fail on the
		// missing address; the payment element renders and the purchase works.
		await payWithAdaptivePricing( page, browser, 'shortcode' );
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
