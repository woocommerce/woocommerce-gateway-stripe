require( 'dotenv' ).config( {
	path: `${ process.env.E2E_ROOT }/config/local.env`,
} );

const { chromium, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );
const {
	downloadZip,
	deleteZip,
	getReleaseZipUrl,
} = require( '../utils/plugin-utils' );

const { loginCustomerAndSaveState } = require( '../utils/pw-setup' );

const {
	ADMIN_USER,
	ADMIN_PASSWORD,
	ADMINSTATE,
	CUSTOMER_USER,
	CUSTOMER_PASSWORD,
	CUSTOMERSTATE,
	GITHUB_TOKEN,
	PLUGIN_REPOSITORY,
	PLUGIN_VERSION,
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
	const { stateDir, baseURL, userAgent } = config.projects[ 0 ].use;

	console.log( `State Dir: ${ stateDir }` );
	console.log( `Base URL: ${ baseURL }` );

	console.log( process.env );

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

	// Pre-requisites
	let adminLoggedIn = false;
	let customerLoggedIn = false;
	let customerKeyConfigured = false;
	let customerSetupReady = false;
	let adminSetupReady = false;

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = { baseURL, userAgent };

	// Create browser, browserContext, and page for customer and admin users
	const browser = await chromium.launch( { headless: false } );
	const adminContext = await browser.newContext( contextOptions );
	const customerContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage();
	const customerPage = await customerContext.newPage();

	loginCustomerAndSaveState( {
		page: customerPage,
		username: customerUsername,
		password: customerPassword,
		statePath: CUSTOMERSTATE,
		retries: 5,
	} )
		.then( async () => {
			await customerContext.close();
			customerSetupReady = true;
		} )
		.catch( () => {
			console.error(
				'Cannot proceed e2e test, as customer login failed. Please check if the test site has been setup correctly.'
			);
			process.exit( 1 );
		} );

	// Sign in as admin user and save state
	const adminRetries = 5;
	for ( let i = 0; i < adminRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as admin...' );
			await adminPage.goto( `/wp-admin` );
			await adminPage.fill( 'input[name="log"]', adminUsername );
			await adminPage.fill( 'input[name="pwd"]', adminPassword );
			await adminPage.click( 'text=Log In' );
			await adminPage.waitForLoadState( 'networkidle' );

			await expect( adminPage.locator( 'div.wrap > h1' ) ).toHaveText(
				'Dashboard'
			);
			await adminPage
				.context()
				.storageState( { path: process.env.ADMINSTATE } );
			console.log( 'Logged-in as admin successfully.' );
			adminLoggedIn = true;
			break;
		} catch ( e ) {
			console.log(
				`Admin log-in failed, Retrying... ${ i }/${ adminRetries }`
			);
			console.log( e );
		}
	}

	if ( ! adminLoggedIn ) {
		console.error(
			'Cannot proceed e2e test, as admin login failed. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	// While we're here, let's add a consumer token for API access
	// This step was failing occasionally, and globalsetup doesn't retry, so make it retry
	const nRetries = 5;
	for ( let i = 0; i < nRetries; i++ ) {
		try {
			console.log( 'Trying to add consumer token...' );
			await adminPage.goto(
				`/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys&create-key=1`
			);
			await adminPage.fill( '#key_description', 'Key for API access' );
			await adminPage.selectOption( '#key_permissions', 'read_write' );
			await adminPage.click( 'text=Generate API key' );
			process.env.CONSUMER_KEY = await adminPage.inputValue(
				'#key_consumer_key'
			);
			process.env.CONSUMER_SECRET = await adminPage.inputValue(
				'#key_consumer_secret'
			);
			console.log( 'Added consumer token successfully.' );
			customerKeyConfigured = true;
			break;
		} catch ( e ) {
			console.log(
				`Failed to add consumer token. Retrying... ${ i }/${ nRetries }`
			);
			console.log( e );
		}
	}

	if ( ! customerKeyConfigured ) {
		console.error(
			'Cannot proceed e2e test, as we could not set the customer key. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	// Use Stripe version from the `--version` parameter
	let pluginZipPath;
	let pluginSlug = PLUGIN_REPOSITORY.split( '/' ).pop();

	// Get the download URL and filename of the plugin
	const pluginDownloadURL = await getReleaseZipUrl( PLUGIN_VERSION );
	const zipFilename = pluginDownloadURL.split( '/' ).pop();
	pluginZipPath = path.resolve( __dirname, `../../tmp/${ zipFilename }` );

	// Download the needed plugin.
	await downloadZip( {
		url: pluginDownloadURL,
		downloadPath: pluginZipPath,
		authToken: GITHUB_TOKEN,
	} );
	await adminPage.goto( 'wp-admin/plugin-install.php?tab=upload', {
		waitUntil: 'networkidle',
	} );

	await adminPage.setInputFiles( 'input#pluginzip', pluginZipPath );
	await adminPage.click( "input[type='submit'] >> text=Install Now" );

	await adminPage.click( 'text=Replace current with uploaded', {
		timeout: 10000,
	} );

	await expect( adminPage.locator( '#wpbody-content .wrap' ) ).toContainText(
		/Plugin (?:downgraded|updated) successfully/gi
	);

	await adminPage.goto( 'wp-admin/plugins.php', {
		waitUntil: 'networkidle',
	} );

	// Assert that the plugin is listed and active
	await expect(
		adminPage.locator( `#deactivate-${ pluginSlug }` )
	).toBeVisible();

	await adminContext.close();
	await browser.close();

	while ( ! customerSetupReady && ! adminSetupReady ) {
		await wait( 3000 );
	}
};
