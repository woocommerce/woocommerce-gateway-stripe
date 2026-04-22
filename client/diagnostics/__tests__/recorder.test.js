import { Recorder } from 'wcstripe/diagnostics/recorder';

describe( 'Recorder', () => {
	let sendBeaconSpy;
	let activeRecorders;

	function makeRecorder( opts ) {
		const recorder = new Recorder( opts );
		activeRecorders.push( recorder );
		return recorder;
	}

	beforeEach( () => {
		sendBeaconSpy = jest.fn().mockReturnValue( true );
		Object.defineProperty( navigator, 'sendBeacon', {
			value: sendBeaconSpy,
			writable: true,
			configurable: true,
		} );
		delete window.wcStripeDiag;
		window.sessionStorage.clear();
		activeRecorders = [];
	} );

	afterEach( () => {
		activeRecorders.forEach( ( r ) => r.destroy() );
		jest.restoreAllMocks();
	} );

	describe( 'when wcStripeDiag is absent', () => {
		it( 'does not flush events to the server', () => {
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.flush( 'manual' );

			expect( sendBeaconSpy ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'when wcStripeDiag is active', () => {
		beforeEach( () => {
			window.wcStripeDiag = {
				active: true,
				sessionId: '550e8400-e29b-41d4-a716-446655440000',
				nonce: 'test-nonce',
				endpoint: '/wp-json/wc/v3/wc_stripe/diagnostics/events',
			};
		} );

		it( 'flushes recorded events via sendBeacon to the configured endpoint', () => {
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.flush( 'manual' );

			expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
			expect( sendBeaconSpy.mock.calls[ 0 ][ 0 ] ).toBe(
				'/wp-json/wc/v3/wc_stripe/diagnostics/events'
			);
		} );

		it( 'sends a body with diag_session_id and events shaped per contract', () => {
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.flush( 'manual' );

			const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
			expect( body ).toEqual( {
				diag_session_id: '550e8400-e29b-41d4-a716-446655440000',
				events: [
					{
						t: expect.any( Number ),
						kind: 'element.ready',
						data: { element_type: 'card' },
					},
				],
			} );
			expect( body.events[ 0 ].t ).toBeGreaterThanOrEqual( 0 );
		} );

		it( 'anchors t to a trace-start timestamp persisted across recorder boots within the same session', () => {
			let mockTime = 1000;
			const clock = () => mockTime;

			// First page load — record at the trace start.
			const r1 = makeRecorder( { now: clock } );
			r1.boot();
			r1.record( 'element.ready', { element_type: 'card' } );
			r1.flush( 'manual' );

			// Time passes (e.g., shopper goes through 3DS challenge).
			mockTime = 5000;

			// Second page load (same diagnostics session) — fresh Recorder instance.
			const r2 = makeRecorder( { now: clock } );
			r2.boot();
			r2.record( 'element.change', { element_type: 'card' } );
			r2.flush( 'manual' );

			const body1 = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
			const body2 = JSON.parse( sendBeaconSpy.mock.calls[ 1 ][ 1 ] );

			// First event sits at the trace start.
			expect( body1.events[ 0 ].t ).toBe( 0 );
			// Second event's t is anchored to the original trace start (4000ms later),
			// NOT the second recorder boot (which would make it 0).
			expect( body2.events[ 0 ].t ).toBe( 4000 );
		} );

		it( 'schedules an idle flush 5 seconds after record() is called', () => {
			let scheduledFn = null;
			let scheduledMs = null;
			const setTimer = ( fn, ms ) => {
				scheduledFn = fn;
				scheduledMs = ms;
				return 1;
			};
			const clearTimer = () => {
				scheduledFn = null;
				scheduledMs = null;
			};

			const recorder = makeRecorder( { setTimer, clearTimer } );
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );

			expect( scheduledMs ).toBe( 5000 );
			expect( sendBeaconSpy ).not.toHaveBeenCalled();

			// Manually fire the scheduled callback (simulates the timer elapsing).
			scheduledFn();

			expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'resets the idle timer each time record() is called', () => {
			const cleared = [];
			let nextId = 1;
			let scheduledFn = null;
			const setTimer = ( fn ) => {
				scheduledFn = fn;
				return nextId++;
			};
			const clearTimer = ( id ) => {
				cleared.push( id );
				scheduledFn = null;
			};

			const recorder = makeRecorder( { setTimer, clearTimer } );
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.record( 'element.change', { element_type: 'card' } );

			// First record scheduled timer id=1; second record cleared it (id=1)
			// then scheduled id=2.
			expect( cleared ).toContain( 1 );

			// Firing the latest scheduled callback flushes both events together.
			scheduledFn();

			expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
			const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
			expect( body.events ).toHaveLength( 2 );
		} );

		it( 'auto-flushes the buffer when the pagehide event fires', () => {
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );

			window.dispatchEvent( new Event( 'pagehide' ) );

			expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'assigns monotonically non-decreasing t to recorded events', () => {
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.record( 'element.change', { element_type: 'card' } );
			recorder.record( 'element.focus', { element_type: 'card' } );
			recorder.flush( 'manual' );

			const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
			expect( body.events ).toHaveLength( 3 );
			expect( body.events[ 0 ].t ).toBeLessThanOrEqual(
				body.events[ 1 ].t
			);
			expect( body.events[ 1 ].t ).toBeLessThanOrEqual(
				body.events[ 2 ].t
			);
		} );

		describe( 'sessionStorage persistence and replay across page reloads (3DS)', () => {
			const STORAGE_KEY =
				'wc_stripe_diag_550e8400-e29b-41d4-a716-446655440000';

			it( 'persists the unflushed buffer to sessionStorage on pagehide (so a 3DS redirect cannot lose events)', () => {
				const recorder = makeRecorder();
				recorder.boot();
				recorder.record( 'element.ready', { element_type: 'card' } );

				window.dispatchEvent( new Event( 'pagehide' ) );

				const stored = JSON.parse(
					window.sessionStorage.getItem( STORAGE_KEY )
				);
				expect( stored.bufferedEvents ).toEqual( [
					expect.objectContaining( {
						kind: 'element.ready',
						data: { element_type: 'card' },
					} ),
				] );
			} );

			it( 'replays buffered events from sessionStorage on boot (return from 3DS)', () => {
				window.sessionStorage.setItem(
					STORAGE_KEY,
					JSON.stringify( {
						traceStartMs: 1000,
						bufferedEvents: [
							{
								t: 100,
								kind: 'element.ready',
								data: { element_type: 'card' },
							},
						],
					} )
				);

				const recorder = makeRecorder();
				recorder.boot();

				expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events ).toEqual( [
					{
						t: 100,
						kind: 'element.ready',
						data: { element_type: 'card' },
					},
				] );
			} );

			it( 'clears sessionStorage bufferedEvents after replay so it cannot replay twice', () => {
				window.sessionStorage.setItem(
					STORAGE_KEY,
					JSON.stringify( {
						traceStartMs: 1000,
						bufferedEvents: [
							{
								t: 100,
								kind: 'element.ready',
								data: { element_type: 'card' },
							},
						],
					} )
				);

				const recorder = makeRecorder();
				recorder.boot();

				const stored = JSON.parse(
					window.sessionStorage.getItem( STORAGE_KEY )
				);
				expect( stored.traceStartMs ).toBe( 1000 );
				expect( stored.bufferedEvents ).toBeUndefined();
			} );
		} );
	} );
} );
