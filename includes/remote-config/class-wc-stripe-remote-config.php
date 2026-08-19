<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache and resolver for remotely-controlled feature flags.
 *
 * Cache layout (one option per mode):
 *
 *   _wcstripe_remote_config_live  / _wcstripe_remote_config_test
 *   {
 *       schema_version: int,            // cache-layout version, plugin-owned
 *       fetched_at:     int,            // unix timestamp of last successful fetch
 *       flags:          { name: { value: <typed> } }
 *   }
 *
 * Last-known-good wins indefinitely: there is no TTL-based expiry of the cache
 * itself. A schema or HTTP failure discards the new payload and keeps the
 * existing cache (circuit breaker). Deliberate: expiring stale values back to
 * local defaults would re-enable a remotely disabled feature on exactly the
 * sites the disable can no longer reach. Recovery from a permanently
 * unreachable endpoint is manual (delete the per-mode option via wp-cli, or
 * the internal channel override).
 */
class WC_Stripe_Remote_Config {

	private const SCHEMA_VERSION = 1;

	/**
	 * Memoized shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Shared instance accessor.
	 *
	 * Safe to memoize: instances hold no state of their own (the cache is
	 * static + options), so callers never need a private copy.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * In-process per-request cache of decoded options, keyed by mode.
	 *
	 * @var array<string, array|null>
	 */
	private static $in_memory_cache = [];

	/**
	 * Reset the in-memory cache. Test-only helper.
	 */
	public static function reset_in_memory_cache(): void {
		self::$in_memory_cache = [];
	}

	/**
	 * Validate and persist a freshly-fetched remote-config payload for the given mode.
	 *
	 * Returns true on success, false on validation failure (in which case the
	 * existing cache is preserved).
	 */
	public function apply( string $mode, array $payload ): bool {
		$rejection_reason = $this->validate_payload( $payload );
		if ( null !== $rejection_reason ) {
			WC_Stripe_Logger::warning(
				'Stripe remote-config: payload rejected; keeping previous cache.',
				[
					'mode'           => $mode,
					'reason'         => $rejection_reason,
					'previous_cache' => $this->get_cache_snapshot( $mode ),
				]
			);
			return false;
		}

		$known_flags = [];
		foreach ( $payload['flags'] as $name => $entry ) {
			if ( ! WC_Stripe_Remote_Config_Flags::is_known_flag( $name ) ) {
				continue;
			}
			$known_flags[ $name ] = [ 'value' => $entry['value'] ];
		}

		$cache_entry = [
			'schema_version' => self::SCHEMA_VERSION,
			'fetched_at'     => time(),
			'flags'          => $known_flags,
		];

		update_option( $this->option_name( $mode ), $cache_entry, false );
		self::$in_memory_cache[ $mode ] = $cache_entry;

		return true;
	}

	/**
	 * Resolve a flag's value: remote wins if present, otherwise the local fallback.
	 *
	 * @param string      $flag        Flag name as declared in WC_Stripe_Remote_Config_Flags::FLAGS.
	 * @param mixed       $local_value Caller-computed local value (the existing logic's result).
	 * @param string|null $mode        'live'/'test'/null (auto-derive).
	 *
	 * @return mixed
	 */
	public function resolve( string $flag, $local_value, ?string $mode = null ) {
		$remote = $this->get_flag( $flag, $mode );
		return null === $remote ? $local_value : $remote;
	}

	/**
	 * Read a flag's value from cache, or null if absent / unknown.
	 *
	 * @param string      $flag
	 * @param string|null $mode
	 *
	 * @return mixed|null
	 */
	public function get_flag( string $flag, ?string $mode = null ) {
		if ( ! WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() ) {
			return null;
		}

		if ( ! WC_Stripe_Remote_Config_Flags::is_known_flag( $flag ) ) {
			return null;
		}

		$resolved_mode = $this->resolve_mode( $mode );
		$cache         = $this->get_cache( $resolved_mode );

		if ( null === $cache ) {
			return null;
		}

		if ( ! isset( $cache['flags'][ $flag ] ) ) {
			return null;
		}

		return $cache['flags'][ $flag ]['value'];
	}

	/**
	 * Validate a remote-config payload.
	 *
	 * @return string|null Null on success, or a short reason describing the
	 *                     first failure (intended for the rejection log).
	 */
	private function validate_payload( array $payload ): ?string {
		// Client also caps wire-body size, but apply() may be
		// called outside the Client (e.g. from tests, or from a future caller that
		// builds the payload in memory).
		$encoded = wp_json_encode( $payload );
		if ( false === $encoded ) {
			return 'payload not JSON-encodable';
		}
		if ( strlen( $encoded ) > WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES ) {
			return 'payload exceeds MAX_PAYLOAD_BYTES';
		}

		if ( ! isset( $payload['generated_at'] ) || ! is_string( $payload['generated_at'] ) ) {
			return 'missing or non-string generated_at';
		}
		// ISO-8601 sanity check (e.g. "2026-05-09T12:00:00Z" or with timezone offset).
		if ( false === strtotime( $payload['generated_at'] ) ) {
			return 'unparseable generated_at';
		}

		if ( ! isset( $payload['flags'] ) || ! is_array( $payload['flags'] ) ) {
			return 'missing or non-array flags';
		}

		foreach ( $payload['flags'] as $name => $entry ) {
			if ( ! WC_Stripe_Remote_Config_Flags::is_known_flag( $name ) ) {
				continue; // Unknown flags are dropped silently, not a validation failure.
			}
			if ( ! is_array( $entry ) || ! array_key_exists( 'value', $entry ) ) {
				return sprintf( 'flag %s: missing value field', $name );
			}
			if ( ! WC_Stripe_Remote_Config_Flags::validate_value( $name, $entry['value'] ) ) {
				return sprintf( 'flag %s: value type does not match schema', $name );
			}
		}

		return null;
	}

	/**
	 * Snapshot of the stored cache for the given mode, or null if
	 * no valid cache is stored.
	 *
	 * @return array|null
	 */
	public function get_cache_snapshot( string $mode ): ?array {
		$cache = $this->get_cache( $mode );
		if ( null === $cache ) {
			return null;
		}
		return [
			'fetched_at' => (int) $cache['fetched_at'],
			'flags'      => $cache['flags'],
		];
	}

	/**
	 * Seconds since the last successful fetch for the given mode.
	 *
	 * @param string $mode 'live' or 'test'.
	 * @return int|null Null when no cache (or no usable timestamp) is stored.
	 */
	public function get_cache_age( string $mode ): ?int {
		$cache = $this->get_cache( $mode );
		if ( null === $cache || empty( $cache['fetched_at'] ) ) {
			return null;
		}
		return max( 0, time() - (int) $cache['fetched_at'] );
	}

	/**
	 * Load the cache for the given mode from the in-memory store or WP option.
	 *
	 * @return array|null Cache entry (or null if no cache / corrupt / wrong schema_version).
	 */
	private function get_cache( string $mode ): ?array {
		if ( array_key_exists( $mode, self::$in_memory_cache ) ) {
			return self::$in_memory_cache[ $mode ];
		}

		$raw = get_option( $this->option_name( $mode ) );
		if ( ! is_array( $raw ) ) {
			self::$in_memory_cache[ $mode ] = null;
			return null;
		}

		if ( ! isset( $raw['schema_version'] ) || self::SCHEMA_VERSION !== $raw['schema_version'] ) {
			self::$in_memory_cache[ $mode ] = null;
			return null;
		}

		if ( ! isset( $raw['flags'] ) || ! is_array( $raw['flags'] ) ) {
			self::$in_memory_cache[ $mode ] = null;
			return null;
		}

		self::$in_memory_cache[ $mode ] = $raw;
		return $raw;
	}

	private function option_name( string $mode ): string {
		$mode = $this->resolve_mode( $mode );
		return '_wcstripe_remote_config_' . $mode;
	}

	private function resolve_mode( ?string $mode ): string {
		if ( 'live' === $mode || 'test' === $mode ) {
			return $mode;
		}
		return WC_Stripe_Mode::is_test() ? 'test' : 'live';
	}
}
