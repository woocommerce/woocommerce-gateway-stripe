<?php
/**
 * Custom exception to allow for test interception.
 *
 * @package WooCommerce_Stripe/Tests/Helpers
 */

/**
 * Thrown so a test can observe the status code that would have been returned.
 */
class WC_Stripe_Webhook_Terminated_Exception extends Exception {
	/**
	 * HTTP status code the handler terminated with.
	 *
	 * @var int
	 */
	public $status_code;

	/**
	 * @param int $status_code HTTP status code.
	 */
	public function __construct( int $status_code ) {
		$this->status_code = $status_code;

		parent::__construct( 'Webhook handler terminated with status ' . $status_code );
	}
}
