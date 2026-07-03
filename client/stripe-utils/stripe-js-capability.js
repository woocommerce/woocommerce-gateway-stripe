import { recordEvent } from 'wcstripe/tracking';

// Fire at most one capability event per surface per page load, so a checkout with
// several element mounts doesn't inflate the counts.
const recordedSurfaces = {};

/**
 * Classifies the loaded Stripe.js build from capability presence alone.
 *
 * We must NOT call initCheckout() to probe: on "dahlia+" builds it is a throwing
 * stub. The presence of initCheckoutElementsSdk is the reliable dahlia marker.
 *
 * - dahlia: initCheckoutElementsSdk present (initCheckout is a throwing stub).
 * - clover: initCheckout present, initCheckoutElementsSdk absent (works normally).
 * - legacy: initCheckout absent (older build).
 * - unknown: no Stripe instance.
 *
 * @param {Object} stripe Resolved Stripe.js instance (from api.getStripe()).
 * @return {{build: string, hasInitCheckout: boolean, hasInitCheckoutElementsSdk: boolean}} Capability summary.
 */
export function detectStripeJsBuild( stripe ) {
	const hasInitCheckout = typeof stripe?.initCheckout === 'function';
	const hasInitCheckoutElementsSdk =
		typeof stripe?.initCheckoutElementsSdk === 'function';

	let build = 'unknown';
	if ( hasInitCheckoutElementsSdk ) {
		build = 'dahlia';
	} else if ( hasInitCheckout ) {
		build = 'clover';
	} else if ( stripe ) {
		build = 'legacy';
	}

	return { build, hasInitCheckout, hasInitCheckoutElementsSdk };
}

/**
 * Records which Stripe.js build actually loaded at Payment Element mount, so the
 * Clover:Dahlia ratio and the render-risk population can be measured in production.
 *
 * The pinned channel is Clover, where the 10.8.3 guard removal (#5618) is inert.
 * On the rare dahlia load with Adaptive Pricing enabled, the Blocks Payment Element
 * fails to render (a synchronous initCheckout() throw bypasses the fallback); classic
 * still renders via its try/catch. predicted_render_risk flags that at-risk combination.
 *
 * Best-effort and non-throwing: telemetry must never affect checkout.
 *
 * @param {Object}  options
 * @param {Object}  options.stripe     Resolved Stripe.js instance.
 * @param {string}  options.surface    'classic' | 'blocks'.
 * @param {Object=} options.serverData Frontend config (isAdaptivePricingEnabled, shouldShowOptimizedCheckout).
 */
export function recordStripeJsCapability( { stripe, surface, serverData } ) {
	try {
		if ( recordedSurfaces[ surface ] ) {
			return;
		}
		recordedSurfaces[ surface ] = true;

		const { build, hasInitCheckout, hasInitCheckoutElementsSdk } =
			detectStripeJsBuild( stripe );

		const adaptivePricingEnabled = Boolean(
			serverData?.isAdaptivePricingEnabled
		);
		const renderRisk =
			surface === 'blocks' &&
			adaptivePricingEnabled &&
			build === 'dahlia';

		recordEvent( 'wcstripe_stripe_js_capability', {
			checkout_surface: surface,
			stripe_js_build: build,
			has_init_checkout: hasInitCheckout ? 'yes' : 'no',
			has_init_checkout_elements_sdk: hasInitCheckoutElementsSdk
				? 'yes'
				: 'no',
			adaptive_pricing_enabled: adaptivePricingEnabled ? 'yes' : 'no',
			optimized_checkout: serverData?.shouldShowOptimizedCheckout
				? 'yes'
				: 'no',
			predicted_render_risk: renderRisk ? 'yes' : 'no',
		} );
	} catch ( e ) {
		// Never let telemetry affect checkout.
	}
}

/**
 * Test-only: clears the per-surface guard so each test starts fresh.
 */
export function resetStripeJsCapabilityGuard() {
	Object.keys( recordedSurfaces ).forEach(
		( key ) => delete recordedSurfaces[ key ]
	);
}
