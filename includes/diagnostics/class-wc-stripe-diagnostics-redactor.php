<?php

defined( 'ABSPATH' ) || exit;

/**
 * Enforces data scrubbing for diagnostics events.
 *
 * Two layers run against every event before it reaches the trace store:
 *
 * 1. An **allow-list registry** keyed by event kind (`apiRequest`,
 *    `apiResponse`, `webhookReceived`, `consoleMessage`, ...). Any field not
 *    explicitly listed for the event's kind is dropped. Unknown kinds produce
 *    an empty event (fail-closed).
 *
 * 2. A set of **string scrubbers** applied to every remaining value: emails
 *    become `[email]`, IPv4/IPv6 become `[ip]`, URL query strings are
 *    truncated, and long opaque tokens / secrets are masked.
 *
 * The redactor is deliberately conservative. It is cheaper to drop a legitimate
 * field than to ship PII.
 */
class WC_Stripe_Diagnostics_Redactor {

	/**
	 * Allow-listed field paths per event kind. Nested fields are declared with
	 * dot notation (e.g. `meta.order_id`). Array elements share the parent
	 * allow list.
	 *
	 * See docs/diagnostics-contracts.md §5.
	 *
	 * @var array<string, string[]>
	 */
	private $allow_list;

	/**
	 * Construct a redactor, optionally overriding the allow list (used in tests).
	 *
	 * @param array<string, string[]>|null $allow_list Optional override.
	 */
	public function __construct( ?array $allow_list = null ) {
		$this->allow_list = $allow_list ?? self::default_allow_list();
	}

	/**
	 * Redact a single event.
	 *
	 * @param array $event The event payload. Must include a `kind` string.
	 * @return array The redacted event. May be empty for unknown kinds.
	 */
	public function redact( array $event ): array {
		$kind = isset( $event['kind'] ) && is_string( $event['kind'] ) ? $event['kind'] : '';
		if ( '' === $kind || ! isset( $this->allow_list[ $kind ] ) ) {
			return [];
		}

		$allowed = $this->allow_list[ $kind ];
		$kept    = [ 'kind' => $kind ];
		if ( isset( $event['ts'] ) && ( is_int( $event['ts'] ) || is_float( $event['ts'] ) ) ) {
			$kept['ts'] = (int) $event['ts'];
		}

		foreach ( $allowed as $path ) {
			$value = self::pluck( $event, $path );
			if ( null === $value ) {
				continue;
			}
			self::assign( $kept, $path, $this->scrub_value( $value ) );
		}

		return $kept;
	}

	/**
	 * Run the scrubbers on an already-structured value. Recurses into arrays.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function scrub_value( $value ) {
		if ( is_string( $value ) ) {
			return self::scrub_string( $value );
		}
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$out[ $k ] = $this->scrub_value( $v );
			}
			return $out;
		}
		return $value;
	}

	/**
	 * The default allow list. Keep this small — adding a field is an explicit
	 * statement that it's safe to ship to a diagnostics bundle.
	 *
	 * @return array<string, string[]>
	 */
	public static function default_allow_list(): array {
		return [
			'apiRequest'      => [
				'api',
				'method',
				'request_id',
				'fields',
				'has_metadata',
				'metadata.order_id',
				'metadata.wc_diag_session_id',
			],
			'apiResponse'     => [
				'api',
				'method',
				'status',
				'request_id',
				'error.code',
				'error.type',
				'error.decline_code',
				'latency_ms',
			],
			'webhookReceived' => [
				'type',
				'status',
				'intent_id',
				'charge_id',
				'order_id',
				'session_id',
			],
			'consoleMessage'  => [
				'level',
				'message_truncated',
				'source',
			],
			'elementEvent'    => [
				'element',
				'type',
				'complete',
				'empty',
				'error_code',
			],
			'sdkCall'         => [
				'method',
				'ok',
				'latency_ms',
				'error_code',
			],
			'blocksEvent'     => [
				'phase',
				'type',
				'ok',
				'error_code',
			],
			'expressCheckout' => [
				'phase',
				'type',
				'payment_method',
			],
			'sessionEnd'      => [
				'reason',
			],
		];
	}

	/**
	 * Strip obvious PII patterns from a string. The regexes are intentionally
	 * broad: false positives in a diagnostics trace are acceptable; PII leaks
	 * are not.
	 *
	 * @param string $value
	 * @return string
	 */
	private static function scrub_string( string $value ): string {
		// Truncate extremely long strings — the redactor should never amplify them.
		if ( strlen( $value ) > 512 ) {
			$value = substr( $value, 0, 512 ) . '…';
		}

		$patterns = [
			// Emails.
			'/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/'     => '[email]',
			// IPv4.
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'                        => '[ip]',
			// IPv6 — catches both fully-qualified and `::`-compressed forms.
			'/\b(?:[A-Fa-f0-9]{0,4}:){2,7}[A-Fa-f0-9]{0,4}\b/'     => '[ip]',
			// Stripe secret-ish tokens (sk_live_…, rk_live_…, sk_test_…, whsec_…).
			'/\b(?:sk|rk|whsec)_(?:live|test)?_?[A-Za-z0-9]{10,}/' => '[secret]',
		];
		foreach ( $patterns as $pattern => $replacement ) {
			$replaced = preg_replace( $pattern, $replacement, $value );
			if ( is_string( $replaced ) ) {
				$value = $replaced;
			}
		}

		// URL query strings — keep the path, drop params entirely. URLs with
		// user identifiers in the query are a common accidental leak source.
		$replaced = preg_replace_callback(
			'~(https?://[^\s?]+)\?[^\s]*~',
			static function ( $m ) {
				return $m[1] . '?[redacted]';
			},
			$value
		);
		if ( is_string( $replaced ) ) {
			$value = $replaced;
		}

		return $value;
	}

	/**
	 * Walk a nested array using a dot-path and return the resolved value,
	 * or null when any segment is missing.
	 *
	 * @param array  $source
	 * @param string $path
	 * @return mixed|null
	 */
	private static function pluck( array $source, string $path ) {
		$segments = explode( '.', $path );
		$cursor   = $source;
		foreach ( $segments as $segment ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $segment, $cursor ) ) {
				return null;
			}
			$cursor = $cursor[ $segment ];
		}
		return $cursor;
	}

	/**
	 * Assign a value into a nested array using a dot-path, creating parent
	 * arrays as needed.
	 *
	 * @param array  $target
	 * @param string $path
	 * @param mixed  $value
	 */
	private static function assign( array &$target, string $path, $value ): void {
		$segments = explode( '.', $path );
		$cursor   = &$target;
		foreach ( $segments as $i => $segment ) {
			if ( count( $segments ) - 1 === $i ) {
				$cursor[ $segment ] = $value;
				return;
			}
			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
				$cursor[ $segment ] = [];
			}
			$cursor = &$cursor[ $segment ];
		}
	}
}
