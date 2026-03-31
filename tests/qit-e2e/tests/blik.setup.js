import { test as setup } from '@playwright/test';
import { admin } from '../utils';
import { updateStripeKeys } from '../utils/stripe-keys.js';

setup( 'Configure store for BLIK tests', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );

	const page = await adminContext.newPage();

	// Update Stripe keys to the PL account via WP REST API.
	if ( process.env.STRIPE_PUB_KEY_PL && process.env.STRIPE_SECRET_KEY_PL ) {
		await updateStripeKeys(
			page,
			process.env.STRIPE_PUB_KEY_PL,
			process.env.STRIPE_SECRET_KEY_PL
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

	// Change store currency to PLN.
	await admin.updateStoreCurrency( browser, 'PLN' );

	// Enable BLIK in the admin.
	await admin.togglePaymentMethod( browser, 'BLIK', true );

	await adminContext.close();
} );
