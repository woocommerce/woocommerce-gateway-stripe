<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Update_Manager class.
 *
 * @since 10.8.0
 */
class WC_Stripe_Update_Manager {

	/**
	 * Run update checks when a new version of the plugin is being installed.
	 * This can include updates, downgrades, and fresh installs.
	 *
	 * @param string $previous_version The previous version of the plugin.
	 * @param string $current_version  The current version of the plugin.
	 * @return void
	 */
	public static function run_update_checks( $previous_version, $current_version ): void {
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-admin-notices.php';

		// Check for any notices to display after an update.
		WC_Stripe_Admin_Notices::check_update_notices( $previous_version );

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-allowed-payment-request-button-types-update.php';
		( new Allowed_Payment_Request_Button_Types_Update() )->maybe_migrate();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-migrate-payment-request-data-to-express-checkout-data.php';
		( new Migrate_Payment_Request_Data_To_Express_Checkout_Data() )->maybe_migrate();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-sepa-tokens-for-other-methods-settings-update.php';
		( new Sepa_Tokens_For_Other_Methods_Settings_Update() )->maybe_migrate();

		( new WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update() )->maybe_migrate();

		/**
		 * Action triggered when the plugin is updated.
		 */
		do_action( 'woocommerce_stripe_updated' );
	}
}
