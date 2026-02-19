import { test as teardown } from '@playwright/test';
import { admin } from '../utils';

teardown(
	'Restore store defaults after ISK express checkout tests',
	async ( { browser } ) => {
		await admin.updateStoreCurrency( browser, 'USD' );
		await admin.initializeOptimizedCheckout( browser, false );
	}
);
