<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controls whether we're on the settings page and enqueues the JS code.
 *
 * @since 5.4.1
 */
class WC_Stripe_Settings_Controller {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wc_stripe_gateway_admin_options_wrapper', [ $this, 'admin_options' ] );
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
		echo '<h2>' . esc_html( $gateway->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo '<div id="wc-stripe-account-settings-container"></div>';
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( WC_Stripe_Feature_Flags::is_upe_settings_redesign_enabled() ) {
			// Webpack generates an assets file containing a dependencies array for our built JS file.
			$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/upe_settings.asset.php';
			$script_asset      = file_exists( $script_asset_path )
				? require $script_asset_path
				: [
					'dependencies' => [],
					'version'      => WC_STRIPE_VERSION,
				];

			wp_register_script(
				'woocommerce_stripe_admin',
				plugins_url( 'build/upe_settings.js', WC_STRIPE_MAIN_FILE ),
				$script_asset['dependencies'],
				$script_asset['version'],
				true
			);
			wp_register_style(
				'woocommerce_stripe_admin',
				plugins_url( 'build/style-upe_settings.css', WC_STRIPE_MAIN_FILE ),
				[ 'wc-components' ],
				$script_asset['version']
			);
			wp_enqueue_style( 'woocommerce_stripe_admin' );
		} else {
			wp_register_script( 'woocommerce_stripe_admin', plugins_url( 'assets/js/stripe-admin' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), [], WC_STRIPE_VERSION, true );
		}

		$params = [
			'time'                    => time(),
			'i18n_out_of_sync'        => wp_kses(
				__( '<strong>Warning:</strong> your site\'s time does not match the time on your browser and may be incorrect. Some payment methods depend on webhook verification and verifying webhooks with a signing secret depends on your site\'s time being correct, so please check your site\'s time before setting a webhook secret. You may need to contact your site\'s hosting provider to correct the site\'s time.', 'woocommerce-gateway-stripe' ),
				[ 'strong' => [] ]
			),
			'is_upe_checkout_enabled' => WC_Stripe_Feature_Flags::is_upe_checkout_enabled(),
		];
		wp_localize_script( 'woocommerce_stripe_admin', 'wc_stripe_settings_params', $params );

		wp_enqueue_script( 'woocommerce_stripe_admin' );
	}
}
