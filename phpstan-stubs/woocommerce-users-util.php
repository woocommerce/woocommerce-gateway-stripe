<?php
/**
 * PHPStan stub for WooCommerce's internal Users utility class.
 *
 * The bundled WooCommerce stubs do not cover the Internal namespace.
 *
 * @package WooCommerce_Stripe
 */

namespace Automattic\WooCommerce\Internal\Utilities;

/**
 * Users class.
 */
class Users {
	/**
	 * Whether the current visitor must verify the order's billing email before viewing it.
	 *
	 * @param int         $order_id       Order ID.
	 * @param string|null $supplied_email Email supplied by the visitor, if any.
	 * @param string      $context        Context in which the email is checked.
	 * @return bool
	 */
	public static function should_user_verify_order_email( $order_id, $supplied_email = null, $context = 'view' ) {
		return true;
	}
}
