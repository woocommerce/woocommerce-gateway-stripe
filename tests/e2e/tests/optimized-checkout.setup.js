import { test as setup } from '@playwright/test';
import { initializeOptimizedCheckout } from '../utils/admin';

setup(
	'Configure store for Optimized Checkout tests',
	async ( { browser } ) => {
		await initializeOptimizedCheckout( browser, true );
	}
);
