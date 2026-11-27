import { test as setup } from '@playwright/test';
import {
	expectPaymentMethodEnabled,
	initializeOptimizedCheckout,
	togglePaymentMethod,
} from '../utils/admin.js';

setup(
	'Configure store for Optimized Checkout tests',
	async ( { browser } ) => {
		await initializeOptimizedCheckout( browser, true );
		// The consent-hiding test needs Link on.
		await expectPaymentMethodEnabled( browser, 'Link by Stripe', true );
		// The SCA scenario needs a wallet in the PMC to exercise card/wallet routing.
		await togglePaymentMethod( browser, 'Cash App Pay', true );
		await expectPaymentMethodEnabled( browser, 'Cash App Pay', true );
	}
);
