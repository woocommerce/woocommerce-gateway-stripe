<?php
/**
 * Subscription helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Subscription.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscription {
	/**
	 * Helper variable for mocking get_related_orders.
	 *
	 * @var array
	 */
	public $related_orders;

	public function get_related_orders( $type ) {
		return $this->related_orders;
	}

	public function set_related_orders( $array ) {
		$this->related_orders = $array;
	}
}
