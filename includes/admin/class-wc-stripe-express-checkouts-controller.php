<?php

use Automattic\WooCommerce\Admin\PageController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for UPE Customize Express Checkouts.
 *
 * @since 5.4.1
 */
class WC_Stripe_Express_Checkouts_Controller {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wc_stripe_gateway_admin_options_wrapper', [ $this, 'admin_options' ] );
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_path       = 'build/express_checkouts_customizer.js';
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/express_checkouts_customizer.asset.php';
		$script_url        = plugins_url( $script_path, WC_STRIPE_MAIN_FILE );
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];
		wp_register_script(
			'wc_stripe-express_checkouts_customizer',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_enqueue_script( 'wc_stripe-express_checkouts_customizer' );
	}

	/**
	 * Prints the admin options for the gateway.
	 * Remove this action once we're fully migrated to UPE and move the wrapper in the `admin_options` method of the UPE gateway.
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway the Stripe gateway.
	 */
	public function admin_options( WC_Stripe_Payment_Gateway $gateway ) {
		global $hide_save_button;
		$hide_save_button = true;
		echo '<h2>Customize express checkouts';
		wc_back_link( __( 'Return to Stripe', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
		echo '</h2>';
		echo '<div class="wrap"><div id="wc_stripe-express_checkouts_customizer-container"></div></div>';
	}
}
