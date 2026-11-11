import { test as teardown } from '@playwright/test';
import { execSync } from 'child_process';
import { admin } from '../utils';

teardown( 'Restore original Stripe keys', async ( { browser } ) => {
	// In QIT, keys are restored via WP-CLI in the run phase after Playwright finishes.
	// This teardown still runs as belt-and-suspenders.
	if ( ! process.env.QIT_SITE_URL ) {
		execSync(
			`WP_PATH="${ process.env.WP_PATH }" STRIPE_PUB_KEY="${ process.env.STRIPE_PUB_KEY }" STRIPE_SECRET_KEY="${ process.env.STRIPE_SECRET_KEY }" ./tests/e2e/bin/set-keys.sh`,
			{ stdio: 'inherit' }
		);
	}

	await admin.refreshAccountDetails( browser );
} );
