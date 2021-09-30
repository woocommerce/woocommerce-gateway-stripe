<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues some JS to show an overlay with Stripe related information after UPE is enabled.
 * The overlay appears only once after enabling UPE and after dismiss it never appears again.
 *
 * @since 5.5.0
 */
class WC_Stripe_Information_Overlay {
	public function __construct() {
		$show_information_overlay = get_option( 'wc_stripe_show_information_overlay' );

		if ( empty( $show_information_overlay ) ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'information_overlay_script' ] );
			add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'wc_stripe_information_overlay_container' ], 5 );
		}
	}

	/**
	 * Enqueues the script to show overlay once UPE has been enabled.
	 */
	public function information_overlay_script() {
		if ( ! is_admin() ) {
			return;
		}

		global $current_tab, $current_section;

		if ( ! isset( $current_tab ) || 'checkout' !== $current_tab ) {
			return;
		}

		if ( ! empty( $current_section ) ) {
			return;
		}

		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/upe_information_overlay.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'woocommerce_stripe_upe_information_overlay',
			plugins_url( 'build/upe_information_overlay.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_enqueue_script( 'woocommerce_stripe_upe_information_overlay' );
	}

	/**
	 * Adds a container to the "payment gateways" page to render the information overlay
	 */
	public function wc_stripe_information_overlay_container() {
		?><div id="wc-stripe-information-overlay-container" />
		<?php
	}
}
