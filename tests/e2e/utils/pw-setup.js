import { resolve } from 'path';

const { expect } = require( '@playwright/test' );

const { NodeSSH } = require( 'node-ssh' );
const path = require( 'path' );
const { downloadZip, getReleaseZipUrl } = require( '../utils/plugin-utils' );

const {
	GITHUB_TOKEN,
	PLUGIN_REPOSITORY,
	PLUGIN_VERSION,
	STRIPE_PUB_KEY,
	STRIPE_SECRET_KEY,
} = process.env;

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

/**
 * Helper function to perform the WooCommerce setup.
 */
export const setupWoo = async ( credentials, path ) => {
	const ssh = new NodeSSH();

	const setupCommands = [
		'wp theme install storefront --activate',
		'wp option set woocommerce_store_address "60 29th Street"',
		'wp option set woocommerce_store_address_2 "#343"',
		'wp option set woocommerce_store_city "San Francisco"',
		'wp option set woocommerce_default_country "US:CA"',
		'wp option set woocommerce_store_postcode "94110"',
		'wp option set woocommerce_currency "USD"',
		'wp option set woocommerce_product_type "both"',
		'wp option set woocommerce_allow_tracking "no"',
		'wp wc --user=admin tool run install_pages',
		'wp plugin install wordpress-importer --activate',
		'wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip',
		`wp wc shipping_zone create --name="Everywhere" --order=1 --user=admin`,
		`wp wc shipping_zone_method create 1 --method_id="flat_rate" --user=admin`,
		`wp wc shipping_zone_method create 1 --method_id="free_shipping" --user=admin`,
		`wp option update --format=json woocommerce_flat_rate_1_settings '{"title":"Flat rate","tax_status":"taxable","cost":"10"}'`,
	];

	return ssh.connect( credentials ).then( async () => {
		for ( const command of setupCommands ) {
			console.log( command );
			await ssh
				.execCommand( command, { cwd: path } )
				.then( ( result ) => {
					console.log( result.stdout );
				} );
		}
	} );
};

/**
 * Helper function to perform the Stripe plugin setup.
 */
export const setupStripe = ( page, baseUrl ) =>
	new Promise( ( resolve, reject ) => {
		( async () => {
			try {
				const stripe = require( 'stripe' )(
					process.env.STRIPE_SECRET_KEY
				);

				// Clean-up previous webhooks for this URL. We can only get the Webhook secret via API when it's created.
				const webhookURL = `${ baseUrl }?wc-api=wc_stripe`;

				const webhooks = await stripe.webhookEndpoints
					.list()
					.then( ( result ) =>
						result.data.filter( ( w ) => w.url == webhookURL )
					)
					.then( async ( webhooks ) => {
						console.log( webhooks );
						for ( const webhook of webhooks ) {
							stripe.webhookEndpoints.del( webhook.id );
						}
					} );

				// Create a new webhook.
				const webhookEndpoint = await stripe.webhookEndpoints.create( {
					url: webhookURL,
					enabled_events: [ '*' ],
					description: 'Webhook created for E2E tests.',
				} );

				const nRetries = 5;
				for ( let i = 0; i < nRetries; i++ ) {
					try {
						console.log( '- Trying to setup the Stripe keys...' );
						await page.goto(
							`/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe`
						);

						await page
							.getByText( /Enter account keys.*/ )
							.click( { timeout: 5000 } );
						await page.locator( 'text="Test"' ).click();

						await page
							.locator( '[name="test_publishable_key"]' )
							.fill( STRIPE_PUB_KEY );
						await page
							.locator( '[name="test_secret_key"]' )
							.fill( STRIPE_SECRET_KEY );
						await page
							.locator( '[name="test_webhook_secret"]' )
							.fill( webhookEndpoint.secret );

						await page.locator( 'text="Save test keys"' ).click();
						await page.waitForNavigation( { timeout: 10000 } );

						await expect( page ).toHaveURL(
							/.*section=stripe&panel=settings.*/
						);

						console.log( '\u2714 Added Stripe keys successfully.' );
						resolve();
						return;
					} catch ( e ) {
						console.log(
							`Failed to add Stripe keys. Retrying... ${ i }/${ nRetries }`
						);
						console.log( e );
					}
				}
			} catch ( e ) {
				reject( e );
			}

			reject();
		} )();
	} );
