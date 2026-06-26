/* global wc_stripe_upe_params */
/*
 * Dependency-free bootstrap for the classic-checkout Stripe (UPE) bundle.
 *
 * Host "defer all render-blocking JS" optimizers (e.g. SiteGround Speed
 * Optimizer) can run our bundle before window.wp.data / window.wp.i18n /
 * window.wc.wcSettings exist, so ./init's first externalized import throws and
 * aborts checkout. Gate ./init behind a readiness check and load it as an async
 * chunk so the externals only resolve once those globals are present. This file
 * imports no WordPress/WooCommerce external, so it can never throw at load.
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
