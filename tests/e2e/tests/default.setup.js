'use strict';

import { admin } from '../utils';
/* jshint node: true */
import { expect, test as setup } from '@playwright/test';

setup( 'Configure store for default tests', async ( { browser } ) => {
	// Set store currency to USD
	await admin.updateStoreCurrency( browser, 'USD' );

	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// Disable legacy checkout experience.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);
	await page.uncheck( 'text=Enable the legacy checkout experience' );
	await page.click( 'text=Save changes' );

	await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
	await expect(
		page.getByTestId( 'legacy-checkout-experience-checkbox' )
	).not.toBeChecked();

	// Enable Link.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
	);
	await page.getByLabel( 'Link by Stripe Input' ).check();
	await page.click( 'text=Save changes' );

	await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
	await expect( page.getByLabel( 'Link by Stripe Input' ) ).toBeChecked();

	await adminContext.close();
} );
