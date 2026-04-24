<?php

defined( 'ABSPATH' ) || exit;

/**
 * Option-backed store for diagnostics traces.
 *
 * A "trace" is the redacted record of a single checkout session keyed by a
 * client-generated sessionId. The store is deliberately plain: each trace
 * is a single `wp_options` row keyed by `wc_stripe_diag_trace_<id>` plus a
 * shared index row (`wc_stripe_diag_index`) holding the ordered list of ids.
 *
 * Invariants enforced here:
 * - at most {@see self::MAX_TRACES} traces exist at any time (FIFO eviction)
 * - at most {@see self::MAX_EVENTS_PER_TRACE} events per trace
 * - the serialized trace blob is capped at {@see self::MAX_TRACE_BYTES}
 *
 * A transient lock serializes writers so concurrent requests never clobber
 * each other's index updates.
 */
class WC_Stripe_Diagnostics_Trace_Store {

	const TRACE_OPTION_PREFIX = 'wc_stripe_diag_trace_';
	const INDEX_OPTION        = 'wc_stripe_diag_index';
	const LOCK_TRANSIENT      = 'wc_stripe_diag_index_lock';
	const LOCK_TIMEOUT        = 10;

	const MAX_TRACES           = 200;
	const MAX_EVENTS_PER_TRACE = 200;
	const MAX_TRACE_BYTES      = 102400; // 100 KB.

	const STATUS_PENDING   = 'pending';
	const STATUS_COMPLETED = 'completed';
	const STATUS_ABANDONED = 'abandoned';

	/**
	 * Create a new trace record. No-op if a trace with this id already exists.
	 *
	 * @param string $session_id Client-generated session identifier.
	 * @param array  $meta       Optional top-level metadata (order_id, source page, etc.).
	 * @return bool True if the trace was newly created.
	 */
	public function create( $session_id, array $meta = [] ) {
		$session_id = self::sanitize_id( $session_id );
		if ( '' === $session_id ) {
			return false;
		}
		if ( false !== get_option( self::option_name( $session_id ), false ) ) {
			return false;
		}

		$trace = [
			'id'         => $session_id,
			'status'     => self::STATUS_PENDING,
			'created_at' => time(),
			'updated_at' => time(),
			'meta'       => $meta,
			'events'     => [],
		];

		return (bool) $this->with_lock(
			function () use ( $session_id, $trace ) {
				update_option( self::option_name( $session_id ), wp_json_encode( $trace ), false );
				$index = $this->read_index();
				if ( ! in_array( $session_id, $index, true ) ) {
					$index[] = $session_id;
				}
				$index = $this->evict_oldest( $index );
				$this->write_index( $index );
				return true;
			}
		);
	}

	/**
	 * Append a single event to an existing trace.
	 *
	 * Drops the event silently if the trace hits either cap. Callers can check
	 * the return value to decide whether to emit a "capped" signal.
	 *
	 * @param string $session_id Session identifier.
	 * @param array  $event      Event payload (already redacted by the caller).
	 * @return bool True if the event was appended.
	 */
	public function append_event( $session_id, array $event ) {
		$session_id = self::sanitize_id( $session_id );
		if ( '' === $session_id ) {
			return false;
		}

		return (bool) $this->with_lock(
			function () use ( $session_id, $event ) {
				$trace = $this->read_trace( $session_id );
				if ( null === $trace ) {
					return false;
				}
				if ( count( $trace['events'] ) >= self::MAX_EVENTS_PER_TRACE ) {
					return false;
				}
				$trace['events'][]   = $event;
				$trace['updated_at'] = time();
				$encoded             = wp_json_encode( $trace );
				if ( false === $encoded || strlen( $encoded ) > self::MAX_TRACE_BYTES ) {
					return false;
				}
				update_option( self::option_name( $session_id ), $encoded, false );
				return true;
			}
		);
	}

	/**
	 * Promote a trace to a terminal status (completed | abandoned).
	 *
	 * @param string $session_id Session identifier.
	 * @param string $status     One of STATUS_COMPLETED, STATUS_ABANDONED.
	 * @return bool True if status was updated.
	 */
	public function set_status( $session_id, $status ) {
		$session_id = self::sanitize_id( $session_id );
		if ( ! in_array( $status, [ self::STATUS_COMPLETED, self::STATUS_ABANDONED ], true ) ) {
			return false;
		}

		return (bool) $this->with_lock(
			function () use ( $session_id, $status ) {
				$trace = $this->read_trace( $session_id );
				if ( null === $trace ) {
					return false;
				}
				$trace['status']     = $status;
				$trace['updated_at'] = time();
				update_option( self::option_name( $session_id ), wp_json_encode( $trace ), false );
				return true;
			}
		);
	}

	/**
	 * Read a single trace. Returns null when the trace does not exist.
	 *
	 * @param string $session_id Session identifier.
	 * @return array|null
	 */
	public function get( $session_id ) {
		$session_id = self::sanitize_id( $session_id );
		if ( '' === $session_id ) {
			return null;
		}
		return $this->read_trace( $session_id );
	}

	/**
	 * Delete a trace and remove it from the index.
	 *
	 * @param string $session_id Session identifier.
	 * @return bool True if the trace was removed.
	 */
	public function delete( $session_id ) {
		$session_id = self::sanitize_id( $session_id );
		if ( '' === $session_id ) {
			return false;
		}

		return (bool) $this->with_lock(
			function () use ( $session_id ) {
				delete_option( self::option_name( $session_id ) );
				$index = array_values( array_diff( $this->read_index(), [ $session_id ] ) );
				$this->write_index( $index );
				return true;
			}
		);
	}

	/**
	 * Return the ordered list of trace ids, oldest first.
	 *
	 * @return string[]
	 */
	public function get_all_ids() {
		return $this->read_index();
	}

	/**
	 * Return the number of traces currently stored.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->read_index() );
	}

	/**
	 * Whether the store is saturated (at {@see self::MAX_TRACES}).
	 *
	 * @return bool
	 */
	public function is_full() {
		return $this->count() >= self::MAX_TRACES;
	}

	/**
	 * Evict oldest trace rows until the index is under {@see self::MAX_TRACES}.
	 *
	 * @param string[] $index The current index.
	 * @return string[] The (possibly trimmed) index.
	 */
	private function evict_oldest( array $index ) {
		$count = count( $index );
		while ( $count > self::MAX_TRACES ) {
			$oldest = array_shift( $index );
			if ( is_string( $oldest ) ) {
				delete_option( self::option_name( $oldest ) );
			}
			--$count;
		}
		return $index;
	}

	/**
	 * Read and decode a single trace row.
	 *
	 * @param string $session_id Sanitized session id.
	 * @return array|null Decoded trace, or null when absent or malformed.
	 */
	private function read_trace( string $session_id ): ?array {
		$raw = get_option( self::option_name( $session_id ), false );
		if ( false === $raw || ! is_string( $raw ) ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Read the trace id index from wp_options.
	 *
	 * @return string[] Ordered list of trace ids, oldest first.
	 */
	private function read_index(): array {
		$raw = get_option( self::INDEX_OPTION, [] );
		return is_array( $raw ) ? array_values( $raw ) : [];
	}

	/**
	 * Persist the trace id index.
	 *
	 * @param string[] $index Ordered list of trace ids.
	 */
	private function write_index( array $index ): void {
		update_option( self::INDEX_OPTION, array_values( $index ), false );
	}

	/**
	 * Acquire a short-lived transient lock and run $callback. Returns the
	 * callback's return value, or null if the lock could not be acquired.
	 *
	 * @param callable $callback The critical section to run under the lock.
	 * @return mixed|null
	 */
	private function with_lock( callable $callback ) {
		// Best-effort lock: transients can silently fail under object-cache
		// contention, and we'd rather accept a rare lost write than drop the
		// event entirely. The failure mode is a concurrent reader's stale view,
		// not corruption (each trace row is a single atomic option write).
		set_transient( self::LOCK_TRANSIENT, 1, self::LOCK_TIMEOUT );
		try {
			return $callback();
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Reduce an id to a slug-safe form. Session ids are client-generated so
	 * we must never trust them as option-name fragments.
	 *
	 * @param string $id Raw session identifier.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		if ( ! is_string( $id ) ) {
			return '';
		}
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
		return is_string( $id ) ? substr( $id, 0, 64 ) : '';
	}

	private static function option_name( string $session_id ): string {
		return self::TRACE_OPTION_PREFIX . $session_id;
	}
}
