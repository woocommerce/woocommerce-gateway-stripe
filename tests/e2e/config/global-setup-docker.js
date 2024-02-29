import * as dotenv from 'dotenv';
import { chromium } from '@playwright/test';
import fs from 'fs';

import {
	loginAdminAndSaveState,
	createApiTokens,
} from '../utils/playwright-setup';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const {
	ADMIN_USER,
	ADMIN_PASSWORD,
	STRIPE_SETUP,
	STRIPE_PUB_KEY,
	STRIPE_SECRET_KEY,
} = process.env;

export default async function ( config ) {
	console.time( 'Total Setup Time' );
	const { baseURL, stateDir, userAgent } = config.projects[ 0 ].use;

	if ( STRIPE_SETUP && ( ! STRIPE_PUB_KEY || ! STRIPE_SECRET_KEY ) ) {
		console.error(
			'The Stripe setup needs that the STRIPE_PUB_KEY and the STRIPE_SECRET_KEY secrets are set in your local.env file.'
		);
		process.exit( 1 );
	}

	console.log( `BASE_URL = ${ baseURL }\n` );

	// used throughout tests for authentication
	process.env.ADMINSTATE = `${ stateDir }/adminState.json`;

	// Clear out the previous saved states
	try {
		fs.unlinkSync( process.env.ADMINSTATE );
	} catch ( err ) {
		if ( err.code !== 'ENOENT' ) {
			console.log( 'Admin state file could not be deleted: ' + err );
		}
	}

	let adminSetupReady = false;

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = { baseURL, userAgent };

	// Create browser, browserContext, and page for customer and admin users
	const browser = await chromium.launch();
	const adminContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage();

	try {
		await loginAdminAndSaveState( {
			page: adminPage,
			username: ADMIN_USER,
			password: ADMIN_PASSWORD,
			statePath: process.env.ADMINSTATE,
			retries: 1,
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

	await adminContext.close();
	await browser.close();

	console.timeEnd( 'Total Setup Time' );
	console.log( `\n======\n\n` );
}
