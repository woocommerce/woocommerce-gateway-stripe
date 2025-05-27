import { test as setup } from '@playwright/test';
import { admin } from '../utils';

setup( 'Configure store for BECS tests', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// Change store currency to AUD.
	await admin.updateStoreCurrency( browser, 'AUD' );

	// Enable BECS in the admin.
	await admin.togglePaymentMethod( browser, 'BECS Direct Debit', true );

	await adminContext.close();
} );
