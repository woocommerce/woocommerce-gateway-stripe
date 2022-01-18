<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues some JS to ensure that some needed UI elements for the old settings are available.
 *
 * @since 5.5.0
 */
class WC_Stripe_Old_Settings_UPE_Toggle_Controller {
	protected $was_upe_checkout_enabled = null;

	public function __construct() {
		add_filter( 'pre_update_option_woocommerce_stripe_settings', [ $this, 'pre_options_save' ] );
		add_action( 'update_option_woocommerce_stripe_settings', [ $this, 'maybe_enqueue_script' ] );
	}

	/**
	 * Stores whether UPE was enabled before saving the options.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function pre_options_save( $value ) {
		$this->was_upe_checkout_enabled = WC_Stripe_Feature_Flags::is_upe_checkout_enabled();

		return $value;
	}

	/**
	 * Determines what to do after the options have been saved.
	 */
	public function maybe_enqueue_script() {
		$is_upe_checkout_enabled = WC_Stripe_Feature_Flags::is_upe_checkout_enabled();

		if ( $this->was_upe_checkout_enabled !== $is_upe_checkout_enabled ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'upe_toggle_script' ] );
		}
	}

	/**
	 * Enqueues the script to determine what to do once UPE has been toggled.
	 */
	public function upe_toggle_script() {
		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/old_settings_upe_toggle.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'woocommerce_stripe_old_settings_upe_toggle',
			plugins_url( 'build/old_settings_upe_toggle.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_localize_script(
			'woocommerce_stripe_old_settings_upe_toggle',
			'wc_stripe_old_settings_param',
			[
				'was_upe_enabled' => $this->was_upe_checkout_enabled,
				'is_upe_enabled'  => WC_Stripe_Feature_Flags::is_upe_checkout_enabled(),
			]
		);
		wp_set_script_translations(
			'woocommerce_stripe_old_settings_upe_toggle',
			'woocommerce-gateway-stripe'
		);
		wp_enqueue_script( 'woocommerce_stripe_old_settings_upe_toggle' );
	}
}
