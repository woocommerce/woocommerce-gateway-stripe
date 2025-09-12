<?php
/**
 * Class Sepa_Tokens_For_Other_Methods_Settings_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Sepa_Tokens_For_Other_Methods_Settings_Update
 *
 * Remaps the deprecated setting key for "SEPA tokens for other methods" to the newly, split version.
 *
 * @since 10.0.0
 */
class Sepa_Tokens_For_Other_Methods_Settings_Update {
	/**
	 * Sepa_Tokens_For_Other_Methods_Settings_Update constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_stripe_updated', [ $this, 'maybe_migrate' ] );
	}

	/**
	 * Only execute the migration if not applied yet.
	 */
	public function maybe_migrate() {
		$stripe_gateway = $this->get_gateway();

		// If the new settings are already set, skip migration.
		$valid_values = [ 'yes', 'no' ];
		if ( in_array( $stripe_gateway->get_option( 'sepa_tokens_for_ideal' ), $valid_values, true )
			|| in_array( $stripe_gateway->get_option( 'sepa_tokens_for_bancontact' ), $valid_values, true ) ) {
			return;
		}

		/**
		 * TODO: Remove this setting and the migration logic after 10.2.0 is released.
		 */
		$value = $stripe_gateway->get_option( 'sepa_tokens_for_other_methods', 'no' );

		$stripe_gateway->update_option( 'sepa_tokens_for_ideal', $value );
		$stripe_gateway->update_option( 'sepa_tokens_for_bancontact', $value );
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
