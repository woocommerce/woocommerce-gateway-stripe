<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backwards-compatibility shim for the now-inlined Stripe settings page logic.
 *
 * The hooks and methods previously registered here now live on `WC_Stripe`. This
 * class is retained only so external code that instantiated it continues to load,
 * and so the public `hide_gateways_on_settings_page` static keeps working when
 * called directly. Each entry point delegates to `WC_Stripe` and emits a
 * deprecation notice.
 *
 * @deprecated 10.7.0 Use the equivalent methods on `WC_Stripe`.
 */
class WC_Stripe_Settings_Controller {
	/**
	 * Constructor (deprecation shim).
	 *
	 * @param WC_Stripe_Account              $account Stripe account (unused — kept for signature compatibility).
	 * @param WC_Stripe_Payment_Gateway|null $gateway Stripe gateway (unused — kept for signature compatibility).
	 */
	public function __construct( WC_Stripe_Account $account, ?WC_Stripe_Payment_Gateway $gateway = null ) {
		_deprecated_function( __CLASS__, '10.7.0', 'WC_Stripe' );
	}

	/**
	 * Delegates to the equivalent method on `WC_Stripe`.
	 *
	 * @deprecated 10.7.0 Use `WC_Stripe::hide_refund_button_for_uncaptured_orders()`.
	 */
	public function hide_refund_button_for_uncaptured_orders( $order ) {
		_deprecated_function( __METHOD__, '10.7.0', 'WC_Stripe::hide_refund_button_for_uncaptured_orders' );
		WC_Stripe::get_instance()->hide_refund_button_for_uncaptured_orders( $order );
	}

	/**
	 * Delegates to the equivalent method on `WC_Stripe`.
	 *
	 * @deprecated 10.7.0 Use `WC_Stripe::render_settings_admin_options()`.
	 */
	public function admin_options( WC_Stripe_Payment_Gateway $gateway ) {
		_deprecated_function( __METHOD__, '10.7.0', 'WC_Stripe::render_settings_admin_options' );
		WC_Stripe::get_instance()->render_settings_admin_options( $gateway );
	}

	/**
	 * Delegates to the equivalent method on `WC_Stripe`.
	 *
	 * @deprecated 10.7.0 Use `WC_Stripe::ajax_get_oauth_url()`.
	 */
	public function ajax_get_oauth_url() {
		_deprecated_function( __METHOD__, '10.7.0', 'WC_Stripe::ajax_get_oauth_url' );
		WC_Stripe::get_instance()->ajax_get_oauth_url();
	}

	/**
	 * Delegates to the equivalent method on `WC_Stripe`.
	 *
	 * @deprecated 10.7.0 Use `WC_Stripe::enqueue_settings_admin_scripts()`.
	 */
	public function admin_scripts( $hook_suffix ) {
		_deprecated_function( __METHOD__, '10.7.0', 'WC_Stripe::enqueue_settings_admin_scripts' );
		WC_Stripe::get_instance()->enqueue_settings_admin_scripts( $hook_suffix );
	}

	/**
	 * Delegates to the equivalent method on `WC_Stripe`.
	 *
	 * @deprecated 10.7.0 Use `WC_Stripe::hide_payment_gateways_on_settings_page()`.
	 */
	public static function hide_gateways_on_settings_page() {
		_deprecated_function( __METHOD__, '10.7.0', 'WC_Stripe::hide_payment_gateways_on_settings_page' );
		WC_Stripe::hide_payment_gateways_on_settings_page();
	}
}
