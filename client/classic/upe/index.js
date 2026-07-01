/* global wc_stripe_upe_params */
/*
 * Dependency-free bootstrap for the classic-checkout Stripe (UPE) bundle.
 *
 * Host "defer all render-blocking JS" optimizers (e.g. SiteGround Speed
 * Optimizer) can run our bundle before the WordPress/WooCommerce globals it
 * depends on (window.wp.data, window.wc.wcSettings, ...) exist, so ./init's
 * first externalized import throws and aborts checkout. Gate ./init behind a
 * readiness check — derived from the build's own declared dependencies so it
 * can't drift from what ./init imports — and load it as an async chunk so the
 * externals only resolve once those globals are present. This file imports no
 * WordPress/WooCommerce external, so it can never throw at load.
 */
import './style.scss';

// Pin the async-chunk base URL to the plugin build dir; webpack's default "auto"
// publicPath would resolve ./init against the optimizer's rewritten entry path,
// where it does not exist. Localized inline, so correct on optimized and
// unoptimized sites alike.
// eslint-disable-next-line camelcase
const bootstrapParams =
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_params !== 'undefined' ? wc_stripe_upe_params : null;

// typeof-guard the webpack-only magic identifier so this is a no-op outside a
// webpack bundle (e.g. unit tests), where assigning it would throw.
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

// Give up after this long and load anyway, so a genuinely missing dependency
// fails as it would without this gate rather than silently never initializing.
const READY_TIMEOUT_MS = 10000;
const READY_POLL_MS = 50;

// Dash-to-camelCase, matching how @wordpress/dependency-extraction-webpack-plugin
// derives a global name from a script handle (e.g. `api-fetch` -> `apiFetch`).
const camelCaseDash = ( value ) =>
	value.replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );

// Handles whose global lives outside the `wp` namespace or is externalized
// irregularly, so it can't be derived from the handle mechanically.
const HANDLE_GLOBAL_PATHS = {
	jquery: [ 'jQuery' ],
	react: [ 'React' ],
	'react-dom': [ 'ReactDOM' ],
	'wc-settings': [ 'wc', 'wcSettings' ],
};

// Handles with no single global to gate on: wp-polyfill patches native
// prototypes, and stripe/wc-checkout are plain enqueue-only scripts we add
// manually rather than webpack externals. Waiting on them would hang or is moot.
const IGNORED_HANDLES = [ 'wp-polyfill', 'stripe', 'wc-checkout' ];

// Used only when the build's dependency list isn't available (e.g. a page cached
// before this version deployed). Must never be empty, or the gate would pass
// immediately and reintroduce the deferred-load crash.
const FALLBACK_GLOBAL_PATHS = [
	[ 'wp', 'data' ],
	[ 'wp', 'i18n' ],
	[ 'wc', 'wcSettings' ],
];

// Map a build dependency handle to the global object path its externalized
// module reads at evaluation time, or null when there's nothing to wait for.
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
	// Unknown handle: don't gate on it so a stray entry can't hang checkout; the
	// bootstrap test guards against a wp-/wc- external silently slipping through.
	return null;
};

// Derived once at load from the localized build dependencies, so the readiness
// gate tracks whatever ./init imports instead of a hand-maintained list.
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

const dependenciesReady = () => requiredGlobalPaths.every( globalPathReady );

// A chunk load can fail transiently (network blip, optimizer still racing the
// request), so retry once before giving up. A hard failure is logged rather
// than left as an unhandled rejection; we deliberately don't surface UI from
// here to keep the bootstrap free of WordPress/WooCommerce externals.
const loadInit = ( retriesLeft = 1 ) =>
	import( /* webpackChunkName: "upe-classic-init" */ './init' ).catch(
		( error ) => {
			if ( retriesLeft > 0 ) {
				return loadInit( retriesLeft - 1 );
			}
			// eslint-disable-next-line no-console
			console.error(
				'WooCommerce Stripe: failed to load the classic checkout init chunk.',
				error
			);
		}
	);

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
