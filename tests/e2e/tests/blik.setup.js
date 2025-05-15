import { test as setup } from '@playwright/test';
import { admin } from '../utils';
import { execSync } from 'child_process';

setup( 'Configure store for BLIK tests', async ( { browser } ) => {
	execSync(
		`WP_PATH="${ process.env.WP_PATH }" STRIPE_PUB_KEY="${ process.env.STRIPE_PUB_KEY_PL }" STRIPE_SECRET_KEY="${ process.env.STRIPE_SECRET_KEY_PL }" ./tests/e2e/bin/set-keys.sh`,
		{ stdio: 'inherit' }
	);

	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// Refresh account data in Stripe settings.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);
	await page.getByLabel( 'Edit details or disconnect' ).click();
	await page
		.getByRole( 'menuitem', { name: 'Refresh account details' } )
		.click();

	// Change store currency to PLN.
	await admin.updateStoreCurrency( browser, 'PLN' );

	// Enable BLIK in the admin.
	await admin.togglePaymentMethod( browser, 'BLIK', true );

	await adminContext.close();
} );
