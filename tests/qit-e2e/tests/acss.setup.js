import { test as setup } from '@playwright/test';
import { admin } from '../utils';

setup( 'Configure store for ACSS tests', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// Change store currency to CAD.
	await admin.updateStoreCurrency( browser, 'CAD' );

	// Enable ACSS in the admin.
	await admin.togglePaymentMethod( browser, 'Pre-Authorized Debit', true );

	await adminContext.close();
} );
