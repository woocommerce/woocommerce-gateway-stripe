describe( 'ensureStripeSdk', () => {
	const SDK_URL = 'https://js.stripe.com/dahlia/stripe.js';

	let ensureStripeSdk;

	const injectedScript = () =>
		document.querySelector( `script[src="${ SDK_URL }"]` );

	beforeEach( () => {
		// The module caches its in-flight promise; re-import so each test
		// starts without a leftover cache from the previous one.
		jest.resetModules();
		( {
			ensureStripeSdk,
		} = require( 'wcstripe/express-checkout/utils/load-stripe-sdk' ) );

		global.wc_stripe_express_checkout_params = {
			stripe: { defer_sdk: true, sdk_url: SDK_URL },
		};
		// Capture the idle callback so tests decide when "idle" happens.
		window.requestIdleCallback = jest.fn();
	} );

	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
		delete window.requestIdleCallback;
		delete window.Stripe;
		injectedScript()?.remove();
	} );

	it( 'resolves immediately without injecting when the SDK is already present', async () => {
		window.Stripe = jest.fn();

		await ensureStripeSdk();

		expect( injectedScript() ).toBeNull();
	} );

	it( 'resolves immediately without injecting when deferral is not active', async () => {
		global.wc_stripe_express_checkout_params.stripe.defer_sdk = false;

		await ensureStripeSdk();

		expect( injectedScript() ).toBeNull();
	} );

	it( 'injects the SDK once on the first interaction, even with several triggers', async () => {
		const promise = ensureStripeSdk();

		window.dispatchEvent( new Event( 'pointerdown' ) );
		window.dispatchEvent( new Event( 'scroll' ) );
		window.dispatchEvent( new Event( 'keydown' ) );

		const scripts = document.querySelectorAll(
			`script[src="${ SDK_URL }"]`
		);
		expect( scripts ).toHaveLength( 1 );

		scripts[ 0 ].onload();
		await promise;
	} );

	it( 'shares one in-flight promise so repeated calls inject a single script', async () => {
		const first = ensureStripeSdk();
		// A cart/checkout AJAX update re-enters before the first injection resolves.
		const second = ensureStripeSdk();

		window.dispatchEvent( new Event( 'pointerdown' ) );

		const scripts = document.querySelectorAll(
			`script[src="${ SDK_URL }"]`
		);
		expect( scripts ).toHaveLength( 1 );
		expect( second ).toBe( first );

		scripts[ 0 ].onload();
		await Promise.all( [ first, second ] );
	} );

	it( 'does not inject when the SDK arrives before the deferred trigger fires', async () => {
		const promise = ensureStripeSdk();

		// An eager tag elsewhere on the page finishes loading first.
		window.Stripe = jest.fn();
		window.dispatchEvent( new Event( 'pointerdown' ) );

		expect( injectedScript() ).toBeNull();
		await promise;
	} );

	it( 'injects the SDK when the browser goes idle after load', async () => {
		const promise = ensureStripeSdk();

		// jsdom reports readyState 'complete', so the idle callback was
		// scheduled immediately; fire it to simulate the browser going idle.
		expect( window.requestIdleCallback ).toHaveBeenCalled();
		window.requestIdleCallback.mock.calls[ 0 ][ 0 ]();

		const script = injectedScript();
		expect( script ).not.toBeNull();

		script.onload();
		await promise;
	} );

	it( 'resolves (fail closed) when the injected script errors', async () => {
		const promise = ensureStripeSdk();

		window.dispatchEvent( new Event( 'pointerdown' ) );

		const script = injectedScript();
		expect( script ).not.toBeNull();
		script.onerror();

		await expect( promise ).resolves.toBeUndefined();
	} );
} );
