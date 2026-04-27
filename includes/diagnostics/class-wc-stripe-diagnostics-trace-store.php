<?php

use Automattic\WooCommerce\Internal\Utilities\FilesystemUtil;

defined( 'ABSPATH' ) || exit;

/**
 * File-backed store for diagnostics traces.
 *
 * A "trace" is the redacted record of a single checkout session keyed by a
 * client-generated sessionId. Each trace is persisted as a single JSON file
 * inside a dedicated, non-indexable directory under the WordPress uploads
 * folder, alongside an `index.json` file holding the ordered list of trace
 * ids.
 *
 * Storing traces as files (rather than wp_options rows) makes it easy to
 * download, attach, or hand off an individual trace, and keeps diagnostics
 * data out of regular database backups.
 *
 * Invariants enforced here:
 * - at most {@see self::MAX_TRACES} traces exist at any time (FIFO eviction)
 * - at most {@see self::MAX_EVENTS_PER_TRACE} events per trace
 * - the serialized trace blob is capped at {@see self::MAX_TRACE_BYTES}
 *
 * An advisory `flock()` on a dedicated lock file serializes writers so
 * concurrent requests never clobber each other's index updates.
 */
class WC_Stripe_Diagnostics_Trace_Store {

	const STORAGE_SUBDIR = 'wc-stripe-diagnostics';
	const INDEX_FILENAME = 'index.json';
	const LOCK_FILENAME  = '.index.lock';
	const TRACE_SUFFIX   = '.json';

	const MAX_TRACES           = 200;
	const MAX_EVENTS_PER_TRACE = 200;
	const MAX_TRACE_BYTES      = 102400; // 100 KB.

	const STATUS_PENDING   = 'pending';
	const STATUS_COMPLETED = 'completed';
	const STATUS_ABANDONED = 'abandoned';

	/**
	 * Cached absolute path (with trailing slash) to the trace storage dir.
	 *
	 * @var string|null
	 */
	private $base_dir;

	/**
	 * Create a new trace record. No-op if a trace with this id already exists.
	 *
	 * @param string $session_id Client-generated session identifier.
	 * @param array  $meta       Optional top-level metadata (order_id, source page, etc.).
	 * @return bool True if the trace was newly created.
	 */
	public function create( $session_id, array $meta = [] ) {
		$session_id = self::sanitize_id( $session_id );
		if ( '' === $session_id || ! $this->ensure_storage_dir() ) {
			return false;
		}
		if ( file_exists( $this->trace_path( $session_id ) ) ) {
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
				if ( ! $this->write_trace( $session_id, $trace ) ) {
					return false;
				}
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
		if ( '' === $session_id || ! $this->ensure_storage_dir() ) {
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
				return false !== @file_put_contents( $this->trace_path( $session_id ), $encoded ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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
		if ( '' === $session_id ) {
			return false;
		}
		if ( ! in_array( $status, [ self::STATUS_COMPLETED, self::STATUS_ABANDONED ], true ) ) {
			return false;
		}
		if ( ! $this->ensure_storage_dir() ) {
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
				return $this->write_trace( $session_id, $trace );
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
				$this->delete_trace_file( $session_id );
				$index = array_values( array_diff( $this->read_index(), [ $session_id ] ) );
				$this->write_index( $index );
				return true;
			}
		);
	}

	/**
	 * Wipe every trace plus the index. Intended for tests and future uninstall.
	 */
	public function delete_all(): void {
		if ( null === $this->base_dir && ! is_dir( $this->base_dir() ) ) {
			return;
		}

		$this->with_lock(
			function () {
				foreach ( $this->read_index() as $id ) {
					$this->delete_trace_file( (string) $id );
				}
				$index_path = $this->index_path();
				if ( file_exists( $index_path ) ) {
					@unlink( $index_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				}
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
	 * Absolute filesystem path to a trace file. Useful for downstream code
	 * that wants to stream or attach the raw JSON without re-decoding.
	 *
	 * @param string $session_id Session identifier (raw or sanitized).
	 * @return string|null Path, or null when the id is invalid.
	 */
	public function get_trace_path( $session_id ) {
		$session_id = self::sanitize_id( $session_id );
		if ( '' === $session_id ) {
			return null;
		}
		return $this->trace_path( $session_id );
	}

	/**
	 * Evict the oldest trace files until the index is under {@see self::MAX_TRACES}.
	 *
	 * @param string[] $index The current index.
	 * @return string[] The (possibly trimmed) index.
	 */
	private function evict_oldest( array $index ) {
		$count = count( $index );
		while ( $count > self::MAX_TRACES ) {
			$oldest = array_shift( $index );
			if ( is_string( $oldest ) ) {
				$this->delete_trace_file( $oldest );
			}
			--$count;
		}
		return $index;
	}

	/**
	 * Read and decode a single trace file.
	 *
	 * @param string $session_id Sanitized session id.
	 * @return array|null Decoded trace, or null when absent or malformed.
	 */
	private function read_trace( string $session_id ): ?array {
		$path = $this->trace_path( $session_id );
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$raw = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Encode and persist a trace to disk.
	 *
	 * @param string $session_id Sanitized session id.
	 * @param array  $trace      Trace payload.
	 * @return bool True on success.
	 */
	private function write_trace( string $session_id, array $trace ): bool {
		$encoded = wp_json_encode( $trace );
		if ( false === $encoded ) {
			return false;
		}
		return false !== @file_put_contents( $this->trace_path( $session_id ), $encoded ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Read the trace id index from disk.
	 *
	 * @return string[] Ordered list of trace ids, oldest first.
	 */
	private function read_index(): array {
		$path = $this->index_path();
		if ( ! is_readable( $path ) ) {
			return [];
		}
		$raw = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}
		return array_values( array_filter( $decoded, 'is_string' ) );
	}

	/**
	 * Persist the trace id index.
	 *
	 * @param string[] $index Ordered list of trace ids.
	 */
	private function write_index( array $index ): void {
		$encoded = wp_json_encode( array_values( $index ) );
		if ( false !== $encoded ) {
			@file_put_contents( $this->index_path(), $encoded ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	private function delete_trace_file( string $session_id ): void {
		$path = $this->trace_path( $session_id );
		if ( file_exists( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Acquire an exclusive flock on the lock file and run $callback. Returns
	 * the callback's return value (or null when the lock file cannot be
	 * opened — best-effort: better to risk a rare lost write than drop the
	 * event entirely).
	 *
	 * @param callable $callback The critical section to run under the lock.
	 * @return mixed|null
	 */
	private function with_lock( callable $callback ) {
		if ( ! $this->ensure_storage_dir() ) {
			return null;
		}

		$fp = @fopen( $this->base_dir() . self::LOCK_FILENAME, 'c' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $fp ) {
			return $callback();
		}
		try {
			@flock( $fp, LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $callback();
		} finally {
			@flock( $fp, LOCK_UN ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@fclose( $fp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}

	/**
	 * Resolve and lazily create the storage directory. Returns false if the
	 * directory cannot be created (read-only filesystem, missing uploads dir).
	 *
	 * @return bool
	 */
	private function ensure_storage_dir(): bool {
		$dir = rtrim( $this->base_dir(), '/' );
		if ( is_dir( $dir ) ) {
			return true;
		}
		if ( class_exists( FilesystemUtil::class ) ) {
			FilesystemUtil::mkdir_p_not_indexable( $dir );
		} else {
			wp_mkdir_p( $dir );
		}
		return is_dir( $dir );
	}

	/**
	 * Resolve the storage directory path (uploads/wc-stripe-diagnostics/).
	 *
	 * @return string Path with trailing slash.
	 */
	private function base_dir(): string {
		if ( null !== $this->base_dir ) {
			return $this->base_dir;
		}
		$uploads        = wp_upload_dir( null, false );
		$this->base_dir = trailingslashit( $uploads['basedir'] ) . self::STORAGE_SUBDIR . '/';
		return $this->base_dir;
	}

	private function trace_path( string $session_id ): string {
		return $this->base_dir() . $session_id . self::TRACE_SUFFIX;
	}

	private function index_path(): string {
		return $this->base_dir() . self::INDEX_FILENAME;
	}

	/**
	 * Reduce an id to a slug-safe form. Session ids are client-generated so
	 * we must never trust them as filename fragments.
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
}
