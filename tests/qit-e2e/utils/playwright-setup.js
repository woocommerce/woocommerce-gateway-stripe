import { expect } from '@playwright/test';
import { user } from './index.js';

/**
 * Helper function to login a WP admin user and save the state on a given path.
 * @param {Object} options
 * @param {Page} options.page Playwright page object.
 * @param {string} options.username Username of the user to login.
 * @param {string} options.password Password of the user to login.
 * @param {string} options.statePath Path to save the state.
 * @param {number} options.retries Number of retries to login.
 * @return {Promise} Promise object represents the state of the operation.
 */
export const loginAdminAndSaveState = ( {
	page,
	username,
	password,
	statePath,
	retries,
} ) =>
	new Promise( ( resolve ) => {
		( async () => {
			// Sign in as admin user and save state
			console.log( '- Trying to log-in as admin...' );
			await user.login( page, username, password, retries );
			await page.context().storageState( { path: statePath } );
			console.log( '\u2714 Logged-in as admin successfully.' );
			resolve();
		} )();
	} );

/**
 * Helper function to create WC API tokens and save them as env variables.
 * This function is used when the admin user is already logged in.
 * @param {Page} page Playwright page object.
 * @return {Promise} Promise object represents the state of the operation.
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
					process.env.CONSUMER_KEY =
						await page.inputValue( '#key_consumer_key' );
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
