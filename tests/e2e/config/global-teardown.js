import path from 'path';
import fs from 'fs';

import { chromium } from '@playwright/test';
import { user } from '../utils';

const { ADMIN_USER, ADMIN_PASSWORD, PLUGIN_VERSION } = process.env;

module.exports = async ( config ) => {
	const { baseURL, userAgent } = config.projects[ 0 ].use;

	console.log( `\n======\n` );

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = { baseURL, userAgent };

	const browser = await chromium.launch();
	const context = await browser.newContext( contextOptions );
	const adminPage = await context.newPage();

	let consumerTokenCleared = false;

	await user.login( adminPage, ADMIN_USER, ADMIN_PASSWORD );

	// Clean up the consumer keys
	const keysRetries = 5;
	for ( let i = 1; i <= keysRetries; i++ ) {
		try {
			console.log( '- Trying to clear consumer token... Try:' + i );

			await adminPage.goto(
				`/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys`
			);
			await adminPage.dispatchEvent( 'a.submitdelete', 'click' );
			console.log( '\u2714 Cleared up consumer token successfully.' );
			consumerTokenCleared = true;
			break;
		} catch ( e ) {
			console.error(
				`Failed to clear consumer token. Retrying... ${ i }/${ keysRetries }. Error:`,
				e
			);
		}
	}

	if ( ! consumerTokenCleared ) {
		console.error( 'Could not clear consumer token.' );
		process.exit( 1 );
	}

	// Delete the tmp folder.
	fs.rmSync( path.resolve( __dirname, `../../tmp` ), {
		recursive: true,
		force: true,
	} );

	console.log( `\n======\n` );
};
