<?php
/**
 * Class Migrate_Payment_Request_Data_To_Express_Checkout_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Migrate_Payment_Request_Data_To_Express_Checkout_Data
 *
 * Migrates Payment Request settings data to Express Checkout settings data.
 *
 * @since 9.1.0
 */
class Migrate_Payment_Request_Data_To_Express_Checkout_Data {
	/**
	 * Migrate_Payment_Request_Data_To_Express_Checkout_Data constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_stripe_updated', [ $this, 'maybe_migrate' ] );
	}

	/**
	 * Only execute the migration if not applied yet.
	 */
	public function maybe_migrate() {
		$stripe_gateway = $this->get_gateway();

		$express_checkout_enabled = $stripe_gateway->get_option( 'express_checkout' );

		if ( empty( $express_checkout_enabled ) ) {
			$this->migrate();
		}
	}

	/**
	 * Copies over Payment Request settings data to Express Checkout settings data.
	 */
	private function migrate() {
		$stripe_gateway = $this->get_gateway();

		$payment_request_enabled          = $stripe_gateway->get_option( 'payment_request', 'no' );
		$payment_request_button_type      = $stripe_gateway->get_option( 'payment_request_button_type', 'default' );
		$payment_request_button_theme     = $stripe_gateway->get_option( 'payment_request_button_theme', 'dark' );
		$payment_request_button_size      = $stripe_gateway->get_option( 'payment_request_button_size', 'default' );
		$payment_request_button_locations = $stripe_gateway->get_option( 'payment_request_button_locations', [ 'checkout' ] );

		$stripe_gateway->update_option( 'express_checkout', $payment_request_enabled );
		$stripe_gateway->update_option( 'express_checkout_button_type', $payment_request_button_type );
		$stripe_gateway->update_option( 'express_checkout_button_theme', $payment_request_button_theme );
		$stripe_gateway->update_option( 'express_checkout_button_size', $payment_request_button_size );
		$stripe_gateway->update_option( 'express_checkout_button_locations', $payment_request_button_locations );
	}

	/**
	 * Returns the main Stripe payment gateways.
	 *
	 * @return WC_Stripe_Payment_Gateway
	 */
	public function get_gateway() {
		return woocommerce_gateway_stripe()->get_main_stripe_gateway();
	}
}
