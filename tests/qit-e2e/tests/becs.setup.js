import { test as setup } from '@playwright/test';
import { admin } from '../utils';
import { updateStripeKeys } from '../utils/stripe-keys.js';

setup( 'Configure store for BECS tests', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// Update Stripe keys to the AU account via WP REST API.
	if ( process.env.STRIPE_PUB_KEY_AU && process.env.STRIPE_SECRET_KEY_AU ) {
		await updateStripeKeys(
			page,
			process.env.STRIPE_PUB_KEY_AU,
			process.env.STRIPE_SECRET_KEY_AU
		);

		// Refresh account data in Stripe settings.
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
		);
		await page.getByLabel( 'Edit details or disconnect' ).click();
		await page
			.getByRole( 'menuitem', { name: 'Refresh account details' } )
			.click();
	}

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);

	// Change store currency to AUD.
	await admin.updateStoreCurrency( browser, 'AUD' );

	// Enable BECS in the admin.
	await admin.togglePaymentMethod( browser, 'BECS Direct Debit', true );

	await adminContext.close();
} );
