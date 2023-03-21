import * as dotenv from 'dotenv';
import { chromium } from '@playwright/test';
import fs from 'fs';

import {
	loginAdminAndSaveState,
	createApiTokens,
	installPluginFromRepository,
	setupWoo,
	setupStripe,
	installWooSubscriptionsFromRepo,
} from '../utils/playwright-setup';

dotenv.config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const {
	BASE_URL,
	ADMIN_USER,
	ADMIN_PASSWORD,
	PLUGIN_VERSION,
	WOO_SETUP,
	STRIPE_SETUP,
	STRIPE_PUB_KEY,
	STRIPE_SECRET_KEY,
	SSH_HOST,
	SSH_USER,
	SSH_PASSWORD,
	SSH_PATH,
	GITHUB_TOKEN,
} = process.env;

function wait( milliseconds ) {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, milliseconds );
	} );
}

module.exports = async ( config ) => {
	console.time( 'Total Setup Time' );
	const { stateDir, baseURL, userAgent } = config.projects[ 0 ].use;

	// Validate env variables are present.
	if ( ! BASE_URL ) {
		console.error( 'The --base_url parameter is mandatory.' );
		process.exit( 1 );
	}

	if ( ! ADMIN_USER || ! ADMIN_PASSWORD ) {
		console.error(
			'Cannot proceed e2e test, ADMIN_USER and ADMIN_PASSWORD secrets are not set. Please check your local.env file.'
		);
		process.exit( 1 );
	}

	if (
		WOO_SETUP &&
		( ! SSH_HOST || ! SSH_USER || ! SSH_PASSWORD || ! SSH_PATH )
	) {
		console.error(
			'The WooCommerce setup needs SSH credentials (SSH_HOST, SSH_USER, SSH_PASSWORD, SSH_PATH) in your local.env file.'
		);
		process.exit( 1 );
	}

	if ( STRIPE_SETUP && ( ! STRIPE_PUB_KEY || ! STRIPE_SECRET_KEY ) ) {
		console.error(
			'The Stripe setup needs that the STRIPE_PUB_KEY and the STRIPE_SECRET_KEY secrets are set in your local.env file.'
		);
		process.exit( 1 );
	}

	console.log( `Base URL: ${ baseURL }` );
	if ( PLUGIN_VERSION ) {
		console.log( `Plugin Version: ${ PLUGIN_VERSION }` );
	}
	console.log( `\n======\n` );

	// used throughout tests for authentication
	process.env.ADMINSTATE = `${ stateDir }adminState.json`;

	// Clear out the previous saved states
	try {
		fs.unlinkSync( process.env.ADMINSTATE );
		console.log( 'Admin state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code === 'ENOENT' ) {
			console.log( 'Admin state file does not exist.' );
		} else {
			console.log( 'Admin state file could not be deleted: ' + err );
		}
	}

	// Setup WooCommerce before any browser interaction.
	if ( WOO_SETUP ) {
		await setupWoo().catch( ( e ) => {
			console.error( e );
			console.error(
				'Cannot proceed e2e test, as we could not update the plugin. Please check if the test site has been setup correctly.'
			);
			process.exit( 1 );
		} );
	} else {
		console.log( 'Skipping Woo Setup.' );
	}

	let adminSetupReady = false;

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = { baseURL, userAgent };

	// Create browser, browserContext, and page for customer and admin users
	const browser = await chromium.launch();
	const adminContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage();

	loginAdminAndSaveState( {
		page: adminPage,
		username: ADMIN_USER,
		password: ADMIN_PASSWORD,
		statePath: process.env.ADMINSTATE,
		retries: 5,
	} )
		.then( async () => {
			const apiTokensPage = await adminContext.newPage();
			const updatePluginPage = await adminContext.newPage();
			const wooSubscriptionsInstallPage = await adminContext.newPage();

			// create consumer token and update plugin in parallel.
			let restApiKeysFinished = false;
			let pluginUpdateFinished = false;
			let wooSubscriptionsInstallFinished = false;
			let stripeSetupFinished = false;

			createApiTokens( apiTokensPage )
				.then( () => {
					restApiKeysFinished = true;
				} )
				.catch( () => {
					console.error(
						'Cannot proceed e2e test, as we could not create a WC REST API key. Please check if the test site has been setup correctly.'
					);
					process.exit( 1 );
				} );

			if ( PLUGIN_VERSION ) {
				installPluginFromRepository( updatePluginPage )
					.then( () => {
						pluginUpdateFinished = true;
					} )
					.catch( ( e ) => {
						console.error( e );
						console.error(
							'Cannot proceed e2e test, as we could not update the plugin. Please check if the test site has been setup correctly.'
						);
						process.exit( 1 );
					} );
			} else {
				console.log(
					'Skipping plugin update. The version already installed on the test site will be used.'
				);
				pluginUpdateFinished = true;
			}

			if ( WOO_SETUP && GITHUB_TOKEN ) {
				installWooSubscriptionsFromRepo( wooSubscriptionsInstallPage )
					.then( () => {
						wooSubscriptionsInstallFinished = true;
					} )
					.catch( ( e ) => {
						console.error( e );
						console.error(
							'Cannot proceed e2e test, as we could not install WooCommerce Subscriptions. Please check if the GITHUB_TOKEN env variable is valid.'
						);
						process.exit( 1 );
					} );
			} else {
				console.log(
					'Skipping WC Subscriptions setup. The version already installed on the test site will be used if needed.'
				);
				wooSubscriptionsInstallFinished = true;
			}

			if ( STRIPE_SETUP ) {
				while ( PLUGIN_VERSION && ! pluginUpdateFinished ) {
					await wait( 1000 );
				}

				setupStripe( adminPage, baseURL )
					.then( () => {
						stripeSetupFinished = true;
					} )
					.catch( ( e ) => {
						console.error( e );
						console.error(
							'Cannot proceed e2e test, as we could not setup Stripe keys in the plugin. Please check if the test site has been setup correctly.'
						);
						process.exit( 1 );
					} );
			} else {
				console.log(
					'Skipping Stripe setup. Ensure Stripe webhook and keys are already setup in this environment.'
				);
				stripeSetupFinished = true;
			}

			while (
				! pluginUpdateFinished ||
				! restApiKeysFinished ||
				! stripeSetupFinished ||
				! wooSubscriptionsInstallFinished
			) {
				await wait( 1000 );
			}

			adminSetupReady = true;
		} )
		.catch( ( e ) => {
			console.error( e );
			console.error(
				'Cannot proceed e2e test, as admin login failed. Please check if the test site has been setup correctly.'
			);
			process.exit( 1 );
		} );

	while ( ! adminSetupReady ) {
		await wait( 1000 );
	}

	await adminContext.close();
	await browser.close();

	console.timeEnd( 'Total Setup Time' );
	console.log( `\n======\n\n` );
};
