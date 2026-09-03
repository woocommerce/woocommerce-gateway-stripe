import { execSync } from 'child_process';
import { NodeSSH } from 'node-ssh';

/**
 * Run WP-CLI commands against the site under test.
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
 * @param {Array.<string>} commands WP-CLI commands, each starting with `wp `.
 * @return {Promise<void>} Resolves when all commands have run.
 */
export async function runWpCommands( commands ) {
	if ( process.env.DOCKER ) {
		for ( const command of commands ) {
			execSync( `. ./tests/e2e/bin/common.sh && cli ${ command }`, {
				shell: '/bin/bash',
				stdio: 'pipe',
			} );
		}
		return;
	}

	if ( ! process.env.SSH_HOST ) {
		throw new Error(
			'Cannot run WP-CLI commands: set the SSH_* variables in tests/e2e/config/local.env for remote runs.'
		);
	}

	const ssh = new NodeSSH();
	await ssh.connect( {
		host: process.env.SSH_HOST.replace( /\/$/, '' ),
		username: process.env.SSH_USER,
		password: process.env.SSH_PASSWORD,
	} );

	try {
		for ( const command of commands ) {
			const result = await ssh.execCommand( command, {
				cwd: process.env.SSH_PATH,
			} );
			if ( 0 !== result.code ) {
				throw new Error(
					`WP-CLI command failed: ${ command }\n${ result.stderr }`
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
		`wp post term set ${ productId } product_type ${ type }`,
	] );
}
