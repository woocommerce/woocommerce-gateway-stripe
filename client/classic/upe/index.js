/* global wc_stripe_upe_params */
/*
 * Bootstrap for the classic-checkout Stripe (UPE) bundle.
 *
 * The real entry (./init and its dependency graph) statically imports the
 * WordPress data, i18n, and WooCommerce settings packages, which webpack
 * externalizes to window.wp.data / window.wp.i18n / window.wc.wcSettings. Those
 * globals are populated by the wp-data, wp-i18n, and wc-settings scripts that
 * WordPress runs first via our declared script dependencies.
 *
 * Some host "defer all render-blocking JS" optimizers (e.g. SiteGround Speed
 * Optimizer) relocate and defer our bundle without preserving that dependency
 * order. Our module graph then evaluates before those globals exist, the very
 * first externalized import read (e.g. window.wc.wcSettings) throws, and the
 * whole checkout script aborts before the card fields initialize. Gate the
 * real init behind a readiness check and load it as a separate async chunk, so
 * the externals are only resolved once those globals are present.
 *
 * This file intentionally imports nothing that resolves a WordPress/WooCommerce
 * external, so it can never throw at load. The SCSS import only produces the
 * entry stylesheet and carries no JS dependency.
 */
import './style.scss';

// Pin the async-chunk base URL to the plugin's build directory. Under a JS
// optimizer our entry script can be served from a rewritten path (e.g.
// .../siteground-optimizer-assets/), and webpack's default "auto" publicPath
// would resolve the init chunk against that path, where it does not exist. The
// localized value points at the real build dir in every environment, so it is
// also correct for unoptimized sites.
// eslint-disable-next-line camelcase
const bootstrapParams =
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_params !== 'undefined' ? wc_stripe_upe_params : null;

// `typeof` guards the webpack-only magic identifier so this is a no-op outside
// a webpack bundle (e.g. unit tests), where assigning it would throw.
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

// Give up waiting after this long and load anyway, so a genuinely missing
// dependency fails the same way it would without this gate rather than
// silently never initializing.
const READY_TIMEOUT_MS = 10000;
const READY_POLL_MS = 50;

const dependenciesReady = () =>
	typeof window !== 'undefined' &&
	!! ( window.wp && window.wp.data ) &&
	!! ( window.wp && window.wp.i18n ) &&
	!! ( window.wc && window.wc.wcSettings );

const loadInit = () =>
	import( /* webpackChunkName: "upe-classic-init" */ './init' );

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
