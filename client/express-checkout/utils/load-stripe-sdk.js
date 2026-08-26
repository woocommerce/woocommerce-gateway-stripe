import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

// Shopper-activity signals; once seen, waiting longer only delays the button paint.
const INTERACTION_EVENTS = [
	'pointerdown',
	'pointermove',
	'touchstart',
	'keydown',
	'wheel',
	'scroll',
];

/**
 * Resolves once the Stripe SDK is available on the page.
 *
 * When deferred (see WC_Stripe_Express_Checkout_Helper::should_defer_stripe_js()),
 * the SDK is injected at the first shopper interaction or once the browser is
 * idle after the load event — whichever comes first — keeping its request
 * cascade out of the page-load metrics window. On eager pages `window.Stripe`
 * already exists and this resolves immediately.
 *
 * @return {Promise<void>} Promise resolving when the SDK can be used.
 */
// Cart/checkout AJAX updates re-enter ensureStripeSdk() while the first
// injection may still be loading (window.Stripe not yet set); sharing one
// promise across calls prevents a second script tag from being injected.
let sdkPromise = null;

export const ensureStripeSdk = () => {
	if ( window.Stripe ) {
		return Promise.resolve();
	}

	const stripeParams = getExpressCheckoutData( 'stripe' );
	if ( ! stripeParams?.defer_sdk || ! stripeParams?.sdk_url ) {
		// Eager page: the SDK loads via script dependency; getStripe() fails
		// closed if it genuinely isn't there.
		return Promise.resolve();
	}

	if ( sdkPromise ) {
		return sdkPromise;
	}

	sdkPromise = new Promise( ( resolve ) => {
		let injected = false;
		const inject = () => {
			if ( injected ) {
				return;
			}
			injected = true;

			// The SDK may have arrived through another path (e.g. an eager
			// tag elsewhere on the page) between scheduling and firing.
			if ( window.Stripe ) {
				resolve();
				return;
			}

			const script = document.createElement( 'script' );
			script.src = stripeParams.sdk_url;
			script.onload = resolve;
			// Resolve on failure too: getStripe() then fails closed and the
			// buttons simply don't render, as with a blocked eager load.
			script.onerror = resolve;
			document.head.appendChild( script );
		};

		INTERACTION_EVENTS.forEach( ( eventName ) =>
			window.addEventListener( eventName, inject, {
				once: true,
				passive: true,
				capture: true,
			} )
		);

		const injectWhenIdle = () => {
			if ( window.requestIdleCallback ) {
				window.requestIdleCallback( inject, { timeout: 2000 } );
			} else {
				setTimeout( inject, 200 );
			}
		};

		if ( document.readyState === 'complete' ) {
			injectWhenIdle();
		} else {
			window.addEventListener( 'load', injectWhenIdle, { once: true } );
		}
	} );

	return sdkPromise;
};
