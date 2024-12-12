<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Order
 *
 * Wrapper for the original WC_Order class to allow custom getters and setter with the extension's specific metadata.
 */
class WC_Stripe_Order extends WC_Order {
	/**
	 * Set the charge captured flag.
	 *
	 * @param $value string The value to set.
	 * @return void
	 */
	public function set_charge_captured( $value ) {
		$this->update_meta_data( '_stripe_charge_captured', $value );
	}
	/**
	 * Whether the charge has been captured.
	 *
	 * @return bool
	 */
	public function charge_captured() {
		return $this->get_meta( '_stripe_charge_captured' ) === 'yes';
	}

	/**
	 * Set the status final value.
	 *
	 * @param $value bool The value to set.
	 * @return void
	 */
	public function set_status_final( $value ) {
		$this->update_meta_data( '_stripe_status_final', $value );
	}

	/**
	 * Whether the current order status is final.
	 *
	 * @return bool
	 */
	public function status_final() {
		return (bool) $this->get_meta( '_stripe_status_final' );
	}
}
