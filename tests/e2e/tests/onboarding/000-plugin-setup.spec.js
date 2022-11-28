const {
	ADMINSTATE,
	ADMIN_USER,
	ADMIN_PASSWORD,
	GITHUB_TOKEN,
	PLUGIN_NAME,
	PLUGIN_REPOSITORY,
	PLUGIN_VERSION,
} = process.env;
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const {
	createPlugin,
	deletePlugin,
	downloadZip,
	deleteZip,
	getReleaseZipUrl,
} = require( '../../utils/plugin-utils' );

const adminUsername = ADMIN_USER ?? 'admin';
const adminPassword = ADMIN_PASSWORD ?? 'password';

let pluginZipPath;
let pluginSlug;

test.describe(
	`${ PLUGIN_NAME } plugin can be uploaded and activated @smoke`,
	() => {
		// Skip test if PLUGIN_REPOSITORY is falsy.
		test.skip(
			! PLUGIN_VERSION,
			`Skipping this test because value of PLUGIN_VERSION was falsy: ${ PLUGIN_VERSION }`
		);

		test.use( { storageState: ADMINSTATE } );

		test.beforeAll( async ( { page } ) => {
			await page.waitForTimeout( 100000 );

			pluginSlug = PLUGIN_REPOSITORY.split( '/' ).pop();

			// Get the download URL and filename of the plugin
			const pluginDownloadURL = await getReleaseZipUrl( PLUGIN_VERSION );
			console.log( pluginDownloadURL );
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
		} );

		test.afterAll( async ( { baseURL, playwright } ) => {
			// Delete the downloaded zip.
			await deleteZip( pluginZipPath );

			// Delete the plugin from the test site.
			// await deletePlugin( {
			// 	request: playwright.request,
			// 	baseURL,
			// 	slug: pluginSlug,
			// 	username: adminUsername,
			// 	password: adminPassword,
			// } );
		} );

		test( `can upload and activate ${ PLUGIN_NAME }`, async ( {
			page,
			playwright,
			baseURL,
		} ) => {
			// Delete the plugin if it's installed.
			// await deletePlugin( {
			// 	request: playwright.request,
			// 	baseURL,
			// 	slug: pluginSlug,
			// 	username: adminUsername,
			// 	password: adminPassword,
			// } );

			await page.goto( 'wp-admin/plugin-install.php?tab=upload', {
				waitUntil: 'networkidle',
			} );

			await page.setInputFiles( 'input#pluginzip', pluginZipPath );
			await page.click( "input[type='submit'] >> text=Install Now" );

			await page.click( 'text=Replace current with uploaded', {
				timeout: 10000,
			} );

			// const pageContent = await page.locator("#wpbody-content .wrap").allInnerTexts().then(x => x.join());
			// console.log(pageContent);

			// await page.pause();

			await expect(
				page.locator( '#wpbody-content .wrap' )
			).toContainText( /Plugin (?:downgraded|updated) successfully/gi );

			await page.pause();

			await page.goto( 'wp-admin/plugins.php', {
				waitUntil: 'networkidle',
			} );

			// Assert that the plugin is listed and active
			await expect(
				page.locator( `#deactivate-${ pluginSlug }` )
			).toBeVisible();
		} );
	}
);
