import crypto from 'crypto';
import fs from 'fs';
import os from 'os';
import path from 'path';

const LOCK_DIR = path.join(
	os.tmpdir(),
	`wc-stripe-e2e-settings-${ ( process.env.BASE_URL || 'local' ).replace(
		/[^a-z0-9]/gi,
		'_'
	) }.lock`
);
const OWNER_FILE = path.join( LOCK_DIR, 'owner' );
const STALE_MS = 90 * 1000;

const readOwner = () => {
	try {
		return fs.readFileSync( OWNER_FILE, 'utf8' );
	} catch ( error ) {
		return null;
	}
};

const lockAgeMs = () => {
	try {
		return Date.now() - fs.statSync( LOCK_DIR ).mtimeMs;
	} catch ( error ) {
		return 0;
	}
};

/**
 * Cross-process mutex for Stripe settings writes: Playwright workers are
 * separate processes, so an atomic mkdir on the shared filesystem is the lock.
 * It only serializes the workers of one Playwright run; CI jobs that share a
 * Stripe account write the same account-wide payment method configuration and
 * can still interleave.
 *
 * A lock older than STALE_MS is treated as a crashed holder and reclaimed. The
 * reclaim renames it first so only one waiter can take it, and the owner token
 * stops a slow holder from releasing a lock that is no longer its own. Waiting
 * is unbounded here; the test timeout bounds it.
 *
 * @returns {Promise<Function>} Release function.
 */
export const acquireSettingsLock = async () => {
	const token = crypto.randomUUID();
	for (;;) {
		try {
			fs.mkdirSync( LOCK_DIR );
		} catch ( error ) {
			if ( error.code !== 'EEXIST' ) {
				throw error;
			}
			if ( lockAgeMs() > STALE_MS ) {
				const stale = `${ LOCK_DIR }.stale-${ token }`;
				try {
					fs.renameSync( LOCK_DIR, stale );
					fs.rmSync( stale, { recursive: true, force: true } );
				} catch ( renameError ) {
					// Another waiter reclaimed it first.
				}
				continue;
			}
			await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
			continue;
		}
		fs.writeFileSync( OWNER_FILE, token );
		return () => {
			if ( readOwner() === token ) {
				fs.rmSync( LOCK_DIR, { recursive: true, force: true } );
			}
		};
	}
};
