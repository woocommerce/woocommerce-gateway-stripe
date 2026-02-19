import { expect, test as setup } from '@playwright/test';
import { admin } from '../utils';

setup(
	'Configure store for ISK express checkout tests',
	async ( { browser } ) => {
		await admin.updateStoreCurrency( browser, 'ISK' );

		const adminContext = await browser.newContext( {
			storageState: process.env.ADMINSTATE,
		} );
		const page = await adminContext.newPage();

		// Enable Link.
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
		);
		await page.getByLabel( 'Link by Stripe' ).check();
		await page.click( 'text=Save changes' );

		await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
		await expect( page.getByLabel( 'Link by Stripe' ) ).toBeChecked();

		await adminContext.close();
		await admin.initializeOptimizedCheckout( browser, false );
	}
);
