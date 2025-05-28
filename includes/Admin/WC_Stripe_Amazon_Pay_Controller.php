<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for Amazon Pay Customize Express Checkouts.
 */
class WC_Stripe_Amazon_Pay_Controller {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wc_stripe_gateway_admin_options_wrapper', [ $this, 'admin_options' ] );
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/amazon-pay-settings.asset.php';
		$asset_metadata    = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];
		wp_register_script(
			'wc-stripe-amazon-pay-settings',
			plugins_url( 'build/amazon-pay-settings.js', WC_STRIPE_MAIN_FILE ),
			$asset_metadata['dependencies'],
			$asset_metadata['version'],
			true
		);
		wp_set_script_translations(
			'wc-stripe-amazon-pay-settings',
			'woocommerce-gateway-stripe'
		);
		wp_enqueue_script( 'wc-stripe-amazon-pay-settings' );

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$params          = [
			'key'            => WC_Stripe_Mode::is_test() ? $stripe_settings['test_publishable_key'] : $stripe_settings['publishable_key'],
			'locale'         => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
			'is_ece_enabled' => WC_Stripe_Feature_Flags::is_stripe_ece_enabled(),
		];
		wp_localize_script(
			'wc-stripe-amazon-pay-settings',
			'wc_stripe_amazon_pay_settings_params',
			$params
		);

		wp_register_style(
			'wc-stripe-amazon-pay-settings',
			plugins_url( 'build/amazon-pay-settings.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			$asset_metadata['version']
		);
		wp_enqueue_style( 'wc-stripe-amazon-pay-settings' );
	}

	/**
	 * Prints the admin options for the gateway.
	 * Remove this action once we're fully migrated to UPE and move the wrapper in the `admin_options` method of the UPE gateway.
	 */
	public function admin_options() {
		global $hide_save_button;
		$hide_save_button = true;
		$return_url      = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' );
		$header          = __( 'Customize express checkouts', 'woocommerce-gateway-stripe' );
		$return_text     = __( 'Return to Stripe', 'woocommerce-gateway-stripe' );

		WC_Stripe_Helper::render_admin_header( $header, $return_text, $return_url );
		echo '<div class="wrap"><div id="wc-stripe-amazon-pay-settings-container"></div></div>';
	}
}
