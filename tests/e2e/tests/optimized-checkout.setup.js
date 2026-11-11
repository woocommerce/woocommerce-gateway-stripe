import { test as setup } from '@playwright/test';
import {
	expectPaymentMethodEnabled,
	initializeOptimizedCheckout,
} from '../utils/admin.js';

setup(
	'Configure store for Optimized Checkout tests',
	async ( { browser } ) => {
		await initializeOptimizedCheckout( browser, true );
		// The consent-hiding test needs Link on; the save-and-reuse tests that
		// need it off run in the optimized-checkout-link-off project.
		await expectPaymentMethodEnabled( browser, 'Link by Stripe', true );
	}
);
