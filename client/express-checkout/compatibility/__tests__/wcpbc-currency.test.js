import { applyFilters, removeAllFilters } from '@wordpress/hooks';

const FILTER = 'wcstripe.express-checkout.resolved-currency';
const SET_CURRENCY_EVENT = 'wc_price_based_country_set_currency_params';
const RETRIGGER_EVENT = 'wc_price_based_country_ajax_geolocation';

// This codebase configures @babel/transform-runtime with corejs:3, which
// rewrites bare setTimeout calls to a polyfilled module reference that
// jest.useFakeTimers() cannot intercept. We replace that module with a
// manual scheduler so timer-based assertions stay deterministic.
// If this stops working, check the corejs version in babel.config.js;
// the polyfill module path tracks the major version.
// The `mock`-prefixed names below are required: babel-jest hoists
// jest.mock() above all other code, and factory closures can only
// reference outer bindings whose names start with `mock`.
const mockPendingTimers = [];
let mockNextTimerId = 0;
jest.mock( '@babel/runtime-corejs3/core-js-stable/set-timeout', () => {
	const mockSetTimeout = ( callback, delay ) => {
		const id = ++mockNextTimerId;
		mockPendingTimers.push( { id, callback, delay } );
		return id;
	};
	return { __esModule: true, default: mockSetTimeout };
} );
const originalClearTimeout = global.clearTimeout;
global.clearTimeout = ( id ) => {
	const idx = mockPendingTimers.findIndex( ( t ) => t.id === id );
	if ( idx !== -1 ) mockPendingTimers.splice( idx, 1 );
	return originalClearTimeout( id );
};

const advanceTimers = ( ms ) => {
	const ready = [];
	for ( let i = mockPendingTimers.length - 1; i >= 0; i-- ) {
		if ( mockPendingTimers[ i ].delay <= ms ) {
			ready.push( mockPendingTimers[ i ] );
			mockPendingTimers.splice( i, 1 );
		} else {
			mockPendingTimers[ i ].delay -= ms;
		}
	}
	ready.reverse().forEach( ( t ) => t.callback() );
};

const buildJQueryMock = () => {
	const handlers = new Map();
	const triggerCounts = new Map();

	const $body = {
		on: jest.fn( ( event, handler ) => {
			if ( ! handlers.has( event ) ) handlers.set( event, new Set() );
			handlers.get( event ).add( handler );
			return $body;
		} ),
		off: jest.fn( ( event, handler ) => {
			handlers.get( event )?.delete( handler );
			return $body;
		} ),
		triggerHandler: jest.fn( ( event, args ) => {
			triggerCounts.set( event, ( triggerCounts.get( event ) ?? 0 ) + 1 );
			handlers
				.get( event )
				?.forEach( ( h ) => h( {}, ...( args ?? [] ) ) );
			return $body;
		} ),
	};

	const jQuery = jest.fn( () => $body );
	jQuery.__emit = ( event, ...args ) => $body.triggerHandler( event, args );
	jQuery.__triggerCount = ( event ) => triggerCounts.get( event ) ?? 0;
	return jQuery;
};

// The resolver registers its filter as a side effect of being imported.
// Loading once at module top means we share the @wordpress/hooks registry
// with this test's `applyFilters` import; jest.isolateModules would give
// each scope its own hooks instance and the filter would never run.
require( '../wcpbc-currency' );

describe( 'WCPBC currency resolver', () => {
	beforeEach( () => {
		mockPendingTimers.length = 0;
		global.jQuery = buildJQueryMock();
	} );

	afterEach( () => {
		delete global.jQuery;
		delete global.wc_price_based_country_ajax_geo_params;
	} );

	afterAll( () => {
		removeAllFilters( FILTER );
		global.clearTimeout = originalClearTimeout;
	} );

	test( 'returns the upstream value when WCPBC AJAX mode is not active', async () => {
		const result = await applyFilters(
			FILTER,
			Promise.resolve( 'usd' ),
			{}
		);

		expect( result ).toBe( 'usd' );
	} );

	test( 'resolves with the WCPBC-reported currency code', async () => {
		global.wc_price_based_country_ajax_geo_params = {};
		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		global.jQuery.__emit( SET_CURRENCY_EVENT, { code: 'EUR' } );

		await expect( piped ).resolves.toBe( 'eur' );
	} );

	test( 'soft watchdog re-triggers WCPBC geolocation if the event never fires', async () => {
		global.wc_price_based_country_ajax_geo_params = {};
		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		expect( global.jQuery.__triggerCount( RETRIGGER_EVENT ) ).toBe( 0 );

		advanceTimers( 3000 );

		expect( global.jQuery.__triggerCount( RETRIGGER_EVENT ) ).toBe( 1 );

		global.jQuery.__emit( SET_CURRENCY_EVENT, { code: 'CAD' } );

		await expect( piped ).resolves.toBe( 'cad' );
	} );

	test( 'hard watchdog falls back to the upstream value', async () => {
		global.wc_price_based_country_ajax_geo_params = {};
		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		advanceTimers( 6000 );
		await Promise.resolve();

		await expect( piped ).resolves.toBe( 'usd' );
	} );

	test( 'ignores events without a currency code', async () => {
		global.wc_price_based_country_ajax_geo_params = {};
		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		global.jQuery.__emit( SET_CURRENCY_EVENT, {} );
		advanceTimers( 6000 );
		await Promise.resolve();

		await expect( piped ).resolves.toBe( 'usd' );
	} );
} );
