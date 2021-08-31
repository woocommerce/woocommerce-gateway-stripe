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
		add_action( 'admin_menu', [ $this, 'add_customization_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

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
	 * Create an admin page without a side menu: wp-admin/admin.php?page=wc_stripe-express_checkouts_customizer
	 */
	public function add_customization_page() {
		add_submenu_page(
			null, // Hide this submenu from admin menu
			__( 'Customize Express Checkouts', 'woocommerce-gateway-stripe' ),
			__( 'Customize Express Checkouts', 'woocommerce-gateway-stripe' ),
			'manage_woocommerce',
			'wc_stripe-express_checkouts_customizer',
			[ $this, 'render_express_checkouts_customizer' ]
		);

		// Connect PHP-powered admin page to wc-admin
		wc_admin_connect_page(
			[
				'id'        => 'wc-stripe-onboarding-wizard',
				'screen_id' => 'admin_page_wc_stripe-express_checkouts_customizer',
				'title'     => __( 'Customize Express Checkouts', 'woocommerce-gateway-stripe' ),
			]
		);
	}

	/**
	 * Output a container for react app to mount on.
	 */
	public function render_express_checkouts_customizer() {
		echo '<div class="wrap"><div id="wc_stripe-express_checkouts_customizer-container"></div></div>';
	}
}
