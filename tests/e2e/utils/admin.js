import { expect } from '@playwright/test';

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

			// When disabling, we need to click the remove button
			if ( ! enable ) {
				await page.getByRole( 'button', { name: 'Remove' } ).click();
			}

			await page.click( 'text=Save changes' );
			await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
		}
	} finally {
		await context.close();
	}
};
