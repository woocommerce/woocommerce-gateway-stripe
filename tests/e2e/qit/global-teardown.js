import { chromium } from '@playwright/test';
import Stripe from 'stripe';
import { user } from '../utils/index.js';
import {
	deleteStripeWebhooksByURL,
	getStripeWebhookURL,
} from '../utils/stripe-webhooks.js';

const ADMIN_USER =
	process.env.QIT_ADMIN_USERNAME || process.env.ADMIN_USER || 'admin';
const ADMIN_PASSWORD =
	process.env.QIT_ADMIN_PASSWORD || process.env.ADMIN_PASSWORD || 'password';
const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY;

export default async function globalTeardown( config ) {
	const { baseURL, userAgent } = config.projects[ 0 ].use;

	console.log( `\n======\n` );

	const contextOptions = { baseURL, userAgent };

	const browser = await chromium.launch();
	const context = await browser.newContext( contextOptions );
	const adminPage = await context.newPage();

	let consumerTokenCleared = false;

	await user.login( adminPage, ADMIN_USER, ADMIN_PASSWORD );

	// Clean up the consumer keys.
	const keysRetries = 5;
	for ( let i = 1; i <= keysRetries; i++ ) {
		try {
			console.log( '- Trying to clear consumer token... Try:' + i );

			await adminPage.goto(
				`/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys`
			);
			await adminPage.dispatchEvent( 'a.submitdelete', 'click' );
			console.log( '\u2714 Cleared up consumer token successfully.' );
			consumerTokenCleared = true;
			break;
		} catch ( e ) {
			console.error(
				`Failed to clear consumer token. Retrying... ${ i }/${ keysRetries }. Error:`,
				e
			);
		}
	}

	if ( ! consumerTokenCleared ) {
		console.error( 'Could not clear consumer token.' );
	}

	// Delete all Stripe webhooks pointed at this site's URL to ensure
	// we have a clean state after each test run.
	if ( STRIPE_SECRET_KEY ) {
		try {
			const stripeClient = new Stripe( STRIPE_SECRET_KEY );
			const deleted = await deleteStripeWebhooksByURL(
				stripeClient,
				getStripeWebhookURL( baseURL )
			);
			if ( deleted > 0 ) {
				console.log( '✔ Deleted Stripe webhook(s) successfully.' );
			}
		} catch ( e ) {
			console.error( 'Failed to delete Stripe webhook:', e );
		}
	}

	await context.close();
	await browser.close();

	console.log( `\n======\n` );
}
