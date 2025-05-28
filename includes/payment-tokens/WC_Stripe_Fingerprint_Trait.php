<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for handling payment method fingerprint property.
 */
trait WC_Stripe_Fingerprint_Trait {
	/**
	 * Returns the token fingerprint (unique identifier).
	 *
	 * @since  9.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Fingerprint
	 */
	public function get_fingerprint( $context = 'view' ) {
		return $this->get_prop( 'fingerprint', $context );
	}

	/**
	 * Set the token fingerprint (unique identifier).
	 *
	 * @since 9.0.0
	 * @param string $fingerprint The fingerprint.
	 */
	public function set_fingerprint( string $fingerprint ) {
		$this->set_prop( 'fingerprint', $fingerprint );
	}
}
