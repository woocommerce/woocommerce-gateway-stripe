/**
 * Adaptive Pricing flows. A same-currency shopper still checks out through
 * the checkout-sessions path (Stripe just presents the store currency), so
 * these exercise the real session code end-to-end.
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
	getOrderIdFromOrderReceivedUrl,
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

	// AP orders are only marked paid by webhooks, which can't reach the
	// tunnel-less CI site — assert the received page, not the "Paid" row.
	await waitForOrderReceivedPage( page );
	await expect(
		page.locator( '.woocommerce-order-overview__total' )
	).toContainText( expectedTotal );
};

/**
 * Completes a converted-currency Adaptive Pricing purchase as a simulated
 * French shopper and asserts the conversion is reflected on the checkout, the
 * order received page and the admin order screen.
 *
 * @param {Page}    page         Playwright page fixture.
 * @param {Object}  context      Playwright browser context, for the location cookie.
 * @param {string}  baseURL      Site base URL, for cookie scoping.
 * @param {Browser} browser      Playwright browser fixture, for the admin page.
 * @param {string}  checkoutType 'shortcode' or 'blocks'.
 */
const payWithConvertedCurrency = async (
	page,
	context,
	baseURL,
	browser,
	checkoutType
) => {
	// The e2e mu-plugin turns this cookie into Stripe's documented
	// "+location_XX" customer_email test hook on the session request.
	await context.addCookies( [
		{ name: 'wc_stripe_e2e_location', value: 'FR', url: baseURL },
	] );

	await setupOptimizedCheckout( page, checkoutType, {
		timeout: 10000,
		skipCartSetup: false,
		cardSelectionOptional: true,
	} );

	// The currency selector only offers a choice when Stripe converts
	// the presentment currency, so EUR here proves the conversion ran.
	// Classic exposes it by class, blocks by test id.
	const currencySelector = page.locator(
		'.wc-stripe-currency-selector, [data-testid="wc-stripe-currency-selector"]'
	);
	await expect( currencySelector ).toBeVisible( { timeout: 20000 } );
	await expect(
		currencySelector
			.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
			.locator( 'body' )
	).toContainText( /EUR|€/, { timeout: 20000 } );

	await fillOCDetails( page, config.get( 'cards.basic' ), checkoutType );
	await clickPlaceOrder( page );
	await waitForOrderReceivedPage( page );

	// Both notices below read presentment meta populated by a lazy
	// checkout-session fetch when the page renders, so they work
	// without the settlement webhook CI can't receive.
	await expect(
		// Classic themes render the notice as `.woocommerce-info`, block themes
		// as a `wc-block-components-notice-banner`.
		page.locator( '.woocommerce-info, .wc-block-components-notice-banner', {
			hasText: 'Currency Conversion:',
		} )
	).toContainText( 'EUR' );

	const orderId = getOrderIdFromOrderReceivedUrl( page.url() );
	const { context: adminContext, page: adminPage } =
		await admin.getAdminPage( browser );
	try {
		await admin.gotoOrderEditPage( adminPage, orderId );
		await expect(
			admin.getOrderTotalForLabel( adminPage, 'Paid by customer' )
		).toContainText( '€' );
	} finally {
		await adminContext.close();
	}
};

test.describe( 'Adaptive Pricing checkout', () => {
	test.describe.configure( { mode: 'serial' } );

	test( 'customer can pay through Adaptive Pricing on shortcode checkout @smoke', async ( {
		page,
	} ) => {
		// No "Paid by customer" assertion: Stripe only reports presentment
		// details on real conversions, impossible for a same-currency CI
		// shopper. Unit tests cover the row.
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
		// Empty addresses: the helper's mapped fields come out undefined and
		// are dropped, creating the customer with no saved address.
		const randomString = randomUUID();
		const username =
			randomString + '.' + config.get( 'users.customer.username' );
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

		// Session creation must not fail on the missing address.
		await payWithAdaptivePricing( page, 'shortcode' );
	} );

	test( 'guest sees converted prices as a simulated French shopper in classic checkout', async ( {
		page,
		context,
		baseURL,
		browser,
	} ) => {
		await payWithConvertedCurrency(
			page,
			context,
			baseURL,
			browser,
			'shortcode'
		);
	} );

	test( 'guest sees converted prices as a simulated French shopper on blocks checkout', async ( {
		page,
		context,
		baseURL,
		browser,
	} ) => {
		await payWithConvertedCurrency(
			page,
			context,
			baseURL,
			browser,
			'blocks'
		);
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
			// Restore automatic capture (no modal on uncheck).
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
