/* global wc_stripe_upe_blocks_params */
/*
 * Bootstrap for the Blocks-checkout Stripe bundle. The real entry (./init)
 * statically imports WP/WC externals and registers payment methods at load. A
 * host "defer all render-blocking JS" optimizer (e.g. SiteGround Speed Optimizer)
 * can run our bundle before those globals exist, throwing and breaking the whole
 * Checkout Block. So wait for the globals, then load init as an async chunk.
 * This file imports nothing resolving a WP/WC external, so it can't throw at load.
 */
import './styles.scss';
import '../express-checkout/styles.scss';

// Pin the async chunk's base URL to the plugin build dir: under an optimizer our
// entry can be served from a rewritten path where the chunk doesn't exist. The
// value rides a tiny dedicated global (not wcSettings, which is itself deferred).
// eslint-disable-next-line camelcase
const bootstrapParams =
	// eslint-disable-next-line camelcase
	typeof wc_stripe_upe_blocks_params !== 'undefined'
		? wc_stripe_upe_blocks_params // eslint-disable-line camelcase
		: null;

// `typeof` guards the webpack-only magic identifier so this is a no-op in tests.
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

// Load anyway after the timeout so a genuinely missing dependency fails as it
// would without this gate rather than silently never initializing.
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
