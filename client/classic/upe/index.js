/* global wc_stripe_upe_params */
/*
 * Dependency-free bootstrap for the classic-checkout Stripe (UPE) bundle.
 *
 * "Defer render-blocking JS" optimizers can run our bundle before the WP/WC
 * globals ./init imports exist, throwing at load. Wait for those globals (the
 * list is derived from the build's declared dependencies so it can't drift),
 * then load ./init as an async chunk. This file imports no WP/WC external.
 */
import './style.scss';

// Pin the async-chunk base URL to the plugin build dir; webpack's default "auto"
// publicPath would resolve ./init against the optimizer's rewritten entry path.
// eslint-disable-next-line camelcase
const bootstrapParams =
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_params !== 'undefined' ? wc_stripe_upe_params : null;

// Guard the webpack-only magic identifier so this is a no-op outside a bundle.
// eslint-disable-next-line no-undef, camelcase
const hasWebpackPublicPath = typeof __webpack_public_path__ !== 'undefined';
if (
	hasWebpackPublicPath &&
	bootstrapParams &&
	bootstrapParams.pluginBuildUrl
) {
	// eslint-disable-next-line no-undef, camelcase
	__webpack_public_path__ = bootstrapParams.pluginBuildUrl;
}

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

const dependenciesReady = () => typeof wc_stripe_upe_params !== 'undefined' && requiredGlobalPaths.every( globalPathReady );

// Retry once on a transient chunk-load failure; log a hard failure rather than
// leave an unhandled rejection.
const loadInit = ( retriesLeft = 1 ) =>
	import( /* webpackMode: "eager" */ './init' ).catch( ( error ) => {
		if ( retriesLeft > 0 ) {
			return loadInit( retriesLeft - 1 );
		}
		// eslint-disable-next-line no-console
		console.error(
			'WooCommerce Stripe: failed to load the classic checkout init chunk.',
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
