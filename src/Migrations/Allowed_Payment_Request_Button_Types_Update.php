<?php
/**
 * Class Allowed_Payment_Request_Button_Types_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Allowed_Payment_Request_Button_Types_Update
 *
 * Remaps deprecated payment request button types to fallback values.
 *
 * @since 5.6.0
 */
class Allowed_Payment_Request_Button_Types_Update {
	/**
	 * Allowed_Payment_Request_Button_Types_Update constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_stripe_updated', [ $this, 'maybe_migrate' ] );
	}

	/**
	 * Only execute the migration if not applied yet.
	 */
	public function maybe_migrate() {
		// not compatible with older WC versions, due to the missing `update_option` method on the gateway class
		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			return;
		}

		$stripe_gateway = $this->get_gateway();

		// "custom" or "branded" are no longer valid values for the button type - map them to new ones
		$button_type = $stripe_gateway->get_option( 'payment_request_button_type' );
		if ( in_array( $button_type, [ 'branded', 'custom' ], true ) ) {
			$branded_type = $stripe_gateway->get_option( 'payment_request_button_branded_type' );
			$stripe_gateway->update_option(
				'payment_request_button_type',
				$this->map_button_type( $button_type, $branded_type )
			);
		}
	}

	/**
	 * Maps deprecated button types to fallback values.
	 *
	 * @param mixed $button_type "payment_request_button_type" value.
	 * @param mixed $branded_type "payment_request_button_branded_type" value.
	 *
	 * @return mixed
	 */
	private function map_button_type( $button_type, $branded_type ) {
		// "branded" with "logo only" => "default" (same result)
		if ( 'branded' === $button_type && 'short' === $branded_type ) {
			return 'default';
		}

		// "branded" with anything else (which would be "long"/"Text and logo") => "buy"
		if ( 'branded' === $button_type ) {
			return 'buy';
		}

		// "custom" is no longer valid => "default"
		if ( 'custom' === $button_type ) {
			return 'buy';
		}

		// anything else is good
		return $button_type;
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
