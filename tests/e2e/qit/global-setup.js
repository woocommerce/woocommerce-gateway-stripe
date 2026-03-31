import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import Stripe from 'stripe';

import {
	loginAdminAndSaveState,
	createApiTokens,
} from '../utils/playwright-setup.js';

const ADMIN_USER =
	process.env.QIT_ADMIN_USERNAME || process.env.ADMIN_USER || 'admin';
const ADMIN_PASSWORD =
	process.env.QIT_ADMIN_PASSWORD || process.env.ADMIN_PASSWORD || 'password';
const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY;

/**
 * Update the Stripe webhook secret in WordPress via a custom REST endpoint
 * exposed by the qit-option-api mu-plugin (installed during globalSetup).
 *
 * @param {import('@playwright/test').Page} page An admin-authenticated page.
 * @param {string} webhookSecret The Stripe webhook signing secret.
 */
async function updateWebhookSecretViaAdmin( page, webhookSecret ) {
	// Navigate to wp-admin so wpApiSettings.nonce is available.
	await page.goto( '/wp-admin/' );

	const result = await page.evaluate( async ( secret ) => {
		/* global wpApiSettings */
		const nonce = wpApiSettings?.nonce;
		if ( ! nonce ) {
			return { success: false, error: 'wpApiSettings.nonce not found' };
		}

		// Fetch current Stripe settings via the QIT option REST endpoint.
		const getRes = await fetch(
			'/wp-json/qit/v1/option/woocommerce_stripe_settings',
			{ headers: { 'X-WP-Nonce': nonce } }
		);

		if ( ! getRes.ok ) {
			return {
				success: false,
				error: `GET option failed: ${ getRes.status }`,
			};
		}

		const settings = await getRes.json();
		settings.test_webhook_secret = secret;

		const putRes = await fetch(
			'/wp-json/qit/v1/option/woocommerce_stripe_settings',
			{
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( settings ),
			}
		);

		return { success: putRes.ok, status: putRes.status };
	}, webhookSecret );

	if ( ! result.success ) {
		throw new Error(
			`Failed to update webhook secret via WP REST API: ${
				result.error || result.status
			}`
		);
	}
}

export default async function globalSetup( config ) {
	console.time( 'Total Setup Time' );

	const { baseURL, userAgent } = config.projects[ 0 ].use;
	const stateDir = config.projects[ 0 ].use.stateDir || './results/storage/';

	// Ensure the storage directory exists.
	const resolvedStateDir = path.resolve( stateDir );
	fs.mkdirSync( resolvedStateDir, { recursive: true } );

	// Used throughout tests for authentication.
	process.env.ADMINSTATE = path.join( resolvedStateDir, 'adminState.json' );

	// Clear out the previous saved state.
	try {
		fs.unlinkSync( process.env.ADMINSTATE );
		console.log( 'Admin state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code !== 'ENOENT' ) {
			console.log( 'Admin state file could not be deleted: ' + err );
		}
	}

	console.log( `Base URL: ${ baseURL }` );

	const contextOptions = { baseURL, userAgent };

	const browser = await chromium.launch();
	const adminContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage();

	// 1. Login as admin and save session state.
	try {
		await loginAdminAndSaveState( {
			page: adminPage,
			username: ADMIN_USER,
			password: ADMIN_PASSWORD,
			statePath: process.env.ADMINSTATE,
			retries: 5,
		} );
	} catch ( err ) {
		console.error( err );
		console.error(
			'Admin login failed. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	// 2. Create WC REST API tokens via browser automation.
	const apiTokensPage = await adminContext.newPage();
	try {
		await createApiTokens( apiTokensPage );
	} catch ( err ) {
		console.error(
			'Could not create a WC REST API key. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	// 3. Create Stripe webhook and update the webhook secret in WordPress.
	if ( STRIPE_SECRET_KEY ) {
		try {
			const stripeClient = new Stripe( STRIPE_SECRET_KEY );
			const webhookURL = `${ baseURL }?wc-api=wc_stripe`;

			// Clean up previous webhooks for this URL.
			const existingWebhooks = await stripeClient.webhookEndpoints.list();
			const matching = existingWebhooks.data.filter(
				( w ) => w.url === webhookURL
			);
			for ( const webhook of matching ) {
				await stripeClient.webhookEndpoints.del( webhook.id );
			}

			// Create a new webhook.
			const webhookEndpoint = await stripeClient.webhookEndpoints.create(
				{
					url: webhookURL,
					enabled_events: [ '*' ],
					description: 'Webhook created for QIT E2E tests.',
				}
			);

			console.log( '\u2714 Created Stripe webhook successfully.' );

			// Update the webhook secret in WordPress via the admin page.
			await updateWebhookSecretViaAdmin(
				adminPage,
				webhookEndpoint.secret
			);

			console.log( '\u2714 Updated Stripe webhook secret successfully.' );
		} catch ( e ) {
			console.error( 'Failed to setup Stripe webhook:', e );
			console.error(
				'Tests requiring webhooks may fail. Continuing anyway...'
			);
		}
	} else {
		console.log(
			'Skipping Stripe webhook setup (STRIPE_SECRET_KEY not set).'
		);
	}

	await adminContext.close();
	await browser.close();

	console.timeEnd( 'Total Setup Time' );
	console.log( `\n======\n\n` );
}
