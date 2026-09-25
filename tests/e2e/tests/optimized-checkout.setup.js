import { test as setup } from '@playwright/test';
import { initializeOptimizedCheckout } from '../utils/admin.js';

setup(
	'Configure store for Optimized Checkout tests',
	async ( { browser } ) => {
		await initializeOptimizedCheckout( browser, true );
	}
);
