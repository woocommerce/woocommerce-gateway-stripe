<?php
/**
 * Mock for the WCS_Retry value object used by the Radar-block path in
 * WC_Stripe_Subscriptions_Trait::process_subscription_payment().
 *
 * This helper should ONLY be used for unit tests.
 */

/**
 * Minimal stand-in for WCS_Retry. Exposes the public surface the trait calls:
 * get_status() and update_status().
 */
class WCS_Retry {
	/**
	 * Current retry status (e.g. 'pending', 'cancelled').
	 *
	 * @var string
	 */
	private $status;

	/**
	 * @param string $status Initial status, defaults to 'pending'.
	 */
	public function __construct( $status = 'pending' ) {
		$this->status = $status;
	}

	/**
	 * @return string Current status.
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * @param string $status New status.
	 * @return void
	 */
	public function update_status( $status ) {
		$this->status = $status;
	}

	/**
	 * Stub matching the real WCS_Retry::get_time() signature so PHPStan can
	 * resolve calls in production code (e.g. WC_Stripe_Email_Failed_Authentication_Retry).
	 * Not exercised by tests in this file.
	 *
	 * @return int
	 */
	public function get_time() {
		return 0;
	}
}
