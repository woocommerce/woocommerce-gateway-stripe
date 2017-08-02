<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Giropay payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.0.0
 */
class WC_Gateway_Stripe_Giropay extends WC_Stripe_Payment_Gateway {
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'stripe_giropay';
		$this->method_title         = __( 'Stripe Giropay', 'woocommerce-gateway-stripe' );
		$this->method_description   = sprintf( __( 'All other general Stripe settings can be adjusted <a href="%s">here</a>.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'check_environment' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * Checks to make sure environment is setup correctly to use this payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function check_environment() {
		$environment_warning = $this->get_environment_warning();

		if ( $environment_warning ) {
			$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
		}

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo '</p></div>';
		}
	}

	/**
	 * Checks the environment for compatibility problems. Returns a string with the first incompatibility
	 * found or false if the environment has no problems.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_environment_warning() {
		if ( 'yes' === $this->enabled && ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			$message = __( 'Giropay is enabled - it requires store currency to be set to Euros.', 'woocommerce-gateway-stripe' );

			return $message;
		}

		return false;
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array
	 */
	public function get_supported_currency() {
		return apply_filters( 'wc_stripe_giropay_supported_currencies', array(
			'EUR',
		) );
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled || ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * All payment icons that work with Stripe.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array
	 */
	public function payment_icons() {
		return apply_filters( 'wc_stripe_payment_icons', array(
			'giropay' => '<i class="stripe-pf stripe-pf-giropay stripe-pf-right" alt="Giropay" aria-hidden="true"></i>',
		) );
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= $icons['giropay'];

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'stripe_paymentfonts' );

		if ( apply_filters( 'wc_stripe_use_elements_checkout_form', true ) ) {
			wp_enqueue_script( 'stripev3' );
			wp_enqueue_script( 'woocommerce_stripe_elements' );
		} else {
			wp_enqueue_script( 'woocommerce_stripe' );
		}
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require( WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-giropay-settings.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user                 = wp_get_current_user();
		$total                = WC()->cart->total;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Payment', 'woocommerce-gateway-stripe' );
			$total        = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-giropay-payment-data"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $this->description ) {
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		echo '</div>';
	}

	/**
	 * Creates the source for charge.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @return mixed
	 */
	public function create_source( $order ) {
		$currency              = WC_Stripe_Helper::is_pre_30() ? $order->get_order_currency() : $order->get_currency();
		$order_id              = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		$return_url            = $this->get_stripe_return_url( $order );
		$post_data             = array();
		$post_data['amount']   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		$post_data['currency'] = strtolower( $currency );
		$post_data['type']     = 'giropay';
		$post_data['owner']    = $this->get_owner_details( $order );
		$post_data['redirect'] = array( 'return_url' => $return_url );
		$post_data['giropay']  = array( 'statement_descriptor' => $this->statement_descriptor );

		WC_Stripe_Logger::log( 'Info: Begin creating Giropay source' );

		return WC_Stripe_API::request( $post_data, 'sources' );
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order = wc_get_order( $order_id );

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
				}

				$response = $this->create_source( $order );

				if ( is_wp_error( $response ) ) {
					$message = $response->get_error_message();

					$order->add_order_note( $message );

					throw new Exception( $message );
				}

				if ( WC_Stripe_Helper::is_pre_30() ) {
					update_post_meta( $order_id, '_stripe_source_id', $response->id );
				} else {
					$order->update_meta_data( '_stripe_source_id', $response->id );
					$order->save();
				}

				WC_Stripe_Logger::log( 'Info: Redirecting to Giropay...' );

				return array(
					'result'   => 'success',
					'redirect' => esc_url_raw( $response->redirect->url ),
				);
			} else {
				do_action( 'wc_gateway_stripe_process_payment', $response, $order );

				$order->payment_complete();
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}
}
