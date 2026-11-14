/* global wc_stripe_upe_params */
/*
 * Dependency-free bootstrap for the classic-checkout Stripe (UPE) bundle.
 *
 * "Defer render-blocking JS" optimizers can run our bundle before the WP/WC
 * globals ./init imports exist, throwing at load. Wait for those globals (the
 * list is derived from the build's declared dependencies so it can't drift),
 * then execute ./init — bundled eagerly, so no extra request is involved.
 * This file imports no WP/WC external.
 */
import './style.scss';

// Read once for the dependency list below; the readiness gate re-checks the
// live global instead, since an optimizer can reorder the inline settings
// script to run after this bundle.
// eslint-disable-next-line camelcase
const bootstrapParams =
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_params !== 'undefined' ? wc_stripe_upe_params : null;

// Give up after this long and load anyway, so a real missing dependency fails
// as it would without this gate.
const READY_TIMEOUT_MS = 10000;
const READY_POLL_MS = 50;

// Dash-to-camelCase, matching how the dependency-extraction plugin names a
// global from a handle (`api-fetch` -> `apiFetch`).
const camelCaseDash = ( value ) =>
	value.replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );

// Handles whose global isn't `wp.<camelCase>` and can't be derived mechanically.
const HANDLE_GLOBAL_PATHS = {
	jquery: [ 'jQuery' ],
	react: [ 'React' ],
	'react-dom': [ 'ReactDOM' ],
	'wc-settings': [ 'wc', 'wcSettings' ],
};

// Handles with no global to gate on (wp-polyfill patches natives; stripe and
// wc-checkout aren't webpack externals). Waiting on them would hang.
const IGNORED_HANDLES = [ 'wp-polyfill', 'stripe', 'wc-checkout' ];

// Fallback when the build's dependency list isn't localized (e.g. a stale cached
// page). Must never be empty, or the gate would pass immediately.
const FALLBACK_GLOBAL_PATHS = [
	[ 'wp', 'data' ],
	[ 'wp', 'i18n' ],
	[ 'wc', 'wcSettings' ],
];

// Map a dependency handle to the global path its module reads, or null to skip.
const handleToGlobalPath = ( handle ) => {
	if ( IGNORED_HANDLES.includes( handle ) ) {
		return null;
	}
	if ( HANDLE_GLOBAL_PATHS[ handle ] ) {
		return HANDLE_GLOBAL_PATHS[ handle ];
	}
	if ( handle.indexOf( 'wp-' ) === 0 ) {
		return [ 'wp', camelCaseDash( handle.slice( 'wp-'.length ) ) ];
	}
	// Unknown handle: skip rather than risk hanging on a wrong path.
	// eslint-disable-next-line no-console
	console.warn(
		`[wc-stripe] No known global for script dependency "${ handle }"; not gating on it.`
	);
	return null;
};

// Derived from the localized build dependencies so the gate tracks ./init.
const requiredGlobalPaths = ( () => {
	const dependencies =
		bootstrapParams && Array.isArray( bootstrapParams.scriptDependencies )
			? bootstrapParams.scriptDependencies
			: [];
	const paths = dependencies.map( handleToGlobalPath ).filter( Boolean );
	return paths.length ? paths : FALLBACK_GLOBAL_PATHS;
} )();

const globalPathReady = ( path ) => {
	let node = typeof window !== 'undefined' ? window : undefined;
	for ( const segment of path ) {
		if ( ! node || ! node[ segment ] ) {
			return false;
		}
		node = node[ segment ];
	}
	return true;
};

// ./init reads the params global at eval, so gate on it appearing too — not
// just the WP/WC globals — in case an optimizer runs this bundle first.
const dependenciesReady = () =>
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_params !== 'undefined' &&
	requiredGlobalPaths.every( globalPathReady );

// ./init is bundled eagerly, so a rejection here is an initialization failure,
// not a network miss: log it rather than leave an unhandled rejection.
const loadInit = () =>
	import( /* webpackMode: "eager" */ './init' ).catch( ( error ) => {
		// eslint-disable-next-line no-console
		console.error(
			'WooCommerce Stripe: failed to load the classic checkout init module.',
			error
		);
	} );

if ( dependenciesReady() ) {
	loadInit();
} else {
	const startedAt = Date.now();
	const timer = setInterval( () => {
		if (
			dependenciesReady() ||
			Date.now() - startedAt >= READY_TIMEOUT_MS
		) {
			clearInterval( timer );
			loadInit();
		}
	}, READY_POLL_MS );
}
