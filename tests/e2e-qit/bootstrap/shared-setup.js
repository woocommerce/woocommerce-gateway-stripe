import { test } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Shared setup that runs before all plugins' tests are executed.
 * Use it to:
 * - Set up shared UI settings
 * - Prepare shared browser storage
 * - Create any shared test data via UI
 * - Set up site-wide settings that require UI interaction
 */

test( 'Add a new WooCommerce Consumer Token', async ( { page }, testInfo ) => {
	const { stateDir } = testInfo.project.use;
	qit.setEnv( 'ADMINSTATE', `${ stateDir }/admin-state.json` );

	await qit.loginAsAdmin( page );
	await page.context().storageState( { path: qit.getEnv( 'ADMINSTATE' ) } );

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys&create-key=1'
	);
	await page.locator( '#key_description' ).fill( 'Key for API access' );
	await page.locator( '#key_permissions' ).selectOption( 'read_write' );
	await page.locator( 'text=Generate API key' ).click();

	qit.setEnv(
		'CONSUMER_KEY',
		await page.locator( '#key_consumer_key' ).inputValue()
	);
	qit.setEnv(
		'CONSUMER_SECRET',
		await page.locator( '#key_consumer_secret' ).inputValue()
	);
} );
