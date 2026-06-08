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
	 * Singleton instance of the update manager.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the update manager.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Run update checks when a new version of the plugin is being installed.
	 * This can include updates, downgrades, and fresh installs.
	 *
	 * @param string $previous_version The previous version of the plugin.
	 * @return void
	 * @since 10.8.0
	 */
	public static function run_update_checks( $previous_version ): void {
		$update_manager = self::get_instance();
		$update_manager->run_update_functions( $previous_version );

		/**
		 * Action triggered when the plugin is updated.
		 */
		do_action( 'woocommerce_stripe_updated' );
	}

	/**
	 * Run the update functions.
	 *
	 * @param string $previous_version The previous version of the plugin.
	 * @return void
	 */
	protected function run_update_functions( $previous_version ): void {
		foreach ( $this->get_update_functions() as $check ) {
			call_user_func( $check, $previous_version );
		}
	}

	/**
	 * Get the update functions to run.
	 *
	 * @return callable[]
	 */
	protected function get_update_functions(): array {
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-admin-notices.php';

		$functions = [
			[ WC_Stripe_Admin_Notices::class, 'check_update_notices' ],
			[ new Allowed_Payment_Request_Button_Types_Update(), 'maybe_migrate' ],
			[ new Migrate_Payment_Request_Data_To_Express_Checkout_Data(), 'maybe_migrate' ],
			[ new Sepa_Tokens_For_Other_Methods_Settings_Update(), 'maybe_migrate' ],
			[ new WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update(), 'maybe_migrate' ],
			[ new WC_Stripe_OCS_AP_Default_On_Update(), 'maybe_migrate' ],
		];

		return $functions;
	}
}
