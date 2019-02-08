<?php
/**
 * WooCommerce Stripe Exception Class
 *
 * Extends WC_Stripe_Exception for specific situations when
 * a PaymentIntent could not be captured.
 *
 * @since 4.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Capture_Exception extends Exception {
	protected $error;

	public function __construct( $error ) {
		$this->error = $error;

		parent::__construct( $error->message );
	}

	public function get_error() {
		return $this->error;
	}
}
