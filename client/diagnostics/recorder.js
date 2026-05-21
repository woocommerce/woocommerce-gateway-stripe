const STORAGE_KEY_PREFIX = 'wc_stripe_diag_';
const IDLE_FLUSH_MS = 5000;
// Server caps a batch at 200 events; flush at the cap so the next record()
// can't push past it and have the controller silently truncate.
const MAX_BUFFER_BEFORE_FLUSH = 200;
// Drop-oldest ceiling so a string of sendBeacon refusals can't grow the buffer unboundedly.
const MAX_BUFFER_SIZE = 500;
const CONSOLE_MESSAGE_MAX_CHARS = 500;
const CONSOLE_LEVELS = [ 'warn', 'error' ];

export class Recorder {
	constructor( {
		now = () => Date.now(),
		setTimer = ( fn, ms ) => setTimeout( fn, ms ),
		clearTimer = ( id ) => clearTimeout( id ),
	} = {} ) {
		this.now = now;
		this.setTimer = setTimer;
		this.clearTimer = clearTimer;
		this.config = null;
		this.buffer = [];
		this.traceStartMs = 0;
		this.idleTimer = null;
		this.pagehideHandler = null;
		this.originalConsole = null;
	}

	boot() {
		this.config = window.wcStripeDiag || null;
		if ( ! this.config?.active ) {
			return;
		}

		const storageKey = STORAGE_KEY_PREFIX + this.config.sessionId;
		const persisted = readStoredState( storageKey );
		if ( persisted ) {
			this.traceStartMs = persisted.traceStartMs;
		} else {
			this.traceStartMs = this.now();
			writeStoredState( storageKey, { traceStartMs: this.traceStartMs } );
		}

		// Replay any buffer left over from a prior pageload (e.g. 3DS redirect)
		// then clear it from sessionStorage to prevent a second replay on the next boot.
		if ( persisted?.bufferedEvents?.length ) {
			const payload = buildIngestBlob( {
				diag_session_id: this.config.sessionId,
				events: persisted.bufferedEvents,
			} );
			const queued = navigator.sendBeacon(
				buildIngestUrl( this.config ),
				payload
			);
			if ( queued ) {
				const cleared = { ...persisted };
				delete cleared.bufferedEvents;
				writeStoredState( storageKey, cleared );
			}
		}

		this.pagehideHandler = () => this._handlePagehide();
		window.addEventListener( 'pagehide', this.pagehideHandler );

		this._interceptConsole();
	}

	_interceptConsole() {
		if ( this.originalConsole ) {
			return; // idempotent
		}
		this.originalConsole = {};
		for ( const level of CONSOLE_LEVELS ) {
			// eslint-disable-next-line no-console
			this.originalConsole[ level ] = console[ level ];
			const orig = this.originalConsole[ level ];
			// eslint-disable-next-line no-console
			console[ level ] = ( ...args ) => {
				this.record( `console.${ level }`, {
					message: String( args[ 0 ] ?? '' ).slice(
						0,
						CONSOLE_MESSAGE_MAX_CHARS
					),
					source: 'parent_frame',
				} );
				return orig.apply( console, args );
			};
		}
	}

	_restoreConsole() {
		if ( ! this.originalConsole ) return;
		for ( const level of CONSOLE_LEVELS ) {
			// eslint-disable-next-line no-console
			console[ level ] = this.originalConsole[ level ];
		}
		this.originalConsole = null;
	}

	_handlePagehide() {
		// Persist current buffer BEFORE attempting the flush so a torn-down
		// browser tab can still replay these events on the return page.
		const hadBuffer = this.buffer.length > 0;
		const storageKey = STORAGE_KEY_PREFIX + this.config.sessionId;
		if ( hadBuffer ) {
			const existing = readStoredState( storageKey ) || {};
			writeStoredState( storageKey, {
				...existing,
				bufferedEvents: [ ...this.buffer ],
			} );
		}
		this.flush();
		// Only clear the persisted copy if flush() actually drained the
		// buffer. If sendBeacon refused (returned false), flush() preserves
		// `this.buffer`, but on pagehide that in-memory buffer is useless —
		// the page is unloading. Keeping the sessionStorage copy means the
		// next-boot replay path can recover the events. If the tab is torn
		// down BEFORE this line runs, the persisted copy also survives.
		if ( hadBuffer && this.buffer.length === 0 ) {
			const existing = readStoredState( storageKey );
			if ( existing?.bufferedEvents ) {
				const cleared = { ...existing };
				delete cleared.bufferedEvents;
				writeStoredState( storageKey, cleared );
			}
		}
	}

	/**
	 * Wrap a Stripe API call with .invoke / .resolve / .throw events,
	 * preserving the original return value (or rethrown error).
	 *
	 * @param {string}   method The Stripe method name being instrumented.
	 * @param {Function} fn     A zero-arg function that performs the Stripe call and returns its Promise.
	 * @return {Promise} The promise returned by fn(), with .invoke / .resolve / .throw recorded around it.
	 */
	aroundStripeCall( method, fn ) {
		this.record( `stripe.${ method }.invoke`, { method } );
		return Promise.resolve()
			.then( fn )
			.then(
				( result ) => {
					this.record( `stripe.${ method }.resolve`, {
						method,
						intent_status:
							result?.paymentIntent?.status ??
							result?.setupIntent?.status,
						payment_method_type: result?.paymentMethod?.type,
						has_error: !! result?.error,
						error_type: result?.error?.type,
						error_code: result?.error?.code,
						error_decline_code: result?.error?.decline_code,
					} );
					return result;
				},
				( err ) => {
					this.record( `stripe.${ method }.throw`, {
						method,
						error_message: err?.message,
					} );
					throw err;
				}
			);
	}

	recordBlocksPaymentSetupStart( site ) {
		this.record( 'blocks.payment_setup.start', { site } );
		return { startMs: this.now(), site };
	}

	recordBlocksPaymentSetupEnd( handle, result = {} ) {
		this.record( 'blocks.payment_setup.end', {
			site: handle.site,
			duration_ms: this.now() - handle.startMs,
			result_type: result.type,
			error_message: result.message,
		} );
	}

	attachExpress( eceButton ) {
		const events = [
			'click',
			'confirm',
			'cancel',
			'shippingratechange',
			'shippingaddresschange',
			'paymentmethod',
		];
		for ( const ev of events ) {
			eceButton.on( ev, ( payload = {} ) => {
				this.recordExpressEvent( ev, payload );
			} );
		}
	}

	/**
	 * Record a single Express Checkout Element event. Used by the Blocks ECE
	 * surface where events arrive via React props rather than `.on()`.
	 *
	 * @param {string} eventName One of: click, confirm, cancel, shippingratechange,
	 *                           shippingaddresschange, paymentmethod.
	 * @param {Object} payload   The event payload from Stripe.
	 */
	recordExpressEvent( eventName, payload = {} ) {
		this.record(
			`express.${ eventName }`,
			projectExpressPayload( eventName, payload )
		);
	}

	/**
	 * Like attach(), but for elements where the underlying Stripe Element
	 * has already fired its one-time `ready` event by the time we get here
	 * (e.g. inside React's <PaymentElement onReady={...}> prop). We synthesize
	 * the ready event so the trace stays symmetric with the classic surface,
	 * then subscribe for the lifecycle events that fire later.
	 *
	 * @param {Object} element The Stripe Element instance.
	 * @param {string} kind    The element kind (e.g. 'payment', 'card').
	 */
	attachAfterReady( element, kind ) {
		this.record( 'element.ready', { element_type: kind } );
		this.attach( element, kind );
	}

	attach( element, kind ) {
		element.on( 'ready', () => {
			this.record( 'element.ready', { element_type: kind } );
		} );
		element.on( 'focus', () => {
			this.record( 'element.focus', { element_type: kind } );
		} );
		element.on( 'blur', () => {
			this.record( 'element.blur', { element_type: kind } );
		} );
		element.on( 'change', ( payload = {} ) => {
			this.record(
				'element.change',
				projectChangePayload( kind, payload )
			);
		} );
		element.on( 'loaderror', ( payload = {} ) => {
			this.record( 'element.loaderror', {
				element_type: kind,
				error_code: payload.error?.code,
				error_type: payload.error?.type,
				error_message: payload.error?.message,
			} );
		} );
	}

	record( kind, data ) {
		if ( ! this.config?.active ) {
			return;
		}
		this.buffer.push( {
			t: this.now() - this.traceStartMs,
			kind,
			data,
		} );
		if ( this.buffer.length > MAX_BUFFER_SIZE ) {
			this.buffer.splice( 0, this.buffer.length - MAX_BUFFER_SIZE );
		}
		if ( this.buffer.length >= MAX_BUFFER_BEFORE_FLUSH ) {
			this.flush();
			return;
		}
		this._resetIdleTimer();
	}

	flush() {
		if ( ! this.config?.active || this.buffer.length === 0 ) {
			return;
		}
		const payload = buildIngestBlob( {
			diag_session_id: this.config.sessionId,
			events: this.buffer,
		} );
		const queued = navigator.sendBeacon(
			buildIngestUrl( this.config ),
			payload
		);
		// sendBeacon returns false when the browser refuses
		// Preserve the buffer so the next flush retries instead
		// of silently dropping events.
		if ( queued ) {
			this.buffer = [];
		}
		this._clearIdleTimer();
	}

	destroy() {
		this._clearIdleTimer();
		if ( this.pagehideHandler ) {
			window.removeEventListener( 'pagehide', this.pagehideHandler );
			this.pagehideHandler = null;
		}
		this._restoreConsole();
		this.config = null;
	}

	_resetIdleTimer() {
		this._clearIdleTimer();
		this.idleTimer = this.setTimer( () => this.flush(), IDLE_FLUSH_MS );
	}

	_clearIdleTimer() {
		if ( this.idleTimer !== null ) {
			this.clearTimer( this.idleTimer );
			this.idleTimer = null;
		}
	}
}

let _singleton = null;

/**
 * Lazily-constructed recorder singleton. Boots on first access, capturing
 * `window.wcStripeDiag` once at boot time. Runtime mutations to that global
 * (flipping `.active`, rotating `sessionId`, swapping `endpoint`, etc.) do
 * NOT propagate to an already-booted recorder by design — config is a
 * per-pageview snapshot. To pick up new config, reload the page.
 *
 * @return {Recorder} The booted singleton.
 */
export function getRecorder() {
	if ( ! _singleton ) {
		_singleton = new Recorder();
		_singleton.boot();
	}
	return _singleton;
}

// Wrap the JSON payload in a Blob so sendBeacon sets Content-Type to
// application/json. Without this, sendBeacon defaults to text/plain and
// WP's REST server skips JSON body parsing.
function buildIngestBlob( payload ) {
	return new Blob( [ JSON.stringify( payload ) ], {
		type: 'application/json',
	} );
}

function buildIngestUrl( config ) {
	if ( ! config.nonce ) {
		return config.endpoint;
	}
	const separator = config.endpoint.includes( '?' ) ? '&' : '?';
	return (
		config.endpoint +
		separator +
		'_wpnonce=' +
		encodeURIComponent( config.nonce )
	);
}

function projectExpressPayload( eventName, payload ) {
	const base = { wallet_type: payload.expressPaymentType };
	if ( eventName === 'shippingaddresschange' ) {
		return { ...base, country: payload.address?.country };
	}
	if ( eventName === 'paymentmethod' || eventName === 'confirm' ) {
		// Blocks surfaces paymentMethod via onConfirm; classic surfaces it
		// via a separate `paymentmethod` event. Either way, project the
		// type when present.
		if ( payload.paymentMethod?.type ) {
			return { ...base, payment_method_type: payload.paymentMethod.type };
		}
	}
	return base;
}

function projectChangePayload( kind, payload ) {
	return {
		element_type: kind,
		complete: !! payload.complete,
		empty: !! payload.empty,
		valid: !! payload.complete && ! payload.error,
		brand: payload.brand,
		error_code: payload.error?.code,
		error_type: payload.error?.type,
	};
}

function readStoredState( key ) {
	try {
		const raw = window.sessionStorage.getItem( key );
		return raw ? JSON.parse( raw ) : null;
	} catch ( e ) {
		return null;
	}
}

function writeStoredState( key, state ) {
	try {
		window.sessionStorage.setItem( key, JSON.stringify( state ) );
	} catch ( e ) {
		// sessionStorage can throw on quota or in private mode; recorder degrades silently.
	}
}
