const { expect } = require( '@playwright/test' );

const path = require( 'path' );
const { downloadZip, getReleaseZipUrl } = require( '../utils/plugin-utils' );

const { GITHUB_TOKEN, PLUGIN_REPOSITORY, PLUGIN_VERSION } = process.env;

/**
 * Helper function to login a WP user and save the state on a given path.
 */
export const loginCustomerAndSaveState = ( {
	page,
	username,
	password,
	statePath,
	retries,
} ) =>
	new Promise( ( resolve, reject ) => {
		( async () => {
			// Sign in as customer user and save state
			for ( let i = 0; i < retries; i++ ) {
				try {
					console.log( '- Trying to log-in as customer...' );
					await page.goto( `/wp-admin` );
					await page.fill( 'input[name="log"]', username );
					await page.fill( 'input[name="pwd"]', password );
					await page.click( 'text=Log In' );

					await page.goto( `/my-account` );
					await expect(
						page.locator(
							'.woocommerce-MyAccount-navigation-link--customer-logout'
						)
					).toBeVisible();
					await expect(
						page.locator(
							'div.woocommerce-MyAccount-content > p >> nth=0'
						)
					).toContainText( 'Hello' );

					await page.context().storageState( { path: statePath } );
					console.log( '\u2714 Logged-in as customer successfully.' );
					resolve();
					return;
				} catch ( e ) {
					console.log(
						`Customer log-in failed. Retrying... ${ i }/${ retries }`
					);
					console.log( e );
				}
			}

			reject();
		} )();
	} );

/**
 * Helper function to login a WP admin user and save the state on a given path.
 */
export const loginAdminAndSaveState = ( {
	page,
	username,
	password,
	statePath,
	retries,
} ) =>
	new Promise( ( resolve, reject ) => {
		( async () => {
			// Sign in as admin user and save state
			for ( let i = 0; i < retries; i++ ) {
				try {
					console.log( '- Trying to log-in as admin...' );
					await page.goto( `/wp-admin` );
					await page.fill( 'input[name="log"]', username );
					await page.fill( 'input[name="pwd"]', password );
					await page.click( 'text=Log In' );
					await page.waitForLoadState( 'networkidle' );

					await expect( page.locator( 'div.wrap > h1' ) ).toHaveText(
						'Dashboard'
					);
					await page.context().storageState( { path: statePath } );
					console.log( '\u2714 Logged-in as admin successfully.' );
					resolve();
					return;
				} catch ( e ) {
					console.log(
						`Admin log-in failed, Retrying... ${ i }/${ retries }`
					);
					console.log( e );
				}
			}
			reject();
		} )();
	} );

/**
 * Helper function to login a WP admin user and save the state on a given path.
 */
export const createApiTokens = ( page ) =>
	new Promise( ( resolve, reject ) => {
		( async () => {
			const nRetries = 5;
			for ( let i = 0; i < nRetries; i++ ) {
				try {
					console.log( '- Trying to add consumer token...' );
					await page.goto(
						`/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys&create-key=1`
					);
					await page.fill( '#key_description', 'Key for API access' );
					await page.selectOption( '#key_permissions', 'read_write' );
					await page.click( 'text=Generate API key' );
					process.env.CONSUMER_KEY = await page.inputValue(
						'#key_consumer_key'
					);
					process.env.CONSUMER_SECRET = await page.inputValue(
						'#key_consumer_secret'
					);
					console.log( '\u2714 Added consumer token successfully.' );
					resolve();
					return;
				} catch ( e ) {
					console.log(
						`Failed to add consumer token. Retrying... ${ i }/${ nRetries }`
					);
					console.log( e );
				}
			}
			reject();
		} )();
	} );

/**
 * Helper function to update the plugin.
 */
export const installPluginFromRepository = ( page ) =>
	new Promise( ( resolve ) => {
		( async () => {
			console.log(
				`- Trying to install plugin version ${ PLUGIN_VERSION } from repository ${ PLUGIN_REPOSITORY }...`
			);
			let pluginZipPath;
			let pluginSlug = PLUGIN_REPOSITORY.split( '/' ).pop();

			// Get the download URL and filename of the plugin
			const pluginDownloadURL = await getReleaseZipUrl( PLUGIN_VERSION );
			const zipFilename = pluginDownloadURL.split( '/' ).pop();
			pluginZipPath = path.resolve(
				__dirname,
				`../../tmp/${ zipFilename }`
			);

			// Download the needed plugin.
			await downloadZip( {
				url: pluginDownloadURL,
				downloadPath: pluginZipPath,
				authToken: GITHUB_TOKEN,
			} );
			await page.goto( 'wp-admin/plugin-install.php?tab=upload', {
				waitUntil: 'networkidle',
			} );

			await page.setInputFiles( 'input#pluginzip', pluginZipPath, {
				timeout: 10000,
			} );
			await page.click( "input[type='submit'] >> text=Install Now" );

			await page.click( 'text=Replace current with uploaded', {
				timeout: 10000,
			} );

			await expect(
				page.locator( '#wpbody-content .wrap' )
			).toContainText( /Plugin (?:downgraded|updated) successfully/gi );

			await page.goto( 'wp-admin/plugins.php', {
				waitUntil: 'networkidle',
			} );

			// Assert that the plugin is listed and active
			await expect(
				page.locator( `#deactivate-${ pluginSlug }` )
			).toBeVisible();

			console.log(
				`\u2714 Plugin version ${ PLUGIN_VERSION } installed successfully.`
			);

			resolve();
		} )();
	} );
