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

		it( 'flushes recorded events via sendBeacon to the configured endpoint with the nonce as a _wpnonce query param', () => {
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.flush( 'manual' );

			expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
			expect( sendBeaconSpy.mock.calls[ 0 ][ 0 ] ).toBe(
				'/wp-json/wc/v3/wc_stripe/diagnostics/events?_wpnonce=test-nonce'
			);
		} );

		it( 'percent-encodes the nonce when appending it as a query param', () => {
			window.wcStripeDiag.nonce = 'a/b c+d';
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.flush( 'manual' );

			expect( sendBeaconSpy.mock.calls[ 0 ][ 0 ] ).toBe(
				'/wp-json/wc/v3/wc_stripe/diagnostics/events?_wpnonce=a%2Fb%20c%2Bd'
			);
		} );

		it( 'omits the _wpnonce query param when no nonce is configured', () => {
			delete window.wcStripeDiag.nonce;
			const recorder = makeRecorder();
			recorder.boot();
			recorder.record( 'element.ready', { element_type: 'card' } );
			recorder.flush( 'manual' );

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

		describe( 'attach() integration with Stripe Element instances', () => {
			function makeFakeElement() {
				const handlers = {};
				return {
					on: ( event, cb ) => {
						handlers[ event ] = cb;
					},
					emit: ( event, payload ) => handlers[ event ]?.( payload ),
				};
			}

			it( 'records element.ready with element_type when the Stripe Element fires ready', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const element = makeFakeElement();

				recorder.attach( element, 'card' );
				element.emit( 'ready' );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events ).toEqual( [
					expect.objectContaining( {
						kind: 'element.ready',
						data: { element_type: 'card' },
					} ),
				] );
			} );

			it( 'attachAfterReady records a synthetic element.ready (the underlying ready event has already fired)', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const element = makeFakeElement();

				recorder.attachAfterReady( element, 'payment' );
				// Note: NOT emitting 'ready' on the fake element — the whole
				// point is that Stripe has already fired ready before we attach.
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ] ).toMatchObject( {
					kind: 'element.ready',
					data: { element_type: 'payment' },
				} );
			} );

			it( 'attachAfterReady still subscribes to lifecycle events that fire later (focus/blur/change/loaderror)', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const element = makeFakeElement();

				recorder.attachAfterReady( element, 'payment' );
				element.emit( 'focus' );
				element.emit( 'blur' );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events.map( ( e ) => e.kind ) ).toEqual( [
					'element.ready', // synthetic
					'element.focus',
					'element.blur',
				] );
			} );

			it( 'records element.focus and element.blur with element_type', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const element = makeFakeElement();

				recorder.attach( element, 'card' );
				element.emit( 'focus' );
				element.emit( 'blur' );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events.map( ( e ) => e.kind ) ).toEqual( [
					'element.focus',
					'element.blur',
				] );
				expect( body.events[ 0 ].data ).toEqual( {
					element_type: 'card',
				} );
				expect( body.events[ 1 ].data ).toEqual( {
					element_type: 'card',
				} );
			} );

			it( 'records element.loaderror with element_type and the projected error fields', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const element = makeFakeElement();

				recorder.attach( element, 'card' );
				element.emit( 'loaderror', {
					elementType: 'card',
					error: {
						type: 'integration_error',
						code: 'integration_error',
						message: 'Element failed to load',
					},
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ] ).toMatchObject( {
					kind: 'element.loaderror',
					data: {
						element_type: 'card',
						error_code: 'integration_error',
						error_type: 'integration_error',
						error_message: 'Element failed to load',
					},
				} );
			} );

			it( 'records element.change with the contract-allowed projection of the Stripe payload', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const element = makeFakeElement();

				recorder.attach( element, 'card' );
				element.emit( 'change', {
					elementType: 'card',
					complete: false,
					empty: false,
					brand: 'visa',
					error: {
						code: 'incomplete_number',
						type: 'validation_error',
						message: 'Your card number is incomplete.',
					},
					// Fields that MUST be filtered out (not in §5.1 allow-list):
					value: { postalCode: '90210' },
					country: 'US',
					classes: { focus: false },
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events ).toHaveLength( 1 );
				expect( body.events[ 0 ].kind ).toBe( 'element.change' );
				expect( body.events[ 0 ].data ).toEqual( {
					element_type: 'card',
					complete: false,
					empty: false,
					valid: false,
					brand: 'visa',
					error_code: 'incomplete_number',
					error_type: 'validation_error',
				} );
			} );
		} );

		describe( 'recordBlocksPaymentSetupStart / End', () => {
			it( 'records start and end events with site, duration_ms, and result_type for a successful payment setup', () => {
				let mockTime = 1000;
				const clock = () => mockTime;
				const recorder = makeRecorder( { now: clock } );
				recorder.boot();

				const handle =
					recorder.recordBlocksPaymentSetupStart(
						'payment_processor'
					);
				mockTime = 1750;
				recorder.recordBlocksPaymentSetupEnd( handle, {
					type: 'success',
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events.map( ( e ) => e.kind ) ).toEqual( [
					'blocks.payment_setup.start',
					'blocks.payment_setup.end',
				] );
				expect( body.events[ 0 ].data ).toEqual( {
					site: 'payment_processor',
				} );
				expect( body.events[ 1 ].data ).toEqual( {
					site: 'payment_processor',
					duration_ms: 750,
					result_type: 'success',
					error_message: undefined,
				} );
			} );

			it( 'records error_message on end when the result carries one', () => {
				let mockTime = 0;
				const clock = () => mockTime;
				const recorder = makeRecorder( { now: clock } );
				recorder.boot();

				const handle =
					recorder.recordBlocksPaymentSetupStart(
						'checkout_sessions'
					);
				mockTime = 100;
				recorder.recordBlocksPaymentSetupEnd( handle, {
					type: 'error',
					message: 'Your payment information is incomplete.',
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				const endEvent = body.events.find(
					( e ) => e.kind === 'blocks.payment_setup.end'
				);
				expect( endEvent.data ).toMatchObject( {
					site: 'checkout_sessions',
					duration_ms: 100,
					result_type: 'error',
					error_message: 'Your payment information is incomplete.',
				} );
			} );
		} );

		describe( 'attachExpress() integration with the Express Checkout Element', () => {
			function makeFakeEce() {
				const handlers = {};
				return {
					on: ( event, cb ) => {
						handlers[ event ] = cb;
					},
					emit: ( event, payload ) => handlers[ event ]?.( payload ),
				};
			}

			it( 'records paymentmethod with wallet_type and payment_method_type', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const eceButton = makeFakeEce();

				recorder.attachExpress( eceButton );
				eceButton.emit( 'paymentmethod', {
					expressPaymentType: 'link',
					paymentMethod: {
						type: 'card',
						card: { brand: 'visa', last4: '4242' },
					},
					billingDetails: {
						name: 'Jane Doe',
						email: 'jane@example.com',
					},
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe( 'express.paymentmethod' );
				expect( body.events[ 0 ].data ).toEqual( {
					wallet_type: 'link',
					payment_method_type: 'card',
				} );
			} );

			it( 'records shippingaddresschange with wallet_type and country (drops other address fields)', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const eceButton = makeFakeEce();

				recorder.attachExpress( eceButton );
				eceButton.emit( 'shippingaddresschange', {
					expressPaymentType: 'google_pay',
					address: {
						country: 'CA',
						postalCode: 'M5V 0J5',
						city: 'Toronto',
						state: 'ON',
					},
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe(
					'express.shippingaddresschange'
				);
				expect( body.events[ 0 ].data ).toEqual( {
					wallet_type: 'google_pay',
					country: 'CA',
				} );
			} );

			it( 'records click, confirm, cancel, and shippingratechange with wallet_type', () => {
				const recorder = makeRecorder();
				recorder.boot();
				const eceButton = makeFakeEce();

				recorder.attachExpress( eceButton );
				eceButton.emit( 'click', { expressPaymentType: 'apple_pay' } );
				eceButton.emit( 'confirm', {
					expressPaymentType: 'apple_pay',
				} );
				eceButton.emit( 'cancel', {
					expressPaymentType: 'apple_pay',
				} );
				eceButton.emit( 'shippingratechange', {
					expressPaymentType: 'apple_pay',
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events.map( ( e ) => e.kind ) ).toEqual( [
					'express.click',
					'express.confirm',
					'express.cancel',
					'express.shippingratechange',
				] );
				body.events.forEach( ( e ) => {
					expect( e.data ).toEqual( { wallet_type: 'apple_pay' } );
				} );
			} );
		} );

		describe( 'recordExpressEvent() — call-site capture for the Blocks ECE React component', () => {
			it( 'records click, cancel, and shippingratechange with wallet_type', () => {
				const recorder = makeRecorder();
				recorder.boot();

				recorder.recordExpressEvent( 'click', {
					expressPaymentType: 'apple_pay',
				} );
				recorder.recordExpressEvent( 'cancel', {
					expressPaymentType: 'apple_pay',
				} );
				recorder.recordExpressEvent( 'shippingratechange', {
					expressPaymentType: 'apple_pay',
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events.map( ( e ) => e.kind ) ).toEqual( [
					'express.click',
					'express.cancel',
					'express.shippingratechange',
				] );
				body.events.forEach( ( e ) => {
					expect( e.data ).toEqual( { wallet_type: 'apple_pay' } );
				} );
			} );

			it( 'records shippingaddresschange with wallet_type and country, dropping other address fields', () => {
				const recorder = makeRecorder();
				recorder.boot();

				recorder.recordExpressEvent( 'shippingaddresschange', {
					expressPaymentType: 'google_pay',
					address: {
						country: 'CA',
						postalCode: 'M5V 0J5',
						city: 'Toronto',
						state: 'ON',
					},
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe(
					'express.shippingaddresschange'
				);
				expect( body.events[ 0 ].data ).toEqual( {
					wallet_type: 'google_pay',
					country: 'CA',
				} );
			} );

			it( 'records confirm with wallet_type and payment_method_type when paymentMethod is present (Blocks merges paymentmethod into onConfirm)', () => {
				const recorder = makeRecorder();
				recorder.boot();

				recorder.recordExpressEvent( 'confirm', {
					expressPaymentType: 'link',
					paymentMethod: {
						type: 'card',
						card: { brand: 'visa', last4: '4242' },
					},
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe( 'express.confirm' );
				expect( body.events[ 0 ].data ).toEqual( {
					wallet_type: 'link',
					payment_method_type: 'card',
				} );
			} );

			it( 'records confirm with only wallet_type when paymentMethod is absent', () => {
				const recorder = makeRecorder();
				recorder.boot();

				recorder.recordExpressEvent( 'confirm', {
					expressPaymentType: 'apple_pay',
				} );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].data ).toEqual( {
					wallet_type: 'apple_pay',
				} );
			} );

			it( 'tolerates an undefined payload (no event payload from React)', () => {
				const recorder = makeRecorder();
				recorder.boot();

				recorder.recordExpressEvent( 'click' );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe( 'express.click' );
				expect( body.events[ 0 ].data ).toEqual( {
					wallet_type: undefined,
				} );
			} );
		} );

		describe( 'aroundStripeCall() — call-site capture for methods we cannot wrap', () => {
			it( 'records invoke + resolve around a successful Stripe-shaped call (e.g. createPaymentMethod)', async () => {
				const recorder = makeRecorder();
				recorder.boot();

				const stripeResponse = {
					paymentMethod: { id: 'pm_test_123', type: 'card' },
				};
				const result = await recorder.aroundStripeCall(
					'createPaymentMethod',
					() => Promise.resolve( stripeResponse )
				);
				recorder.flush( 'manual' );

				expect( result ).toBe( stripeResponse );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				const kinds = body.events.map( ( e ) => e.kind );
				expect( kinds ).toEqual( [
					'stripe.createPaymentMethod.invoke',
					'stripe.createPaymentMethod.resolve',
				] );

				const resolve = body.events.find(
					( e ) => e.kind === 'stripe.createPaymentMethod.resolve'
				);
				expect( resolve.data ).toMatchObject( {
					method: 'createPaymentMethod',
					payment_method_type: 'card',
					has_error: false,
				} );
			} );

			it( 'records the resolve event with error fields when Stripe returns { error }', async () => {
				const recorder = makeRecorder();
				recorder.boot();

				await recorder.aroundStripeCall( 'createPaymentMethod', () =>
					Promise.resolve( {
						error: {
							type: 'card_error',
							code: 'incorrect_number',
							message: 'Your card number is incorrect.',
						},
					} )
				);
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				const resolve = body.events.find(
					( e ) => e.kind === 'stripe.createPaymentMethod.resolve'
				);
				expect( resolve.data ).toMatchObject( {
					method: 'createPaymentMethod',
					has_error: true,
					error_type: 'card_error',
					error_code: 'incorrect_number',
				} );
			} );

			it( 'records throw when the wrapped call throws and re-throws the error', async () => {
				const recorder = makeRecorder();
				recorder.boot();

				await expect(
					recorder.aroundStripeCall( 'createPaymentMethod', () =>
						Promise.reject( new Error( 'network down' ) )
					)
				).rejects.toThrow( 'network down' );

				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				const thrown = body.events.find(
					( e ) => e.kind === 'stripe.createPaymentMethod.throw'
				);
				expect( thrown.data ).toMatchObject( {
					method: 'createPaymentMethod',
					error_message: 'network down',
				} );
			} );
		} );

		// The no-console rule fires on console.warn/error calls below — these are
		// intentional, exercising the recorder's console-interception behavior.
		/* eslint-disable no-console */
		describe( 'parent-frame console interception', () => {
			it( 'records console.warn calls as console.warn events with the message', () => {
				const recorder = makeRecorder();
				recorder.boot();

				console.warn(
					'[Stripe.js] invalid country code "AS"; "AS" is not a valid 2-letter country code'
				);
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe( 'console.warn' );
				expect( body.events[ 0 ].data ).toEqual( {
					message:
						'[Stripe.js] invalid country code "AS"; "AS" is not a valid 2-letter country code',
					source: 'parent_frame',
				} );
			} );

			it( 'records console.error calls as console.error events', () => {
				const recorder = makeRecorder();
				recorder.boot();

				console.error( 'something exploded' );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].kind ).toBe( 'console.error' );
				expect( body.events[ 0 ].data.message ).toBe(
					'something exploded'
				);
			} );

			it( 'truncates messages to 500 chars', () => {
				const recorder = makeRecorder();
				recorder.boot();

				const longMessage = 'x'.repeat( 1000 );
				console.warn( longMessage );
				recorder.flush( 'manual' );

				const body = JSON.parse( sendBeaconSpy.mock.calls[ 0 ][ 1 ] );
				expect( body.events[ 0 ].data.message ).toHaveLength( 500 );
			} );

			it( 'destroy() restores the original console methods', () => {
				const originalWarn = console.warn;
				const originalError = console.error;

				const recorder = makeRecorder();
				recorder.boot();

				expect( console.warn ).not.toBe( originalWarn );
				expect( console.error ).not.toBe( originalError );

				recorder.destroy();

				expect( console.warn ).toBe( originalWarn );
				expect( console.error ).toBe( originalError );
			} );

			it( 'still calls through to the original console method (does not swallow output)', () => {
				const originalWarn = console.warn;
				const warnSpy = jest.fn();
				console.warn = warnSpy;

				const recorder = makeRecorder();
				recorder.boot();
				console.warn( 'pass through me' );

				expect( warnSpy ).toHaveBeenCalledWith( 'pass through me' );

				console.warn = originalWarn;
			} );
		} );
		/* eslint-enable no-console */

		describe( 'sessionStorage persistence and replay across page reloads (3DS)', () => {
			const STORAGE_KEY =
				'wc_stripe_diag_550e8400-e29b-41d4-a716-446655440000';

			it( 'flushes the buffer on pagehide AND clears the persisted copy so the next boot does not double-send (3DS replay safety net only fires on torn-down tabs)', () => {
				// First page load: record + pagehide fires the flush.
				const r1 = makeRecorder();
				r1.boot();
				r1.record( 'element.ready', { element_type: 'card' } );

				window.dispatchEvent( new Event( 'pagehide' ) );

				// Pagehide must have triggered exactly one sendBeacon.
				expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );

				// And the persisted bufferedEvents must have been cleared so
				// the next boot does NOT replay them.
				const stored = JSON.parse(
					window.sessionStorage.getItem( STORAGE_KEY )
				);
				expect( stored?.bufferedEvents ).toBeUndefined();
				expect( stored?.traceStartMs ).toEqual( expect.any( Number ) );

				// Simulate a fresh page load — boot a new recorder. It must
				// NOT trigger another sendBeacon for the events from r1.
				const r2 = makeRecorder();
				r2.boot();
				expect( sendBeaconSpy ).toHaveBeenCalledTimes( 1 );
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
