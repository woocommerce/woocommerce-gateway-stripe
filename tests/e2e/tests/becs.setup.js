import { test as setup } from '@playwright/test';
import { admin } from '../utils';
import { execSync } from 'child_process';

setup( 'Configure store for BECS tests', async ( { browser } ) => {
	// In QIT, keys are switched via WP-CLI in the run phase before Playwright starts.
	if ( ! process.env.QIT_SITE_URL && ! process.env.CI ) {
		execSync(
			`WP_PATH="${ process.env.WP_PATH }" STRIPE_PUB_KEY="${ process.env.STRIPE_PUB_KEY_AU }" STRIPE_SECRET_KEY="${ process.env.STRIPE_SECRET_KEY_AU }" ./tests/e2e/bin/set-keys.sh`,
			{ stdio: 'inherit' }
		);
	}

	await admin.refreshAccountDetails( browser );

	// Change store currency to AUD.
	await admin.updateStoreCurrency( browser, 'AUD' );

	// Enable BECS in the admin.
	await admin.togglePaymentMethod( browser, 'BECS Direct Debit', true );
} );
