<?php
/**
 * Class WC_REST_Stripe_Orders_Controller
 */

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
		$disallowed_order_statuses = apply_filters( 'wc_stripe_create_customer_disallowed_order_statuses', [ 'completed', 'cancelled', 'refunded', 'failed' ] );
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
}
