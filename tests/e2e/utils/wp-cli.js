import { execSync } from 'child_process';
import { NodeSSH } from 'node-ssh';

/**
 * Wrap a value in single quotes for safe interpolation into a shell command,
 * escaping any embedded single quotes, so argument values can never be
 * interpreted as shell syntax.
 *
 * @param {string|number} arg Value to escape.
 * @return {string} The single-quoted, escaped value.
 */
const shellEscape = ( arg ) =>
	"'" + String( arg ).replace( /'/g, "'\\''" ) + "'";

/**
 * Run WP-CLI commands against the site under test.
 *
 * Each command is an array of WP-CLI arguments without the leading `wp`, e.g.
 * `[ 'post', 'term', 'set', '123', 'product_type', 'subscription' ]`. Every
 * argument is shell-escaped individually, so values can never be interpreted as
 * shell syntax.
 *
 * Dispatches on the DOCKER env flag set by run-tests.sh. Docker runs reuse the
 * canonical cli() helper from tests/e2e/bin/common.sh — the exact same
 * throwaway wordpress:cli invocation the setup scripts rely on — rather than
 * duplicating the container name and flags here, so this can't drift from the
 * container the setup created. Remote runs go over SSH with the credentials
 * from local.env, like the setup helpers in playwright-setup.js.
 *
 * The Docker branch assumes the process runs from the repository root (common.sh
 * derives E2E_ROOT from `pwd`); this holds for every npm-invoked test run in CI
 * and locally.
 *
 * @param {Array.<Array.<string>>} commands WP-CLI commands, each an array of arguments.
 * @return {Promise<void>} Resolves when all commands have run.
 */
export async function runWpCommands( commands ) {
	const wpCommands = commands.map( ( args ) =>
		[ 'wp', ...args ].map( shellEscape ).join( ' ' )
	);

	if ( process.env.DOCKER ) {
		for ( const wpCommand of wpCommands ) {
			execSync( `. ./tests/e2e/bin/common.sh && cli ${ wpCommand }`, {
				shell: '/bin/bash',
				stdio: 'pipe',
			} );
		}
		return;
	}

	const missing = [
		'SSH_HOST',
		'SSH_USER',
		'SSH_PASSWORD',
		'SSH_PATH',
	].filter( ( name ) => ! process.env[ name ] );
	if ( missing.length ) {
		throw new Error(
			`Cannot run WP-CLI commands remotely: set ${ missing.join(
				', '
			) } in tests/e2e/config/local.env.`
		);
	}

	const ssh = new NodeSSH();
	await ssh.connect( {
		host: process.env.SSH_HOST.replace( /\/$/, '' ),
		username: process.env.SSH_USER,
		password: process.env.SSH_PASSWORD,
	} );

	try {
		for ( const wpCommand of wpCommands ) {
			const result = await ssh.execCommand( wpCommand, {
				cwd: process.env.SSH_PATH,
			} );
			if ( 0 !== result.code ) {
				throw new Error(
					`WP-CLI command failed: ${ wpCommand }\n${ result.stderr }`
				);
			}
		}
	} finally {
		ssh.dispose();
	}
}

/**
 * Change a product's type (the product_type taxonomy term).
 *
 * The WC REST API rejects non-core product types — WooCommerce Subscriptions
 * only registers its types in wc_get_product_types() for admin screens — so
 * subscription-type products must be created as simple via REST and then
 * flipped here.
 *
 * @param {number} productId Product ID.
 * @param {string} type      Product type slug (e.g. 'subscription').
 * @return {Promise<void>} Resolves when the type has been set.
 */
export function setProductType( productId, type ) {
	return runWpCommands( [
		[ 'post', 'term', 'set', String( productId ), 'product_type', type ],
	] );
}
