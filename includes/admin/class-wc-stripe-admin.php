<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class that handles various admin tasks.
 *
 * @since 4.1.0
 */
class WC_Stripe_Admin {
	/**
	 * Constructor
	 *
	 * @since 4.1.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_payments_scripts' ] );
		add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'wc_stripe_gateway_container' ] );
	}

	public function register_payments_scripts() {
		$payment_gateways_script_src_url    = plugins_url( 'build/payment_gateways.js', WC_STRIPE_MAIN_FILE );
		$payment_gateways_script_asset_path = WC_STRIPE_ABSPATH . 'build/payment_gateways.asset.php';
		$payment_gateways_script_asset      = file_exists( $payment_gateways_script_asset_path ) ? require_once $payment_gateways_script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'WC_STRIPE_PAYMENT_GATEWAYS_PAGE',
			$payment_gateways_script_src_url,
			$payment_gateways_script_asset['dependencies'],
			WC_Stripe::get_file_version( 'build/payment_gateways.js' ),
			true
		);
		wp_register_style(
			'WC_STRIPE_PAYMENT_GATEWAYS_PAGE',
			plugins_url( 'build/payment_gateways.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			WC_Stripe::get_file_version( 'build/payment_gateways.css' )
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

		// Update this conditional to see if we need to load the modal code
		// Don't show if:
			// UPE is not enabled
			// No UPE payment methods have been added
		$enabled_upe_payment_methods = get_option( 'woocommerce_stripe_settings', [] )['upe_checkout_experience_accepted_payments'];
		if ( $is_payment_methods_page && WC_Stripe_Feature_Flags::is_upe_checkout_enabled() && count( $enabled_upe_payment_methods ) > 0 ) {
			wp_enqueue_script( 'WC_STRIPE_PAYMENT_GATEWAYS_PAGE' );
			wp_enqueue_style( 'WC_STRIPE_PAYMENT_GATEWAYS_PAGE' );
		}
	}

	/**
	 * Adds a container to the "payment gateways" page.
	 * This is where the "Are you sure you want to disable WCPay?" confirmation dialog is rendered.
	 */
	public function wc_stripe_gateway_container() {
		?><div id="wc-stripe-payment-gateways-container" />
		<?php
	}

}

new WC_Stripe_Admin();
