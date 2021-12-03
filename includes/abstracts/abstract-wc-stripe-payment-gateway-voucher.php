<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName
/**
 * Abstract class that will be inherited by voucher payment methods.
 * Used by Boleto and OXXO
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 5.8.0
 */
abstract class WC_Stripe_Payment_Gateway_Voucher extends WC_Stripe_Payment_Gateway {

	/**
	 * ID used by UPE
	 *
	 * @var string
	 */
	const ID = '';

	/**
	 * ID used by WooCommerce to identify the payment method
	 * Override this when extending the class
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * ID used by stripe
	 * Change this when extending this class
	 */
	protected $stripe_id = '';

	/**
	 * List of accepted currencies
	 * Change this when extending this class
	 *
	 * @var array
	 */
	protected $supported_currencies = [];

	/**
	 * List of accepted countries
	 * Change this when extending this class
	 */
	protected $supported_countries = [];

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
	 * Gateway has additional fields during checkout
	 *
	 * @var bool
	 */
	public $has_fields = true;

	/**
	 * Constructor
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		$this->method_description = sprintf(
		/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			__( 'All other general Stripe settings can be adjusted %1$shere%2$s ', 'woocommerce-gateway-stripe' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) ) . '">',
			'</a>'
		);
		$this->supports = [
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
		add_action(
			'wc_ajax_wc_stripe_' . $this->stripe_id . '_update_payment_intent',
			[
				$this,
				'update_payment_intent_ajax',
			]
		);
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @return bool
	 * @return bool
	 * @since 5.8.0
	 */
	public function is_available() {
		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Hides refund through stripe when payment method does not allow refund
	 *
	 * @param WC_Order $order
	 *
	 * @return array|bool
	 */
	public function can_refund_order( $order ) {
		return false;
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @return array
	 * @return array
	 * @since 5.8.0
	 */
	public function get_supported_currency() {
		return apply_filters(
			'wc_stripe_' . $this->stripe_id . '_supported_currencies',
			$this->supported_currencies
		);
	}

	/**
	 * Get_icon function.
	 *
	 * @return string
	 * @since 5.8.0
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= isset( $icons[ $this->stripe_id ] ) ? $icons[ $this->stripe_id ] : '';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Payment_scripts function.
	 *
	 * @since 5.8.0
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		parent::payment_scripts();
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 *
	 * @since 5.8.0
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-' . $this->stripe_id . '-settings.php';
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force payment source to be saved.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @since 5.8.0
	 * @since 5.8.0
	 */
	public function process_payment( $order_id, $retry = true, $force_save_save = false ) {
		try {
			$order = wc_get_order( $order_id );

			if ( ! in_array( $order->get_billing_country(), $this->supported_countries ) ) {
				throw new \Exception( __( 'This payment method is not available in the selected country', 'woocommerce-gateway-stripe' ) );
			}

			$intent = $this->create_or_update_payment_intent( $order );

			$order->update_meta_data( '_stripe_upe_payment_type', $this->stripe_id );
			$order->update_status( 'pending', __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
			$order->save();

			WC_Stripe_Helper::add_payment_intent_to_order( $intent->id, $order );

			return [
				'result'               => 'success',
				'redirect'             => $this->get_return_url( $order ),
				'intent_id'            => $intent->id,
				'client_secret'        => $intent->client_secret,
				'order_id'             => $order_id,
				'confirm_payment_data' => $this->get_confirm_payment_data( $order ),
			];
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			$statuses = apply_filters(
				'wc_stripe_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ],
				$order
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

	/**
	 * Creates payment intent using order and store details.
	 * If the order already has a Payment Intent it gets updated
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return object
	 * @throws Exception - If the create intent call returns with an error.
	 * @since 5.8.0
	 */
	public function create_or_update_payment_intent( $order ) {
		$amount   = $order->get_total();
		$currency = $order->get_currency();

		$this->validate_amount_limits( $amount );

		$intent = $this->get_intent_from_order( $order );

		$intent_to_be_updated = '';

		if ( $intent ) {
			$intent_to_be_updated = '/' . $intent->id;
		}

		$payment_intent = WC_Stripe_API::request(
			[
				'amount'               => WC_Stripe_Helper::get_stripe_amount( $amount, strtolower( $currency ) ),
				'currency'             => strtolower( $currency ),
				'payment_method_types' => [ $this->stripe_id ],
				'description'          => __( 'stripe - Order', 'woocommerce-gateway-stripe' ) . ' ' . $order->get_id(),
			],
			'payment_intents' . $intent_to_be_updated
		);

		if ( ! empty( $payment_intent->error ) ) {
			throw new Exception( $payment_intent->error->message );
		}

		return $payment_intent;
	}

	/**
	 * Validates the minimum and maximum amount.
	 * Override this method when extending the class
	 *
	 * @param $amount
	 *
	 * @throws WC_Stripe_Exception when amount is out of range
	 * @since 5.8.0
	 */
	abstract protected function validate_amount_limits( $amount );

	/**
	 * Updates the payment intent when trying to pay again via Pay Order Page
	 */
	public function update_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_update_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : null;

			if ( ! $order_id ) {
				throw new \Exception( __( 'Order Id not found, send an order id', 'woocommerce-gateway-stripe' ) );
			}

			$order = wc_get_order( $order_id );
			$order->set_payment_method( $this );
			$intent = $this->create_or_update_payment_intent( $order );

			$order->update_status( 'pending', __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
			$order->update_meta_data( '_stripe_upe_payment_type', $this->stripe_id );
			$order->save();

			wp_send_json(
				[
					'redirect'             => $this->get_return_url( $order ),
					'intent_id'            => $intent->id,
					'client_secret'        => $intent->client_secret,
					'order_id'             => $order_id,
					'result'               => 'success',
					'confirm_payment_data' => $this->get_confirm_payment_data( $order ),
				]
			);
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json(
				[
					'result'   => 'fail',
					'messages' => __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ),
				]
			);
		}
	}

	/**
	 * Gather the data necessary to confirm the payment via javascript
	 * Override this when extending the class
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function get_confirm_payment_data( $order ) {
		return [];
	}
}
