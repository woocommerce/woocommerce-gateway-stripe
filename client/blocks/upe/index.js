/* global wc_stripe_upe_blocks_params */
/*
 * Bootstrap for the Blocks-checkout Stripe (UPE) bundle.
 *
 * The real entry (./init and its dependency graph) statically imports the
 * WooCommerce Blocks registry, WooCommerce settings, WordPress data, and i18n
 * packages, which webpack externalizes to window.wc.wcBlocksRegistry /
 * window.wc.wcSettings / window.wp.data / window.wp.i18n. It also reads the
 * localized config and calls registerPaymentMethod/registerExpressPaymentMethod
 * at module evaluation time. Those globals are populated by the scripts
 * WordPress runs first via our declared dependencies.
 *
 * Some host "defer all render-blocking JS" optimizers (e.g. SiteGround Speed
 * Optimizer) relocate and defer our bundle without preserving that dependency
 * order. The module graph then evaluates before those globals exist, the first
 * externalized read throws, and the throw aborts the integration script — which
 * breaks the whole Checkout Block, not just our payment method. Gate the real
 * init behind a readiness check and load it as a separate async chunk, so the
 * externals are only resolved (and the methods only registered) once those
 * globals are present.
 *
 * This file intentionally imports nothing that resolves a WordPress/WooCommerce
 * external, so it can never throw at load. The SCSS imports only produce the
 * entry stylesheet and carry no JS dependency.
 */
import './styles.scss';
import '../express-checkout/styles.scss';

// Pin the async-chunk base URL to the plugin's build directory. Under a JS
// optimizer our entry script can be served from a rewritten path (e.g.
// .../siteground-optimizer-assets/), and webpack's default "auto" publicPath
// would resolve the init chunk against that path, where it does not exist. The
// value is localized inline on its own tiny global (not via wcSettings, which
// is itself deferred), so it is available before the bundle executes.
// eslint-disable-next-line camelcase
const bootstrapParams =
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_blocks_params !== 'undefined'
		? wc_stripe_upe_blocks_params // eslint-disable-line camelcase
		: null;

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
	!! ( window.wc && window.wc.wcSettings ) &&
	!! ( window.wc && window.wc.wcBlocksRegistry );

const loadInit = () =>
	import( /* webpackChunkName: "upe-blocks-init" */ './init' );

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
