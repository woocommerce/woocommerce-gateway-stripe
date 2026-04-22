const STORAGE_KEY_PREFIX = 'wc_stripe_diag_';
const IDLE_FLUSH_MS = 5000;

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
			const payload = JSON.stringify( {
				diag_session_id: this.config.sessionId,
				events: persisted.bufferedEvents,
			} );
			navigator.sendBeacon( this.config.endpoint, payload );

			const cleared = { ...persisted };
			delete cleared.bufferedEvents;
			writeStoredState( storageKey, cleared );
		}

		this.pagehideHandler = () => this._handlePagehide();
		window.addEventListener( 'pagehide', this.pagehideHandler );
	}

	_handlePagehide() {
		// Persist current buffer BEFORE attempting the flush so a torn-down
		// browser tab can still replay these events on the return page.
		if ( this.buffer.length > 0 ) {
			const storageKey = STORAGE_KEY_PREFIX + this.config.sessionId;
			const existing = readStoredState( storageKey ) || {};
			writeStoredState( storageKey, {
				...existing,
				bufferedEvents: [ ...this.buffer ],
			} );
		}
		this.flush( 'pagehide' );
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
		this._resetIdleTimer();
	}

	flush() {
		if ( ! this.config?.active || this.buffer.length === 0 ) {
			return;
		}
		const payload = JSON.stringify( {
			diag_session_id: this.config.sessionId,
			events: this.buffer,
		} );
		navigator.sendBeacon( this.config.endpoint, payload );
		this.buffer = [];
		this._clearIdleTimer();
	}

	destroy() {
		this._clearIdleTimer();
		if ( this.pagehideHandler ) {
			window.removeEventListener( 'pagehide', this.pagehideHandler );
			this.pagehideHandler = null;
		}
		this.config = null;
	}

	_resetIdleTimer() {
		this._clearIdleTimer();
		this.idleTimer = this.setTimer(
			() => this.flush( 'idle' ),
			IDLE_FLUSH_MS
		);
	}

	_clearIdleTimer() {
		if ( this.idleTimer !== null ) {
			this.clearTimer( this.idleTimer );
			this.idleTimer = null;
		}
	}
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
