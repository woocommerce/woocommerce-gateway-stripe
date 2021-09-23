<?php
/**
 * Stripe API helpers.
 *
 * @package WooCommerce\Tests
 */

/**
 * Class WC_Helper_Stripe_Api.
 *
 * This helper class should ONLY be used for unit tests!.
 * This helper class is used to mock static functions of WC_Stripe_API
 */
class WC_Helper_Stripe_Api {

	/**
	 * retrieve data. This is the equivalent mock for WC_Stripe_API::retrieve
	 *
	 * @param string data type
	 *
	 * @return array retrieved data mock
	 */
	public static function retrieve( $key = 'account' ) {
		return [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
	}
}
