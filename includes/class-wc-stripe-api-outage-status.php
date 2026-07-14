<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks whether the Stripe API is currently experiencing an outage.
 *
 * Outages are detected from API responses (network failures, timeouts, 5xx
 * status codes) and recorded in a transient so that other parts of the plugin
 * (admin notices, REST endpoints, payment methods) can react without each
 * having to make their own probe request to Stripe.
 *
 * 4xx responses are NOT treated as outages: they confirm Stripe is reachable
 * and our request was rejected for a non-outage reason (auth, validation, etc.).
 */
class WC_Stripe_API_Outage_Status {

	/**
	 * Transient key used to store the outage state.
	 */
	public const OUTAGE_TRANSIENT_KEY = 'wc_stripe_api_outage';

	/**
	 * How long an outage is considered active without a fresh signal.
	 *
	 * Short enough that a recovered Stripe API stops being flagged quickly,
	 * long enough that we don't repeatedly hammer Stripe to re-confirm.
	 */
	public const OUTAGE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * HTTP status codes that indicate a Stripe-side outage.
	 *
	 * 5xx means Stripe accepted the request but failed to serve it. We keep
	 * this list narrow on purpose so admin/auth issues stay in the existing
	 * error paths.
	 */
	private const OUTAGE_STATUS_CODES = [ 500, 502, 503, 504 ];

	/**
	 * Determine whether a wp_remote_* response indicates a Stripe outage.
	 *
	 * @param array|WP_Error $response The raw response from wp_safe_remote_*.
	 * @return bool
	 */
	public static function is_outage_response( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return true;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, self::OUTAGE_STATUS_CODES, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Record that an outage is currently happening.
	 *
	 * Preserves the original `detected_at` timestamp on subsequent calls so the
	 * recorded outage start time reflects when it actually began.
	 */
	public static function record_outage(): void {
		$existing = get_transient( self::OUTAGE_TRANSIENT_KEY );
		$detected = is_array( $existing ) && ! empty( $existing['detected_at'] )
			? (int) $existing['detected_at']
			: time();

		set_transient(
			self::OUTAGE_TRANSIENT_KEY,
			[ 'detected_at' => $detected ],
			self::OUTAGE_TTL
		);
	}

	/**
	 * Clear the outage state.
	 *
	 * Called when we receive a non-outage response — including 4xx — because
	 * any response from Stripe servers proves the API is reachable.
	 */
	public static function record_success(): void {
		delete_transient( self::OUTAGE_TRANSIENT_KEY );
	}

	/**
	 * Whether an outage is currently flagged.
	 */
	public static function is_in_outage(): bool {
		return false !== get_transient( self::OUTAGE_TRANSIENT_KEY );
	}
}
