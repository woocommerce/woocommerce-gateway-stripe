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
	const SCREEN_ID = 'admin_page_wc_stripe-onboarding_wizard';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_onboarding_wizard' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return;
		}

		if ( empty( $current_screen->id ) || self::SCREEN_ID !== $current_screen->id ) {
			return;
		}

		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_path       = 'build/upe_onboarding_wizard.js';
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/upe_onboarding_wizard.asset.php';
		$script_url        = plugins_url( $script_path, WC_STRIPE_MAIN_FILE );
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];
		$style_path        = 'build/upe_onboarding_wizard.css';
		$style_url         = plugins_url( $style_path, WC_STRIPE_MAIN_FILE );

		wp_register_script(
			'wc_stripe_onboarding_wizard',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_localize_script(
			'wc_stripe_onboarding_wizard',
			'wc_stripe_onboarding_params',
			[
				'is_upe_checkout_enabled' => WC_Stripe_Feature_Flags::is_upe_checkout_enabled(),
			]
		);
		wp_register_style(
			'wc_stripe_onboarding_wizard',
			$style_url,
			[ 'wc-components' ],
			$script_asset['version']
		);

		wp_enqueue_script( 'wc_stripe_onboarding_wizard' );
		wp_enqueue_style( 'wc_stripe_onboarding_wizard' );
	}

	/**
	 * Create an admin page without a side menu: wp-admin/admin.php?page=wc_stripe-onboarding_wizard
	 */
	public function add_onboarding_wizard() {
		// This submenu is hidden from the admin menu
		add_submenu_page(
			'admin.php',
			__( 'Stripe - Onboarding Wizard', 'woocommerce-gateway-stripe' ),
			__( 'Onboarding Wizard', 'woocommerce-gateway-stripe' ),
			'manage_woocommerce',
			'wc_stripe-onboarding_wizard',
			[ $this, 'render_onboarding_wizard' ]
		);

		// Connect PHP-powered admin page to wc-admin
		wc_admin_connect_page(
			[
				'id'        => 'wc-stripe-onboarding-wizard',
				'screen_id' => self::SCREEN_ID,
				'title'     => __( 'Onboarding Wizard', 'woocommerce-gateway-stripe' ),
			]
		);
	}

	/**
	 * Output a container for react app to mount on.
	 */
	public function render_onboarding_wizard() {
		echo '<div class="wrap"><div id="wc-stripe-onboarding-wizard-container"></div></div>';
	}
}
