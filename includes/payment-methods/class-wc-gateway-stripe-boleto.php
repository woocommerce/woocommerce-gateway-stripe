<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Boleto payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.0.0
 */
class WC_Gateway_Stripe_Boleto extends WC_Stripe_Payment_Gateway {

	const ID = 'stripe_boleto';

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
		$this->id           = self::ID;
		$this->method_title = __( 'Stripe Boleto', 'woocommerce-gateway-stripe' );
		/* translators: link */
		$this->method_description = sprintf( __( 'All other general Stripe settings can be adjusted <a href="%s">here</a>.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
		$this->supports           = [
			'products',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_action( 'wc_ajax_wc_stripe_boleto_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_wc_stripe_boleto_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );
	}

	/**
	 * Handle AJAX requests for creating a payment intent for Stripe UPE.
	 */
	public function create_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_boleto_create_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			// If paying from order, we need to get the total from the order instead of the cart.
			$order_id = isset( $_POST['stripe_order_id'] ) ? absint( $_POST['stripe_order_id'] ) : null;

			wp_send_json_success( $this->create_payment_intent( $order_id ), 200 );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Create payment intent error: ' . $e->getMessage() );
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
		}
	}

	/**
	 * Creates payment intent using current cart or order and store details.
	 *
	 * @param {int} $order_id The id of the order if intent created from Order.
	 * @throws Exception - If the create intent call returns with an error.
	 * @return array
	 */
	public function create_payment_intent( $order_id = null ) {
		$amount   = WC()->cart->get_total( false );
		$currency = 'BRL';
		$order    = wc_get_order( $order_id );
		if ( is_a( $order, 'WC_Order' ) ) {
			$amount = $order->get_total();
		}

		$payment_intent = WC_Stripe_API::request(
			[
				'amount'               => WC_Stripe_Helper::get_stripe_amount( $amount, strtolower( $currency ) ),
				'currency'             => strtolower( $currency ),
				'payment_method_types' => [ 'boleto' ],
			],
			'payment_intents'
		);

		if ( ! empty( $payment_intent->error ) ) {
			throw new Exception( $payment_intent->error->message );
		}

		return [
			'id'            => $payment_intent->id,
			'client_secret' => $payment_intent->client_secret,
		];
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
			'wc_stripe_boleto_supported_currencies',
			[
				'BRL',
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
		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			return false;
		}

		return parent::is_available();
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

		$icons_str .= isset( $icons['boleto'] ) ? $icons['boleto'] : '';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Payment_scripts function.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		wp_enqueue_style( 'stripe_styles' );
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-boleto-settings.php';
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
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
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
			id="stripe-boleto-payment-data"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $description ) {
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $description ) ), $this->id );
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
	public function create_source( WC_Order $order ) {
		$currency  = $order->get_currency();
		$post_data = [
			'amount'               => WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency ),
			'currency'             => strtolower( $currency ),
			'payment_method_types' => [ 'boleto' ],
		];

		if ( ! empty( $this->statement_descriptor ) ) {
			$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $this->statement_descriptor );
		}

		WC_Stripe_Logger::log( 'Info: Begin creating Boleto source' );
		$adf = WC_Stripe_API::request( apply_filters( 'wc_stripe_boleto_source', $post_data, $order ), 'payment_intents' );

		return $adf;
	}

	private function validate_amount_limits( $order ) {
		$this->validate_minimum_order_amount( $order );

		if ( $order->get_total() < 5.00 ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( 'Did not meet limit amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( 5.00 ) ) );
		}

		if ( $order->get_total() > 49999.99 ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( 'Did not meet maximum amount', sprintf( __( 'Sorry, the maximum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( 49999.99 ) ) );
		}
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
	public function process_payment( $order_id, $retry = true, $force_save_save = false ) {
		try {
			global $woocommerce;

			$order = wc_get_order( $order_id );
			$order->update_status( 'on-hold', __( 'Awaiting boleto payment.', 'woocommerce-gateway-stripe' ) );

			wc_reduce_stock_levels( $order_id );
			$woocommerce->cart->empty_cart();

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

			// This will throw exception if not valid.
			//          $this->validate_amount_limits( $order );

			//          $order->update_meta_data( '_stripe_source_id', $response->id );
			//          $order->save();

			//          WC_Stripe_Logger::log( 'Info: Redirecting to Alipay...' );
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			$statuses = apply_filters(
				'wc_stripe_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ]
			);

			if ( $order->has_status( $statuses ) ) {
				$this->send_failed_order_email( $order_id );
			}

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}
}
