require( 'dotenv' ).config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const { chromium } = require( '@playwright/test' );
const fs = require( 'fs' );

const {
	loginCustomerAndSaveState,
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
	CUSTOMER_USER,
	CUSTOMER_PASSWORD,
	CUSTOMERSTATE,
	PLUGIN_VERSION,
	WOO_SETUP,
	STRIPE_SETUP,
	SSH_HOST,
	SSH_USER,
	SSH_PASSWORD,
	SSH_PATH,
} = process.env;

const adminUsername = ADMIN_USER ?? 'admin';
const adminPassword = ADMIN_PASSWORD ?? 'password';
const customerUsername = CUSTOMER_USER ?? 'customer';
const customerPassword = CUSTOMER_PASSWORD ?? 'password';

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
	process.env.CUSTOMERSTATE = `${ stateDir }customerState.json`;

	// Clear out the previous save states
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
	try {
		fs.unlinkSync( process.env.CUSTOMERSTATE );
		console.log( 'Customer state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code === 'ENOENT' ) {
			console.log( 'Customer state file does not exist.' );
		} else {
			console.log( 'Customer state file could not be deleted: ' + err );
		}
	}

	// Setup WooCommerce before any browser interaction.
	if ( WOO_SETUP ) {
		if ( ! SSH_HOST || ! SSH_USER || ! SSH_PASSWORD ) {
			console.error( 'The --woo_setup flag needs SSH credentials!' );
			process.exit( 1 );
		}
		await setupWoo(
			{
				host: SSH_HOST,
				username: SSH_USER,
				password: SSH_PASSWORD,
			},
			SSH_PATH
		).catch( ( e ) => {
			console.error( e );
			console.error(
				'Cannot proceed e2e test, as we could not update the plugin. Please check if the test site has been setup correctly.'
			);
			process.exit( 1 );
		} );
	} else {
		console.log( 'Skipping Woo Setup.' );
	}

	let customerSetupReady = false;
	let adminSetupReady = false;

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = { baseURL, userAgent };

	// Create browser, browserContext, and page for customer and admin users
	const browser = await chromium.launch( { headless: false } );
	const adminContext = await browser.newContext( contextOptions );
	const customerContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage( { headless: false } );
	const customerPage = await customerContext.newPage();

	loginCustomerAndSaveState( {
		page: customerPage,
		username: customerUsername,
		password: customerPassword,
		statePath: CUSTOMERSTATE,
		retries: 5,
	} )
		.then( async () => {
			customerSetupReady = true;
		} )
		.catch( () => {
			console.error(
				'Cannot proceed e2e test, as customer login failed. Please check if the test site has been setup correctly.'
			);
			process.exit( 1 );
		} );

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
			let customerTokenFinished = false;
			let pluginUpdateFinished = false;
			let stripeSetupFinished = false;

			createApiTokens( apiTokensPage )
				.then( () => {
					customerTokenFinished = true;
				} )
				.catch( () => {
					console.error(
						'Cannot proceed e2e test, as we could not set the customer key. Please check if the test site has been setup correctly.'
					);
					process.exit( 1 );
				} );

			if ( PLUGIN_VERSION ) {
				installPluginFromRepository( updatePluginPage )
					.then( () => {
						pluginUpdateFinished = true;
					} )
					.catch( () => {
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
					console.log( '*** Waiting plugin install to be finished' );
					await wait( 1000 );
				}

				setupStripe( adminPage )
					.then( () => {
						stripeSetupFinished = true;
					} )
					.catch( () => {
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
				! customerTokenFinished ||
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

	while ( ! customerSetupReady || ! adminSetupReady ) {
		await wait( 1000 );
	}

	await adminContext.close();
	await customerContext.close();
	await browser.close();

	console.timeEnd( 'Total Setup Time' );
	console.log( `\n======\n\n` );
};
