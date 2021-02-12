<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Session class.
 *
 * Represents a Stripe Session.
 */
class WC_Stripe_Session {

	/**
	 * Stripe session ID
	 * @var string
	 */
	private $id = '';

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Get the Stripe session ID.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set the Stripe session ID.
	 * @param string $id Stripe session ID.
	 */
	public function set_id( $id ) {
		$this->id = wc_clean( $id );
	}

	/**
	 * Generates the session request, used for both creating and updating sessions.
	 *
	 * @param  array $args Additional arguments (optional).
	 * @return array
	 */
	protected function generate_session_request( $args = array() ) {
		$cart_contents = WC()->cart->get_cart();

		$items = [];
		foreach( $cart_contents as $item ) {
			$items[] = [
				'quantity'   => $item['quantity'] ?? 0,
				'price_data' => [
					'currency'     => 'eur',
					'product_data' => [
						'name' => $item['data']->get_title() ?? '',
					],
					'unit_amount'  => floatval( $item['data']->get_price() ) * 100 ?? 0,
				],
			];
		}

		$defaults = [
			'payment_method_types' => [ 'card', 'ideal' ],
			'line_items' => $items,
			'mode' => 'payment',
			'success_url' => '',
			'cancel_url' => wc_get_checkout_url()
		];

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Create a session via API.
	 * @param $args
	 * @return WP_Error|string Session ID
	 * @throws WC_Stripe_Exception
	 */
	public function create_session( $args ) {
		$args     = $this->generate_session_request( $args );
		$response = WC_Stripe_API::request( apply_filters( 'wc_stripe_create_session_args', $args ), 'checkout/sessions' );

		if ( ! empty( $response->error ) ) {
			throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
		}

		$this->set_id( $response->id );

		do_action( 'woocommerce_stripe_create_session', $args, $response );

		return $response;
	}

	/**
	 * Retrieves a Stripe Session object via API.
	 * @param $args
	 * @throws WC_Stripe_Exception
	 */
	public static function retrieve_session( $session_id ) {
		$response = WC_Stripe_API::retrieve( "sessions/{$session_id}" );

		if ( ! empty( $response->error ) ) {
			throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
		}

		return $response;
	}

	/**
	 * Updates the Stripe session through the API.
	 *
	 * @return string Session ID
	 *
	 * @throws WC_Stripe_Exception
	 */
	public function update_customer( $args = array() ) {
		if ( empty( $this->get_id() ) ) {
			throw new WC_Stripe_Exception( 'id_required_to_update_session', __( 'Attempting to update a Stripe session without a session ID.', 'woocommerce-gateway-stripe' ) );
		}

		$args     = $this->generate_session_request( $args );
		$args     = apply_filters( 'wc_stripe_update_session_args', $args );
		$response = WC_Stripe_API::request( $args, 'sessions/' . $this->get_id() );

		if ( ! empty( $response->error ) ) {
			if ( $this->is_no_such_session_error( $response->error ) ) {
				// This can happen when switching the main Stripe account or importing users from another site.
				// If not already retrying, recreate the customer and then try updating it again.
				$this->recreate_session();
				return $this->update_session( $args ); // TODO retry limit
			}

			throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
		}

		do_action( 'woocommerce_stripe_update_session', $args, $response );

		return $this->get_id();
	}
}
