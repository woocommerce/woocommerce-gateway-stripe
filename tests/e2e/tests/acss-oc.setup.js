import { test as setup } from '@playwright/test';
import { admin } from '../utils';

setup(
	'Configure store for ACSS Optimized Checkout tests',
	async ( { browser } ) => {
		const adminContext = await browser.newContext( {
			storageState: process.env.ADMINSTATE,
		} );
		const page = await adminContext.newPage();

		// Change store currency to CAD.
		await admin.updateStoreCurrency( browser, 'CAD' );

		// Enable ACSS in the admin.
		await admin.togglePaymentMethod(
			browser,
			'Pre-Authorized Debit',
			true
		);

		// Enable the Optimized Checkout Suite. ACSS is a non-deferred-intent method,
		// so it renders as its own entry alongside the OC Payment Element rather than
		// inside it.
		await admin.initializeOptimizedCheckout( browser, true );

		await adminContext.close();
	}
);
