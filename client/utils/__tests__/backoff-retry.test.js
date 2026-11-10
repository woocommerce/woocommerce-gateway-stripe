import { createBackoffRetry } from '../backoff-retry';

// babel's transform-runtime turns bare setTimeout and Date.now into these core-js imports, which bind
// the real functions when they load, so jest's fake timers never see them.
jest.mock( '@babel/runtime-corejs3/core-js-stable/set-timeout', () =>
	jest.fn()
);
jest.mock( '@babel/runtime-corejs3/core-js-stable/date/now', () => jest.fn() );
const mockSetTimeout = jest.requireMock(
	'@babel/runtime-corejs3/core-js-stable/set-timeout'
);
const mockDateNow = jest.requireMock(
	'@babel/runtime-corejs3/core-js-stable/date/now'
);

const WINDOW = 70000;

describe( 'createBackoffRetry', () => {
	let timers;
	let now;
	let action;
	let retry;

	const pendingDelays = () =>
		timers.filter( ( { done } ) => ! done ).map( ( { delay } ) => delay );
	// Lets the pending retry fire, as if its delay had passed.
	const runPendingRetry = () => {
		for ( const timer of timers.filter( ( { done } ) => ! done ) ) {
			now += timer.delay;
			timer.done = true;
			timer.callback();
		}
	};

	beforeEach( () => {
		timers = [];
		now = 1_000_000;
		mockSetTimeout.mockImplementation( ( callback, delay ) => {
			timers.push( { callback, delay, done: false } );
			return timers.length;
		} );
		mockDateNow.mockImplementation( () => now );
		jest.spyOn( global, 'clearTimeout' ).mockImplementation( ( id ) => {
			if ( timers[ id - 1 ] ) {
				timers[ id - 1 ].done = true;
			}
		} );
		action = jest.fn();
		retry = createBackoffRetry( action );
	} );

	it( 'runs the action once the delay passes', () => {
		expect( retry.schedule( WINDOW ) ).toBe( true );
		expect( action ).not.toHaveBeenCalled();

		runPendingRetry();

		expect( action ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'doubles the delay, shortens the last one to end with the window, then refuses', () => {
		const delays = [];
		while ( retry.schedule( WINDOW ) ) {
			delays.push( ...pendingDelays() );
			runPendingRetry();
		}

		// 250 ms doubling to 32 s, then the rest of the 70 s window.
		expect( delays ).toEqual( [
			250, 500, 1000, 2000, 4000, 8000, 16000, 32000, 6250,
		] );
		expect( action ).toHaveBeenCalledTimes( 9 );
	} );

	it.each( [
		[ 'NaN', NaN ],
		[ 'zero', 0 ],
		[ 'negative', -1000 ],
		[ 'infinite', Infinity ],
	] )( 'refuses a window that is %s', ( _label, windowMs ) => {
		expect( retry.schedule( windowMs ) ).toBe( false );
		expect( pendingDelays() ).toEqual( [] );
	} );

	it( 'never waits longer than a browser timer can', () => {
		const delays = [];
		for ( let i = 0; i < 30; i++ ) {
			retry.schedule( Number.MAX_SAFE_INTEGER );
			delays.push( ...pendingDelays() );
			runPendingRetry();
		}

		expect( Math.max( ...delays ) ).toBe( 2147483647 );
	} );

	it( 'uses the given base delay', () => {
		retry = createBackoffRetry( action, { baseDelay: 100 } );

		retry.schedule( WINDOW );
		runPendingRetry();
		retry.schedule( WINDOW );

		expect( pendingDelays() ).toEqual( [ 200 ] );
	} );

	it( 'starts over after a reset', () => {
		retry.schedule( WINDOW );
		runPendingRetry();
		retry.schedule( WINDOW );
		runPendingRetry();

		retry.reset();
		retry.schedule( WINDOW );

		expect( pendingDelays() ).toEqual( [ 250 ] );
	} );

	it( 'drops the pending retry when cancelled, keeping its place in the back-off', () => {
		retry.schedule( WINDOW );

		retry.cancel();

		expect( pendingDelays() ).toEqual( [] );
		expect( action ).not.toHaveBeenCalled();

		retry.schedule( WINDOW );

		expect( pendingDelays() ).toEqual( [ 500 ] );
	} );
} );
