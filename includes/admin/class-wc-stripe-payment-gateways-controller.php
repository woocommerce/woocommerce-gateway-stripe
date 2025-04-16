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
		$stripe_settings              = WC_Stripe_Helper::get_stripe_settings();
		$enabled_upe_payment_methods  = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
		$upe_payment_requests_enabled = 'yes' === $stripe_settings['payment_request'];

		if ( ( is_array( $enabled_upe_payment_methods ) && count( $enabled_upe_payment_methods ) > 0 ) || $upe_payment_requests_enabled ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_payments_scripts' ] );
			add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'wc_stripe_gateway_container' ] );
		}
	}

	public function register_payments_scripts() {
		$payment_gateways_script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/payment-gateways.asset.php';
		$payment_gateways_script_asset      = file_exists( $payment_gateways_script_asset_path )
			? require_once $payment_gateways_script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'woocommerce_stripe_payment_gateways_page',
			plugins_url( 'build/payment-gateways.js', WC_STRIPE_MAIN_FILE ),
			$payment_gateways_script_asset['dependencies'],
			$payment_gateways_script_asset['version'],
			true
		);
		wp_set_script_translations(
			'woocommerce_stripe_payment_gateways_page',
			'woocommerce-gateway-stripe'
		);
		wp_register_style(
			'woocommerce_stripe_payment_gateways_page',
			plugins_url( 'build/payment-gateways.css', WC_STRIPE_MAIN_FILE ),
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
}
