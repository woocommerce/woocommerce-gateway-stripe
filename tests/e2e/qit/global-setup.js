import { chromium } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import Stripe from 'stripe';

import {
	loginAdminAndSaveState,
	createApiTokens,
} from '../utils/playwright-setup.js';
import {
	deleteStripeWebhooksByURL,
	getStripeWebhookURL,
} from '../utils/stripe-webhooks.js';

const ADMIN_USER =
	process.env.QIT_ADMIN_USERNAME || process.env.ADMIN_USER || 'admin';
const ADMIN_PASSWORD =
	process.env.QIT_ADMIN_PASSWORD || process.env.ADMIN_PASSWORD || 'password';
const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY;

export default async function globalSetup( config ) {
	console.time( 'Total Setup Time' );

	const { baseURL, userAgent } = config.projects[ 0 ].use;
	const stateDir = config.projects[ 0 ].use.stateDir || './results/storage/';

	const resolvedStateDir = path.resolve( stateDir );
	fs.mkdirSync( resolvedStateDir, { recursive: true } );

	process.env.ADMINSTATE = path.join( resolvedStateDir, 'adminState.json' );

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

	// Create a valid admin user session so we can (re)use the session for any admin UI actions during tests.
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

	const apiTokensPage = await adminContext.newPage();
	try {
		await createApiTokens( apiTokensPage );
	} catch ( err ) {
		console.error(
			'Could not create a WC REST API key. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	if ( STRIPE_SECRET_KEY ) {
		try {
			const stripeClient = new Stripe( STRIPE_SECRET_KEY );
			const webhookURL = getStripeWebhookURL( baseURL );

			// Clean up all previous webhooks for this URL to ensure we have a clean starting state.
			await deleteStripeWebhooksByURL( stripeClient, webhookURL );

			const webhookEndpoint = await stripeClient.webhookEndpoints.create(
				{
					url: webhookURL,
					enabled_events: [ '*' ],
					description: 'Webhook created for QIT E2E tests.',
				}
			);

			console.log( '\u2714 Created Stripe webhook successfully.' );

			const envId = process.env.QIT_ENV_ID;
			if ( envId ) {
				execSync(
					`qit env:exec "wp option patch update woocommerce_stripe_settings test_webhook_secret '${ webhookEndpoint.secret }'"`,
					{ stdio: 'inherit' }
				);
				console.log(
					'\u2714 Updated Stripe webhook secret successfully.'
				);
			} else {
				console.error(
					'QIT_ENV_ID not set — cannot update webhook secret via WP-CLI.'
				);
			}
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
