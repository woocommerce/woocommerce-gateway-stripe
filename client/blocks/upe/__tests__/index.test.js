// The bootstrap must wait for the WP/WC globals before loading the real init
// chunk, so a defer-all optimizer can't make it throw. See STRIPE-1236.

// Mock init so importing the bootstrap doesn't pull the Blocks graph; count loads.
jest.mock(
	'../init',
	() => {
		global.__upeBlocksInitLoadCount =
			( global.__upeBlocksInitLoadCount || 0 ) + 1;
		return {};
	},
	{ virtual: true }
);

const setDependenciesReady = () => {
	window.wp = { data: {}, i18n: {} };
	window.wc = { wcSettings: {}, wcBlocksRegistry: {} };
};

const clearDependencies = () => {
	delete window.wp;
	delete window.wc;
};

const flushPromises = () => new Promise( ( resolve ) => resolve() );

describe( 'Blocks UPE bootstrap', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		global.__upeBlocksInitLoadCount = 0;
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

		expect( global.__upeBlocksInitLoadCount ).toBe( 1 );
	} );

	it( 'does not load init until the dependencies appear', async () => {
		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();
			expect( global.__upeBlocksInitLoadCount ).toBe( 0 );

			setDependenciesReady();
			jest.advanceTimersByTime( 50 );
			await flushPromises();

			expect( global.__upeBlocksInitLoadCount ).toBe( 1 );
		} );
	} );

	it( 'stays gated until the Blocks registry is also present', async () => {
		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();

			// Everything but wcBlocksRegistry — registration would throw, so stay gated.
			window.wp = { data: {}, i18n: {} };
			window.wc = { wcSettings: {} };
			jest.advanceTimersByTime( 50 );
			await flushPromises();
			expect( global.__upeBlocksInitLoadCount ).toBe( 0 );

			window.wc.wcBlocksRegistry = {};
			jest.advanceTimersByTime( 50 );
			await flushPromises();
			expect( global.__upeBlocksInitLoadCount ).toBe( 1 );
		} );
	} );

	it( 'gives up waiting and loads init after the timeout', async () => {
		await jest.isolateModulesAsync( async () => {
			require( '../index' );
			await flushPromises();
			expect( global.__upeBlocksInitLoadCount ).toBe( 0 );

			jest.advanceTimersByTime( 10000 );
			await flushPromises();

			expect( global.__upeBlocksInitLoadCount ).toBe( 1 );
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

		expect( global.__upeBlocksInitLoadCount ).toBe( 1 );
	} );
} );
