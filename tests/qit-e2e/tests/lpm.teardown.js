import { test as teardown } from '@playwright/test';
import { updateStripeKeys } from '../utils/stripe-keys.js';

teardown( 'Restore original Stripe keys', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	// Restore the original Stripe keys via WP REST API.
	await updateStripeKeys(
		page,
		process.env.STRIPE_PUB_KEY,
		process.env.STRIPE_SECRET_KEY
	);

	// Refresh account data in Stripe settings.
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);
	await page.getByLabel( 'Edit details or disconnect' ).click();
	await page
		.getByRole( 'menuitem', { name: 'Refresh account details' } )
		.click();

	await adminContext.close();
} );
