<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for UPE onboarding wizard.
 *
 * @since 5.4.1
 */
class WC_Stripe_Onboarding_Controller {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_onboarding_wizard' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_path       = 'build/additional_methods_setup.js';
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/additional_methods_setup.asset.php';
		$script_url        = plugins_url( $script_path, WC_STRIPE_MAIN_FILE );
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [ 'dependencies' => [] ];

		wp_register_script( // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			'wc_stripe_onboarding_wizard',
			$script_url,
			$script_asset['dependencies'],
			null,
			true
		);

		wp_enqueue_script( 'wc_stripe_onboarding_wizard' );
	}

	/**
	 * Create an admin page without a side menu: wp-admin/admin.php?page=wc_stripe-onboarding_wizard
	 */
	public function add_onboarding_wizard() {
		add_submenu_page(
			null, // Hide this submenu from admin menu
			__( 'Onboarding Wizard', 'woocommerce-gateway-stripe' ),
			__( 'Onboarding Wizard', 'woocommerce-gateway-stripe' ),
			'manage_woocommerce',
			'wc_stripe-onboarding_wizard',
			[ $this, 'render_onboarding_wizard' ]
		);
	}

	/**
	 * Output a container for react app to mount on.
	 */
	public function render_onboarding_wizard() {
		echo '<div id="wc-stripe-onboarding-wizard-container"></div>';
	}
}
