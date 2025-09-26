<?php

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles giropay payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.0.0
 */
class WC_Gateway_Stripe_Giropay extends WC_Stripe_Payment_Gateway {

	const ID = 'stripe_giropay';

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];

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
		$this->id                 = self::ID;
		$this->method_title       = __( 'Stripe giropay', 'woocommerce-gateway-stripe' );
		$this->method_description = sprintf(
		/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			__( 'All other general Stripe settings can be adjusted %1$shere%2$s.', 'woocommerce-gateway-stripe' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) ) . '">',
			'</a>'
		);
		$this->supports = [
			'products',
			'refunds',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = WC_Stripe_Helper::get_stripe_settings();
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = WC_Stripe_Mode::is_test();
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array
	 */
	public function get_supported_currency() {
		return apply_filters(
			'wc_stripe_giropay_supported_currencies',
			[
				WC_Stripe_Currency_Code::EURO,
			]
		);
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return bool
	 */
	public function is_available() {
		return false;
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

		$icons_str .= isset( $icons['giropay'] ) ? $icons['giropay'] : '';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Outputs scripts used for stripe payment
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! parent::is_valid_pay_for_order_endpoint() && ! is_add_payment_method_page() ) {
			return;
		}

		wp_enqueue_style( 'stripe_styles' );
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-giropay-settings.php';
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $wp;
		$user        = wp_get_current_user();
		$total       = WC()->cart->total;
		$description = $this->get_description();

		// If paying from order, we need to get total from order not cart.
		if ( parent::is_valid_pay_for_order_endpoint() ) {
			$order = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
			$total = $order->get_total();
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Payment', 'woocommerce-gateway-stripe' );
			$total           = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-giropay-payment-data"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $description ) {
			echo wp_kses_post( wpautop( apply_filters( 'wc_stripe_description', $description, $this->id ) ) );
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
		$currency              = $order->get_currency();
		$return_url            = $this->get_stripe_return_url( $order );
		$post_data             = [];
		$post_data['amount']   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		$post_data['currency'] = strtolower( $currency );
		$post_data['type']     = WC_Stripe_Payment_Methods::GIROPAY;
		$post_data['owner']    = WC_Stripe_Order_Helper::get_instance()->get_owner_details( $order );
		$post_data['redirect'] = [ 'return_url' => $return_url ];

		if ( ! empty( $this->statement_descriptor ) ) {
			$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $this->statement_descriptor );
		}

		WC_Stripe_Logger::log( 'Info: Begin creating giropay source' );

		return WC_Stripe_API::request( apply_filters( 'wc_stripe_giropay_source', $post_data, $order ), 'sources' );
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force payment source to be saved.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false ) {
		try {
			$order = wc_get_order( $order_id );

			// This will throw exception if not valid.
			WC_Stripe_Order_Helper::get_instance()->validate_minimum_order_amount( $order );

			// This comes from the create account checkbox in the checkout page.
			$create_account = ! empty( $_POST['createaccount'] ) ? true : false;

			if ( $create_account ) {
				$new_customer_id     = $order->get_customer_id();
				$new_stripe_customer = new WC_Stripe_Customer( $new_customer_id );
				$new_stripe_customer->create_customer();
			}

			$response = $this->create_source( $order );

			if ( ! empty( $response->error ) ) {
				$order->add_order_note( $response->error->message );

				throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
			}

			$order->update_meta_data( '_stripe_source_id', $response->id );
			$order->save();

			WC_Stripe_Logger::log( 'Info: Redirecting to giropay...' );

			return [
				'result'   => 'success',
				'redirect' => esc_url_raw( $response->redirect->url ),
			];
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			if ( $order->has_status(
				apply_filters(
					'wc_stripe_allowed_payment_processing_statuses',
					[ OrderStatus::PENDING, OrderStatus::FAILED ],
					$order
				)
			) ) {
				$this->send_failed_order_email( $order_id );
			}

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}
}
