import { test as setup } from '@playwright/test';
import {
	expectPaymentMethodEnabled,
	initializeOptimizedCheckout,
	refreshAccountDetails,
} from '../utils/admin.js';

// The site is pointed at the Link-off account before Playwright starts: CI
// prepares it with that account's secrets, QIT uses its own env file, and
// run-tests.sh switches the keys for a local Docker run.
setup(
	'Configure store for Optimized Checkout tests with Link off',
	async ( { browser } ) => {
		await refreshAccountDetails( browser );
		await initializeOptimizedCheckout( browser, true );
		// The account, not a toggle in the specs, is what keeps Link off.
		await expectPaymentMethodEnabled( browser, 'Link by Stripe', false );
	}
);
