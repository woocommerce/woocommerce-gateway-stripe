import { ensureStripeSdk } from 'wcstripe/express-checkout/utils/load-stripe-sdk';

describe( 'ensureStripeSdk', () => {
	const SDK_URL = 'https://js.stripe.com/dahlia/stripe.js';

	const injectedScript = () =>
		document.querySelector( `script[src="${ SDK_URL }"]` );

	beforeEach( () => {
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
