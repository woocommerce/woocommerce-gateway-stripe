import { test as setup } from '@playwright/test';
import { initializeAdaptivePricing } from '../utils/admin';

setup( 'Configure store for Adaptive Pricing tests', async ( { browser } ) => {
	await initializeAdaptivePricing( browser, true );
} );
