import { execSync } from 'child_process';
import { NodeSSH } from 'node-ssh';

/**
 * Run WP-CLI commands against the site under test.
 *
 * Dispatches on the DOCKER env flag set by run-tests.sh: Docker runs use a
 * throwaway wordpress:cli container attached to the E2E WordPress container
 * (mirroring cli() in tests/e2e/bin/common.sh); remote runs go over SSH with
 * the credentials from local.env, like the setup helpers in playwright-setup.js.
 *
 * @param {Array.<string>} commands WP-CLI commands, each starting with `wp `.
 * @return {Promise<void>} Resolves when all commands have run.
 */
export async function runWpCommands( commands ) {
	if ( process.env.DOCKER ) {
		for ( const command of commands ) {
			execSync(
				`docker run -i --rm --user 33:33 --env-file ${ process.env.E2E_ROOT }/env/default.env --volumes-from wcstripe-e2e-wordpress --network container:wcstripe-e2e-wordpress wordpress:cli ${ command }`,
				{ stdio: 'pipe' }
			);
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
