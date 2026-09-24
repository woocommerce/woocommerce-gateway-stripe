import { test as teardown } from '@playwright/test';
import {
	initializeAdaptivePricing,
	initializeOptimizedCheckout,
} from '../utils/admin';

teardown(
	'Restore store after Adaptive Pricing tests',
	async ( { browser } ) => {
		// AP first: its checkbox is only interactable while OC is still on.
		await initializeAdaptivePricing( browser, false );
		await initializeOptimizedCheckout( browser, false );
	}
);
