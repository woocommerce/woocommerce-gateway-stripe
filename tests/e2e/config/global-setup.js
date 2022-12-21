require( 'dotenv' ).config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const { chromium } = require( '@playwright/test' );
const fs = require( 'fs' );

const {
	loginAdminAndSaveState,
	createApiTokens,
	installPluginFromRepository,
	setupWoo,
	setupStripe,
} = require( '../utils/pw-setup' );

const {
	ADMIN_USER,
	ADMIN_PASSWORD,
	ADMINSTATE,
	PLUGIN_VERSION,
	WOO_SETUP,
	STRIPE_SETUP,
} = process.env;

const adminUsername = ADMIN_USER ?? 'admin';
const adminPassword = ADMIN_PASSWORD ?? 'password';

function wait( milliseconds ) {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, milliseconds );
	} );
}

module.exports = async ( config ) => {
	console.time( 'Total Setup Time' );
	const { stateDir, baseURL, userAgent } = config.projects[ 0 ].use;

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
		username: adminUsername,
		password: adminPassword,
		statePath: ADMINSTATE,
		retries: 5,
	} )
		.then( async () => {
			const apiTokensPage = await adminContext.newPage();
			const updatePluginPage = await adminContext.newPage();

			// create consumer token and update plugin in parallel.
			let restApiKeysFinished = false;
			let pluginUpdateFinished = false;
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
				! stripeSetupFinished
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
