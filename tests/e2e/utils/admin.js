import fs from 'fs';
import os from 'os';
import path from 'path';
import { expect } from '@playwright/test';
import { user } from './index.js';

const ADMIN_USER =
	process.env.QIT_ADMIN_USERNAME || process.env.ADMIN_USER || 'admin';
const ADMIN_PASSWORD =
	process.env.QIT_ADMIN_PASSWORD || process.env.ADMIN_PASSWORD || 'password';

/**
 * Get a new admin page with admin context.
 * If in a QIT tunnel environment and the stored session is stale,
 * re-authenticates automatically.
 * @param {Browser} browser Playwright browser fixture.
 * @returns {Promise<{context: BrowserContext, page: Page}>} The admin context and page.
 */
export const getAdminPage = async ( browser ) => {
	const context = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await context.newPage();

	// In QIT tunnel environments, auth cookies can expire between tests.
	if ( process.env.QIT_SITE_URL ) {
		await page.goto( '/wp-admin/' );
		if ( page.url().includes( 'wp-login.php' ) ) {
			await user.login( page, ADMIN_USER, ADMIN_PASSWORD );
		}
	}

	return { context, page };
};

/**
 * Enable or disable a payment method in Stripe settings.
 * @param {Browser} browser Playwright browser fixture.
 * @param {string} methodName The payment method name as shown in admin.
 * @param {boolean} enable Whether to enable or disable the payment method.
 */
export const togglePaymentMethod = async (
	browser,
	methodName,
	enable = true
) => {
	// The settings page submits every method's enabled state, so two workers
	// toggling different methods at once lose one of the changes: the later
	// save overwrites the earlier one with the stale state it loaded. One
	// lost save disables a method (seen with Link) for the rest of the run.
	// Serialize the load-change-save sequence across worker processes.
	const releaseLock = await acquireSettingsLock();
	const { context, page } = await getAdminPage( browser );

	try {
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
		);

		const checkbox = page.getByRole( 'checkbox', {
			name: methodName,
		} );
		const isChecked = await checkbox.isChecked();

		if ( ( enable && ! isChecked ) || ( ! enable && isChecked ) ) {
			await checkbox.click();

			// When disabling, some methods show a Remove confirmation button.
			if ( ! enable ) {
				const removeButton = page.getByRole( 'button', {
					name: 'Remove',
				} );
				try {
					await removeButton.waitFor( {
						state: 'visible',
						timeout: 3000,
					} );
					await removeButton.click();
				} catch ( error ) {
					if ( error?.name !== 'TimeoutError' ) {
						throw error;
					}
					// Remove button is optional for some methods.
				}
			}

			await page.click( 'text=Save changes' );
			await expect(
				page.getByText( 'Settings saved.' ).first()
			).toBeVisible();
		}
	} finally {
		await context.close();
		releaseLock();
	}
};

const SETTINGS_LOCK_DIR = path.join(
	os.tmpdir(),
	`wc-stripe-e2e-settings-${ ( process.env.BASE_URL || 'local' ).replace(
		/[^a-z0-9]/gi,
		'_'
	) }.lock`
);
const SETTINGS_LOCK_STALE_MS = 90 * 1000;
const SETTINGS_LOCK_WAIT_MS = 120 * 1000;

/**
 * Cross-process mutex for Stripe settings writes. Playwright workers are
 * separate processes, so an in-memory lock cannot serialize them; an atomic
 * directory create on the shared filesystem can. A lock older than the stale
 * threshold is assumed to belong to a crashed worker and is reclaimed.
 *
 * @returns {Promise<Function>} Release function.
 */
const acquireSettingsLock = async () => {
	const deadline = Date.now() + SETTINGS_LOCK_WAIT_MS;
	for (;;) {
		try {
			fs.mkdirSync( SETTINGS_LOCK_DIR );
			return () => {
				try {
					fs.rmdirSync( SETTINGS_LOCK_DIR );
				} catch ( error ) {
					// Already reclaimed as stale by another worker.
				}
			};
		} catch ( error ) {
			if ( error.code !== 'EEXIST' ) {
				throw error;
			}
			try {
				const age =
					Date.now() - fs.statSync( SETTINGS_LOCK_DIR ).mtimeMs;
				if ( age > SETTINGS_LOCK_STALE_MS ) {
					fs.rmdirSync( SETTINGS_LOCK_DIR );
					continue;
				}
			} catch ( statError ) {
				// Released between the mkdir and the stat; retry immediately.
				continue;
			}
			if ( Date.now() > deadline ) {
				throw new Error(
					'Timed out waiting for the Stripe settings lock.'
				);
			}
			await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
		}
	}
};

/**
 * Update the store currency in WooCommerce settings.
 * @param {Browser} browser Playwright browser fixture.
 * @param {string} currency The currency to set.
 */
export const updateStoreCurrency = async ( browser, currency ) => {
	const { context, page } = await getAdminPage( browser );

	try {
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=general' );

		// Check if the store currency is already set to the desired currency.
		if (
			currency ===
			( await page.$eval( '#woocommerce_currency', ( el ) => el.value ) )
		) {
			return;
		}

		await page.selectOption( '#woocommerce_currency', { value: currency } );
		await page.click( 'text=Save changes' );
		await expect(
			page.getByText( 'Your settings have been saved.' )
		).toBeDefined();
	} finally {
		await context.close();
	}
};

/**
 * Enable or disable the Optimized Checkout feature in Stripe settings.
 *
 * @param {Browser} browser      Playwright browser fixture.
 * @param {boolean} shouldEnable Whether to enable or disable the Optimized Checkout element.
 */
export const initializeOptimizedCheckout = async (
	browser,
	shouldEnable = true
) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );

	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);

	const checkbox = page.getByTestId( 'optimized-checkout-element-checkbox' );
	const isChecked = await checkbox.isChecked();

	const updateNeeded =
		( shouldEnable && ! isChecked ) || ( ! shouldEnable && isChecked );

	if ( updateNeeded ) {
		await checkbox.click();
		await page.click( 'text=Save changes' );
		await expect(
			page.locator(
				'.components-snackbar__content:has-text("Settings saved.")'
			)
		).toBeVisible();

		if ( shouldEnable ) {
			await expect(
				page.getByTestId( 'optimized-checkout-element-checkbox' )
			).toBeChecked();
		} else {
			await expect(
				page.getByTestId( 'optimized-checkout-element-checkbox' )
			).not.toBeChecked();
		}
	}

	await adminContext.close();
};

/**
 * Enables or disables Adaptive Pricing in the Stripe settings.
 *
 * Enabling turns on the Optimized Checkout Suite first (AP requires it);
 * disabling leaves OC on.
 *
 * @param {Browser} browser      Playwright browser fixture.
 * @param {boolean} shouldEnable Whether to enable or disable Adaptive Pricing.
 */
export const initializeAdaptivePricing = async (
	browser,
	shouldEnable = true
) => {
	if ( shouldEnable ) {
		await initializeOptimizedCheckout( browser, true );
	}

	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );

	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);

	const checkbox = page.getByLabel(
		'Let customers pay in their local currency with Adaptive Pricing'
	);
	const isChecked = await checkbox.isChecked();

	const updateNeeded =
		( shouldEnable && ! isChecked ) || ( ! shouldEnable && isChecked );

	if ( updateNeeded ) {
		// Fail fast when an availability gate (webhooks, PMC, capture, OC)
		// is unmet, instead of timing out on the click.
		await expect( checkbox ).toBeEnabled();
		await checkbox.click();
		await page.click( 'text=Save changes' );
		await expect(
			page.locator(
				'.components-snackbar__content:has-text("Settings saved.")'
			)
		).toBeVisible();

		// A save that silently didn't take would fail every dependent test
		// deep in checkout; assert the resulting state here instead.
		if ( shouldEnable ) {
			await expect( checkbox ).toBeChecked();
		} else {
			await expect( checkbox ).not.toBeChecked();
		}
	}

	await adminContext.close();
};

/**
 * Open the admin order edit screen. Uses the legacy post edit URL, which
 * redirects to the new order screen when HPOS is enabled.
 *
 * @param {Page}   page    Playwright page already authenticated as admin.
 * @param {string} orderId The WooCommerce order ID.
 */
export const gotoOrderEditPage = async ( page, orderId ) => {
	await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );
};

/**
 * Locate a row in the order totals table by its label and return its amount cell.
 *
 * @param {Page}   page  Playwright page on the order edit screen.
 * @param {string} label The row label, e.g. "Order Total" or "Paid by customer".
 * @return {Locator} The amount cell for that row.
 */
export const getOrderTotalForLabel = ( page, label ) =>
	page
		.locator( '.wc-order-totals tr' )
		.filter( { hasText: label } )
		.locator( '.total' );

/**
 * Open the admin order edit page for an order and confirm the expected amount
 * was charged.
 *
 * Checks the following locations to ensure we are charging the expected amount:
 *  - The "Order Total" row
 *  - The "Paid" (captured) row of the order details
 *  - The sum of the "Stripe Fee" and "Stripe Payout" rows
 *
 * @param {Browser} browser       Playwright browser fixture.
 * @param {string}  orderId       The WooCommerce order ID.
 * @param {string}  expectedTotal The expected charged amount, without currency
 *                                symbol (e.g. "19.99").
 */
export const verifyOrderChargedAmount = async (
	browser,
	orderId,
	expectedTotal
) => {
	const { context, page } = await getAdminPage( browser );

	try {
		await gotoOrderEditPage( page, orderId );

		const totalElementForLabel = ( label ) =>
			getOrderTotalForLabel( page, label );

		// The order is recorded for the expected total...
		await expect( totalElementForLabel( 'Order Total' ) ).toContainText(
			expectedTotal
		);
		// ...and that total was actually captured (the "Paid" line).
		await expect( totalElementForLabel( 'Paid' ) ).toContainText(
			expectedTotal
		);

		// Also verify that the Stripe Fee and Stripe Payout rows are present and add up to the expected amount.
		const stripeFeeElement = totalElementForLabel( 'Stripe Fee' );
		const stripePayoutElement = totalElementForLabel( 'Stripe Payout' );

		await expect( stripeFeeElement ).toContainText( '-' );
		await expect( stripePayoutElement ).not.toBeEmpty();

		const stripeFeeText = await stripeFeeElement.textContent();
		const stripePayoutText = await stripePayoutElement.textContent();

		// Extract the numeric amount from the text content - ignore - and currency symbol.
		const stripeFeeAmount = parseFloat(
			stripeFeeText.replace( /[^0-9\.]/g, '' )
		);
		const stripePayoutAmount = parseFloat(
			stripePayoutText.replace( /[^0-9\.]/g, '' )
		);

		const totalAmount = stripeFeeAmount + stripePayoutAmount;

		expect( totalAmount ).toBeCloseTo( parseFloat( expectedTotal ), 2 );
	} finally {
		await context.close();
	}
};
