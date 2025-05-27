import { test as setup, expect } from '@playwright/test';

setup(
	'Configure store for Optimized Checkout tests',
	async ( { browser } ) => {
		const adminContext = await browser.newContext( {
			storageState: process.env.ADMINSTATE,
		} );

		const page = await adminContext.newPage();

		// Enable SPE in the admin.
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
		);

		const checkbox = page.getByTestId( 'single-payment-element-checkbox' );
		const isChecked = await checkbox.isChecked();

		if ( ! isChecked ) {
			await checkbox.click();
			await page.click( 'text=Save changes' );
			await expect(
				page.locator(
					'.components-snackbar__content:has-text("Settings saved.")'
				)
			).toBeVisible();
			await expect(
				page.getByTestId( 'single-payment-element-checkbox' )
			).toBeChecked();
		}

		await adminContext.close();
	}
);
