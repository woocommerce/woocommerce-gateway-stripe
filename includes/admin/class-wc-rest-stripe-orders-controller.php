<?php
/**
 * Class WC_REST_Stripe_Orders_Controller
 */

use Automattic\WooCommerce\Enums\OrderStatus;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for orders.
 */
class WC_REST_Stripe_Orders_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/orders';

	/**
	 * Minimum charge amounts by currency.
	 * https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts
	 *
	 * @var array
	 */
	protected static $minimum_amounts = [
		'USD' => 50,    // $0.50
		'AED' => 200,   // 2.00 د.إ
		'AUD' => 50,    // $0.50
		'BGN' => 100,   // лв1.00
		'BRL' => 50,    // R$0.50
		'CAD' => 50,    // $0.50
		'CHF' => 50,    // 0.50 Fr
		'CZK' => 1500,  // 15.00Kč
		'DKK' => 250,   // 2.50-kr
		'EUR' => 50,    // €0.50
		'GBP' => 30,    // £0.30
		'HKD' => 400,   // $4.00
		'HUF' => 17500, // 175.00 Ft
		'INR' => 50,    // ₹0.50
		'JPY' => 50,    // ¥50
		'MXN' => 1000,  // $10
		'MYR' => 200,   // RM 2
		'NOK' => 300,   // 3.00-kr
		'NZD' => 50,    // $0.50
		'PLN' => 200,   // 2.00 zł
		'RON' => 200,   // lei2.00
		'SEK' => 300,   // 3.00-kr
		'SGD' => 50,    // $0.50
		'THB' => 1000,  // ฿10
	];

	/**
	 * Stripe payment gateway.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_Stripe $gateway Stripe payment gateway.
	 */
	public function __construct( WC_Gateway_Stripe $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>\d+)/create_customer',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_customer' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>\w+)/capture_terminal_payment',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'capture_terminal_payment' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'payment_intent_id' => [
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Create a Stripe customer for an order if needed, or return existing customer.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function create_customer( $request ) {
		$order_id = $request['order_id'];

		// Ensure order exists.
		$order = wc_get_order( $order_id );
		if ( false === $order || ! ( $order instanceof WC_Order ) ) {
			return new WP_Error( 'wc_stripe', __( 'Order not found', 'woocommerce-gateway-stripe' ), [ 'status' => 404 ] );
		}

		// Validate order status before creating customer.
		$disallowed_order_statuses = apply_filters( 'wc_stripe_create_customer_disallowed_order_statuses', [ OrderStatus::COMPLETED, OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::FAILED ] );
		if ( $order->has_status( $disallowed_order_statuses ) ) {
			return new WP_Error( 'wc_stripe_invalid_order_status', __( 'Invalid order status', 'woocommerce-gateway-stripe' ), [ 'status' => 400 ] );
		}

		// Get a customer object with the order's user, if available.
		$order_user = $order->get_user();
		if ( false === $order_user ) {
			$order_user = new WP_User();
		}
		$customer = new WC_Stripe_Customer( $order_user->ID );

		// Set the customer ID if known but not already set.
		$customer_id = $order->get_meta( '_stripe_customer_id', true );
		if ( ! $customer->get_id() && $customer_id ) {
			$customer->set_id( $customer_id );
		}

		try {
			// Update or create Stripe customer.
			$customer_data = WC_Stripe_Customer::map_customer_data( $order );
			if ( $customer->get_id() ) {
				$customer_id = $customer->update_customer( $customer_data );
			} else {
				$customer_id = $customer->create_customer( $customer_data );
			}
		} catch ( WC_Stripe_Exception $e ) {
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}

		$order->update_meta_data( '_stripe_customer_id', $customer_id );
		$order->save();

		return rest_ensure_response( [ 'id' => $customer_id ] );
	}

	public function capture_terminal_payment( $request ) {
		try {
			$intent_id = $request['payment_intent_id'];
			$order_id  = $request['order_id'];
			$order     = wc_get_order( $order_id );

			// Check that order exists before capturing payment.
			if ( ! $order ) {
				return new WP_Error( 'wc_stripe_missing_order', __( 'Order not found', 'woocommerce-gateway-stripe' ), [ 'status' => 404 ] );
			}

			// Do not process refunded orders.
			if ( 0 < $order->get_total_refunded() ) {
				return new WP_Error( 'wc_stripe_refunded_order_uncapturable', __( 'Payment cannot be captured for partially or fully refunded orders.', 'woocommerce-gateway-stripe' ), [ 'status' => 400 ] );
			}

			// Retrieve intent from Stripe.
			$intent = WC_Stripe_API::retrieve( "payment_intents/$intent_id" );

			// Check that intent exists.
			if ( ! empty( $intent->error ) ) {
				return new WP_Error( 'stripe_error', $intent->error->message );
			}

			// Ensure that intent can be captured.
			if ( ! in_array( $intent->status, [ WC_Stripe_Intent_Status::PROCESSING, WC_Stripe_Intent_Status::REQUIRES_CAPTURE ], true ) ) {
				return new WP_Error( 'wc_stripe_payment_uncapturable', __( 'The payment cannot be captured', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
			}

			// Update order with payment method and intent details.
			$order->set_payment_method( WC_Gateway_Stripe::ID );
			$order->set_payment_method_title( __( 'WooCommerce Stripe In-Person Payments', 'woocommerce-gateway-stripe' ) );
			$this->gateway->save_intent_to_order( $order, $intent );

			// Capture payment intent.
			$charge = $this->gateway->get_latest_charge_from_intent( $intent );
			$this->gateway->process_response( $charge, $order );
			$result = WC_Stripe_Order_Handler::get_instance()->capture_payment( $order );

			// Check for amount_too_small error
			if ( ! empty( $result->error ) && 'amount_too_small' === $result->error->code ) {
				$currency       = strtoupper( $order->get_currency() );
				$minimum_amount = isset( self::$minimum_amounts[ $currency ] ) ? self::$minimum_amounts[ $currency ] : null;

				$message = wp_json_encode(
					[
						'minimum_amount'          => $minimum_amount,
						'minimum_amount_currency' => $currency,
					]
				);

				return new WP_Error(
					'wc_stripe_capture_error_amount_too_small',
					$message,
					[ 'status' => 400 ]
				);
			}

			// Check for failure to capture payment.
			if ( empty( $result ) || empty( $result->status ) || WC_Stripe_Intent_Status::SUCCEEDED !== $result->status ) {
				return new WP_Error(
					'wc_stripe_capture_error',
					sprintf(
						// translators: %s: the error message.
						__( 'Payment capture failed to complete with the following message: %s', 'woocommerce-gateway-stripe' ),
						$result->error->message ?? __( 'Unknown error', 'woocommerce-gateway-stripe' )
					),
					[ 'status' => 502 ]
				);
			}

			// Successfully captured.
			$order->update_status( OrderStatus::COMPLETED );
			return rest_ensure_response(
				[
					'status' => $result->status,
					'id'     => $result->id,
				]
			);
		} catch ( WC_Stripe_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'stripe_error', $e->getMessage() ) );
		}
	}
}
