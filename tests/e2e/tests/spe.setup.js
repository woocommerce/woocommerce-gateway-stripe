import { test as setup } from '@playwright/test';
import { admin } from '../utils';

setup( 'Configure store for SPE tests', async ( { browser } ) => {
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
		await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
		await expect(
			page.getByTestId( 'single-payment-element-checkbox' )
		).toBeChecked();
	}

	await adminContext.close();
} );
