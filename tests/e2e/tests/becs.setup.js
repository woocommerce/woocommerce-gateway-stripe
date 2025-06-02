import { test as setup } from '@playwright/test';
import { admin } from '../utils';
import { execSync } from 'child_process';

const setKeysForLocalEnv = async ( page ) => {
	execSync(
		`WP_PATH="${ process.env.WP_PATH }" STRIPE_PUB_KEY="${ process.env.STRIPE_PUB_KEY_AU }" STRIPE_SECRET_KEY="${ process.env.STRIPE_SECRET_KEY_AU }" ./tests/e2e/bin/set-keys.sh`,
		{ stdio: 'inherit' }
	);

	// Refresh account data in Stripe settings.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);
	await page.getByLabel( 'Edit details or disconnect' ).click();
	await page
		.getByRole( 'menuitem', { name: 'Refresh account details' } )
		.click();
};

setup( 'Configure store for BECS tests', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	if ( ! process.env.CI ) {
		await setKeysForLocalEnv( page );
	}

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);

	// Change store currency to AUD.
	await admin.updateStoreCurrency( browser, 'AUD' );

	// Enable BECS in the admin.
	await admin.togglePaymentMethod( browser, 'BECS Direct Debit', true );

	await adminContext.close();
} );
