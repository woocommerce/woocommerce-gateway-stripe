/**
 * The bootstrap must wait for ./init's WP/WC globals before loading the init
 * chunk, so a "defer render-blocking JS" optimizer can't make it throw at load.
 * The global list is derived from the build's declared dependencies.
 */

// Mock ./init so importing the bootstrap doesn't pull the whole checkout graph;
// count how many times it loads.
jest.mock(
	'../init',
	() => {
		global.__upeInitLoadCount = ( global.__upeInitLoadCount || 0 ) + 1;
		return {};
	},
	{ virtual: true }
);

const setDependenciesReady = () => {
	window.wp = { data: {}, i18n: {} };
	window.wc = { wcSettings: {} };
};

const clearDependencies = () => {
	delete window.wp;
	delete window.wc;
	delete window.jQuery;
	delete window.React;
	delete window.ReactDOM;
	delete global.wc_stripe_upe_params;
};

const flushPromises = () => new Promise( ( resolve ) => resolve() );

describe( 'classic UPE bootstrap', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		global.__upeInitLoadCount = 0;
		clearDependencies();
	} );

	afterEach( () => {
		clearDependencies();
		jest.useRealTimers();
		jest.resetModules();
	} );

	it( 'loads init immediately when dependencies are already present', async () => {
		setDependenciesReady();

		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();
		} );

		expect( global.__upeInitLoadCount ).toBe( 1 );
	} );

	it( 'does not load init until the dependencies appear', async () => {
		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();
			expect( global.__upeInitLoadCount ).toBe( 0 );

			setDependenciesReady();
			jest.advanceTimersByTime( 50 );
			await flushPromises();

			expect( global.__upeInitLoadCount ).toBe( 1 );
		} );
	} );

	it( 'loads init once dependencies are partially then fully ready', async () => {
		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();

			// Only wp present — wc.wcSettings still missing: stay gated.
			window.wp = { data: {}, i18n: {} };
			jest.advanceTimersByTime( 50 );
			await flushPromises();
			expect( global.__upeInitLoadCount ).toBe( 0 );

			window.wc = { wcSettings: {} };
			jest.advanceTimersByTime( 50 );
			await flushPromises();
			expect( global.__upeInitLoadCount ).toBe( 1 );
		} );
	} );

	it( 'gives up waiting and loads init after the timeout', async () => {
		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();
			expect( global.__upeInitLoadCount ).toBe( 0 );

			jest.advanceTimersByTime( 10000 );
			await flushPromises();

			expect( global.__upeInitLoadCount ).toBe( 1 );
		} );
	} );

	it( 'loads init only once after readiness', async () => {
		setDependenciesReady();

		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();
			jest.advanceTimersByTime( 500 );
			await flushPromises();
		} );

		expect( global.__upeInitLoadCount ).toBe( 1 );
	} );

	it( 'waits for every global derived from the build dependencies', async () => {
		// The gate must cover every wp-* external plus wc-settings, not just the
		// three in the fallback list.
		global.wc_stripe_upe_params = {
			scriptDependencies: [
				'jquery',
				'react',
				'react-dom',
				'wc-settings',
				'wp-api-fetch',
				'wp-data',
				'wp-element',
				'wp-hooks',
				'wp-i18n',
				'wp-polyfill',
			],
		};

		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();

			// wp.element still missing: stay gated.
			window.jQuery = {};
			window.React = {};
			window.ReactDOM = {};
			window.wp = { data: {}, i18n: {}, hooks: {}, apiFetch: {} };
			window.wc = { wcSettings: {} };
			jest.advanceTimersByTime( 50 );
			await flushPromises();
			expect( global.__upeInitLoadCount ).toBe( 0 );

			window.wp.element = {};
			jest.advanceTimersByTime( 50 );
			await flushPromises();
			expect( global.__upeInitLoadCount ).toBe( 1 );
		} );
	} );

	it( 'does not gate on ignored handles (wp-polyfill/stripe/wc-checkout)', async () => {
		global.wc_stripe_upe_params = {
			scriptDependencies: [
				'stripe',
				'wc-checkout',
				'wp-polyfill',
				'wp-data',
				'wc-settings',
			],
		};

		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();

			// Ignored handles set no global; gating on them would hang.
			window.wp = { data: {} };
			window.wc = { wcSettings: {} };
			jest.advanceTimersByTime( 50 );
			await flushPromises();

			expect( global.__upeInitLoadCount ).toBe( 1 );
		} );
	} );
} );
