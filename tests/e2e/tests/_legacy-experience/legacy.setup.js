'use strict';

/* jshint node: true */

import { expect, test as setup } from '@playwright/test';

setup( 'Enable legacy checkout experience', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);
	await page.check( 'text=Enable the legacy checkout experience' );
	await page.click( 'text=Save changes' );

	await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
	await expect(
		page.getByTestId( 'legacy-checkout-experience-checkbox' )
	).toBeChecked();
} );
