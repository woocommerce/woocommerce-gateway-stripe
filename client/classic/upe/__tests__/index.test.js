/**
 * Tests for the classic-checkout UPE bootstrap.
 *
 * The bootstrap exists so a host "defer render-blocking JS" optimizer can't make
 * our bundle evaluate before window.wp.data / window.wp.i18n / window.wc.wcSettings
 * exist (which throws and aborts the whole checkout script). It must wait for
 * those globals before loading the real init chunk. See STRIPE-1236.
 */

// Mock the real init module so importing the bootstrap doesn't pull the whole
// checkout graph; count how many times it's loaded.
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
} );
