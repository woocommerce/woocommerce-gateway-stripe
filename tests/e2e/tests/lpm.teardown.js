import { test as teardown } from '@playwright/test';
import { execSync } from 'child_process';

teardown( 'Restore original Stripe keys', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// In QIT, keys are restored via WP-CLI in the run phase after Playwright finishes.
	// This teardown still runs as belt-and-suspenders.
	if ( ! process.env.QIT_SITE_URL ) {
		execSync(
			`WP_PATH="${ process.env.WP_PATH }" STRIPE_PUB_KEY="${ process.env.STRIPE_PUB_KEY }" STRIPE_SECRET_KEY="${ process.env.STRIPE_SECRET_KEY }" ./tests/e2e/bin/set-keys.sh`,
			{ stdio: 'inherit' }
		);
	}

	// Refresh account data in Stripe settings.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);
	await page.getByLabel( 'Edit details or disconnect' ).click();
	await page
		.getByRole( 'menuitem', { name: 'Refresh account details' } )
		.click();
} );
