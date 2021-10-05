<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class that handles various admin tasks.
 *
 * @since 5.6.0
 */
class WC_Stripe_Payment_Gateways_Controller {
	/**
	 * Constructor
	 *
	 * @since 5.6.0
	 */
	public function __construct() {
		// If UPE is enabled and there are enabled payment methods, we need to load the disable Stripe confirmation modal.
		$stripe_settings              = get_option( 'woocommerce_stripe_settings', [] );
		$enabled_upe_payment_methods  = isset( $stripe_settings['upe_checkout_experience_accepted_payments'] ) ? $stripe_settings['upe_checkout_experience_accepted_payments'] : [];
		$upe_payment_requests_enabled = 'yes' === $stripe_settings['payment_request'];

		if ( count( $enabled_upe_payment_methods ) > 0 || $upe_payment_requests_enabled ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_payments_scripts' ] );
			add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'wc_stripe_gateway_container' ] );
		}

		$show_information_overlay = get_option( 'wc_stripe_show_information_overlay' );

		if ( empty( $show_information_overlay ) ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'information_overlay_script' ] );
			add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'wc_stripe_information_overlay_container' ], 5 );
		}
	}

	public function register_payments_scripts() {
		$payment_gateways_script_src_url    = plugins_url( 'build/payment_gateways.js', WC_STRIPE_MAIN_FILE );
		$payment_gateways_script_asset_path = WC_STRIPE_PLUGIN_PATH . 'build/payment_gateways.asset.php';
		$payment_gateways_script_asset      = file_exists( $payment_gateways_script_asset_path )
			? require_once $payment_gateways_script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'woocommerce_stripe_payment_gateways_page',
			$payment_gateways_script_src_url,
			$payment_gateways_script_asset['dependencies'],
			$payment_gateways_script_asset['version'],
			true
		);
		wp_register_style(
			'woocommerce_stripe_payment_gateways_page',
			plugins_url( 'build/payment_gateways.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			$payment_gateways_script_asset['version']
		);
	}

	public function enqueue_payments_scripts() {
		global $current_tab, $current_section;

		$this->register_payments_scripts();

		$is_payment_methods_page = (
			is_admin() &&
			$current_tab && ! $current_section
			&& 'checkout' === $current_tab
		);

		if ( $is_payment_methods_page ) {
			wp_enqueue_script( 'woocommerce_stripe_payment_gateways_page' );
			wp_enqueue_style( 'woocommerce_stripe_payment_gateways_page' );
		}
	}

	/**
	 * Adds a container to the "payment gateways" page.
	 * This is where the "Are you sure you want to disable Stripe?" confirmation dialog is rendered.
	 */
	public function wc_stripe_gateway_container() {
		?><div id="wc-stripe-payment-gateways-container" />
		<?php
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
		?>
		<div id="wc-stripe-information-overlay-container" />
		<?php
	}

}
