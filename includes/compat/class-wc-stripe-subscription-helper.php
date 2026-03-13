<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Subscription_Helper class.
 *
 * Extends WC_Stripe_Order_Helper with subscription-specific metadata handling.
 * Since WC_Subscription extends WC_Order, all parent class methods work on subscription objects.
 *
 * @since 10.7.0
 */
class WC_Stripe_Subscription_Helper extends WC_Stripe_Order_Helper {

	/**
	 * Singleton instance of the class.
	 *
	 * @var null|WC_Stripe_Subscription_Helper
	 */
	private static ?WC_Stripe_Subscription_Helper $instance = null;

	/**
	 * Gets the singleton instance of the class.
	 *
	 * @return WC_Stripe_Subscription_Helper
	 */
	public static function get_instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Sets the singleton instance of the class.
	 *
	 * @param WC_Stripe_Order_Helper|null $instance
	 * @return void
	 */
	public static function set_instance( ?WC_Stripe_Order_Helper $instance ): void {
		self::$instance = $instance instanceof self ? $instance : null;
	}
}
