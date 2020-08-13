<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Stripe (New) Checkout payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.6.0
 */
class WC_Gateway_Stripe_Checkout extends WC_Stripe_Payment_Gateway {
	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = 'stripe_checkout';
		$this->method_title = __( 'Stripe Checkout', 'woocommerce-gateway-stripe' );
		/* translators: link */
		$this->method_description = sprintf( __( 'All other general Stripe settings can be adjusted <a href="%s">here</a>.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @since 4.6.0
	 * @return array
	 */
	public function get_supported_currency() {
		return apply_filters(
			'wc_stripe_checkout_supported_currencies',
			array(
				'USD',
				'EUR',
			)
		);
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @since 4.6.0
	 * @return bool
	 */
	public function is_available() {
		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 *
	 * @since 4.6.0
	 */
	public function init_form_fields() {
		$this->form_fields = require( WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-checkout-settings.php' );
	}

	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		wp_enqueue_style( 'stripe_styles' );
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Payment form on checkout page.
	 *
	 * @since 4.6.0
	 */
	public function payment_fields() {
		$description = $this->get_description();		

		// TODO: Handle paying from pay_for_order.

		// Request session from stripe.
		$stripe_session = new WC_Stripe_Session();
		$stripe_session->create_session(
			[ 'success_url' => $this->get_return_url() ]
		);

		echo '<div id="stripe-checkout-data" data-secret="' . esc_attr( $stripe_session->get_id() ) . '"></div>';

		if ( $description ) {
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $description ) ), $this->id );
		}
	}
}
