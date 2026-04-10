import { expect } from '@playwright/test';
import { update as apiUpdate } from './api';

/**
 * Get a new admin page with admin context.
 * @param {Browser} browser Playwright browser fixture.
 * @returns {Promise<{context: BrowserContext, page: Page}>} The admin context and page.
 */
export const getAdminPage = async ( browser ) => {
	const context = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await context.newPage();
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
 * When enabling, also moves Stripe to the first position among payment gateways,
 * since OCS requires Stripe to be the first available gateway.
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

	// OCS requires Stripe to be the first available payment gateway.
	// Ensure this is the case whenever enabling OCS.
	if ( shouldEnable ) {
		await apiUpdate.paymentGatewayOrder( 'stripe', 0 );
	}

	await adminContext.close();
};
