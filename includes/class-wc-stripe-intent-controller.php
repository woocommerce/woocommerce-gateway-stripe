<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Intent_Controller class.
 *
 * Handles in-checkout AJAX calls, related to Payment Intents.
 */
class WC_Stripe_Intent_Controller {
	/**
	 * Holds an instance of the gateway class.
	 *
	 * @since 4.2.0
	 * @var WC_Gateway_Stripe
	 */
	protected $gateway;

	/**
	 * Class constructor, adds the necessary hooks.
	 *
	 * @since 4.2.0
	 */
	public function __construct() {
		add_action( 'wc_ajax_wc_stripe_verify_intent', [ $this, 'verify_intent' ] );
		add_action( 'wc_ajax_wc_stripe_create_setup_intent', [ $this, 'create_setup_intent' ] );

		add_action( 'wc_ajax_wc_stripe_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );

		add_action( 'wc_ajax_wc_stripe_save_upe_appearance', [ $this, 'save_upe_appearance_ajax' ] );
		add_action( 'wc_ajax_nopriv_wc_stripe_save_upe_appearance', [ $this, 'save_upe_appearance_ajax' ] );
		add_action( 'switch_theme', [ $this, 'clear_upe_appearance_transient' ] );
	}

	/**
	 * Returns an instantiated gateway.
	 *
	 * @since 4.2.0
	 * @return WC_Gateway_Stripe
	 */
	protected function get_gateway() {
		if ( ! isset( $this->gateway ) ) {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$class_name = 'WC_Stripe_Subs_Compat';
			} else {
				$class_name = 'WC_Gateway_Stripe';
			}

			$this->gateway = new $class_name();
		}

		return $this->gateway;
	}

	/**
	 * Loads the order from the current request.
	 *
	 * @since 4.2.0
	 * @throws WC_Stripe_Exception An exception if there is no order ID or the order does not exist.
	 * @return WC_Order
	 */
	protected function get_order_from_request() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'wc_stripe_confirm_pi' ) ) {
			throw new WC_Stripe_Exception( 'missing-nonce', __( 'CSRF verification failed.', 'woocommerce-gateway-stripe' ) );
		}

		// Load the order ID.
		$order_id = null;
		if ( isset( $_GET['order'] ) && absint( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		}

		// Retrieve the order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new WC_Stripe_Exception( 'missing-order', __( 'Missing order ID for payment confirmation', 'woocommerce-gateway-stripe' ) );
		}

		return $order;
	}

	/**
	 * Handles successful PaymentIntent authentications.
	 *
	 * @since 4.2.0
	 */
	public function verify_intent() {
		global $woocommerce;

		$gateway = $this->get_gateway();

		try {
			$order = $this->get_order_from_request();
		} catch ( WC_Stripe_Exception $e ) {
			/* translators: Error message text */
			$message = sprintf( __( 'Payment verification error: %s', 'woocommerce-gateway-stripe' ), $e->getLocalizedMessage() );
			wc_add_notice( esc_html( $message ), 'error' );

			$redirect_url = $woocommerce->cart->is_empty()
				? get_permalink( wc_get_page_id( 'shop' ) )
				: wc_get_checkout_url();

			$this->handle_error( $e, $redirect_url );
		}

		try {
			$gateway->verify_intent_after_checkout( $order );

			if ( isset( $_GET['save_payment_method'] ) && ! empty( $_GET['save_payment_method'] ) ) {
				$intent = $gateway->get_intent_from_order( $order );
				if ( isset( $intent->last_payment_error ) ) {
					// Currently, Stripe saves the payment method even if the authentication fails for 3DS cards.
					// Although, the card is not stored in DB we need to remove the source from the customer on Stripe
					// in order to keep the sources in sync with the data in DB.
					$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );
					$customer->delete_source( $intent->last_payment_error->source->id );
				} else {
					$metadata = $intent->metadata;
					if ( isset( $metadata->save_payment_method ) && 'true' === $metadata->save_payment_method ) {
						$source_object = WC_Stripe_API::retrieve( 'sources/' . $intent->source );
						$gateway->save_payment_method( $source_object );
					}
				}
			}

			if ( ! isset( $_GET['is_ajax'] ) ) {
				$redirect_url = isset( $_GET['redirect_to'] ) // wpcs: csrf ok.
					? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) // wpcs: csrf ok.
					: $gateway->get_return_url( $order );

				wp_safe_redirect( $redirect_url );
			}

			exit;
		} catch ( WC_Stripe_Exception $e ) {
			$this->handle_error( $e, $gateway->get_return_url( $order ) );
		}
	}

	/**
	 * Handles exceptions during intent verification.
	 *
	 * @since 4.2.0
	 * @param WC_Stripe_Exception $e           The exception that was thrown.
	 * @param string              $redirect_url An URL to use if a redirect is needed.
	 */
	protected function handle_error( $e, $redirect_url ) {
		// Log the exception before redirecting.
		$message = sprintf( 'PaymentIntent verification exception: %s', $e->getLocalizedMessage() );
		WC_Stripe_Logger::log( $message );

		// `is_ajax` is only used for PI error reporting, a response is not expected.
		if ( isset( $_GET['is_ajax'] ) ) {
			exit;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Creates a Setup Intent through AJAX while adding cards.
	 */
	public function create_setup_intent() {
		if (
			! is_user_logged_in()
			|| ! isset( $_POST['stripe_source_id'] )
			|| ! isset( $_POST['nonce'] )
		) {
			return;
		}

		try {
			$source_id = wc_clean( wp_unslash( $_POST['stripe_source_id'] ) );

			// 1. Verify.
			if (
				! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wc_stripe_create_si' )
				|| ! preg_match( '/^src_.*$/', $source_id )
			) {
				throw new Exception( __( 'Unable to verify your request. Please reload the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			// 2. Load the customer ID (and create a customer eventually).
			$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );

			// 3. Attach the source to the customer (Setup Intents require that).
			$source_object = $customer->attach_source( $source_id );

			if ( ! empty( $source_object->error ) ) {
				throw new Exception( $source_object->error->message );
			}
			if ( is_wp_error( $source_object ) ) {
				throw new Exception( $source_object->get_error_message() );
			}

			// SEPA Direct Debit payments do not require any customer action after the source has been created.
			// Once the customer has provided their IBAN details and accepted the mandate, no further action is needed and the resulting source is directly chargeable.
			if ( 'sepa_debit' === $source_object->type ) {
				$response = [
					'status' => 'success',
				];
				echo wp_json_encode( $response );
				return;
			}

			// 4. Generate the setup intent
			$setup_intent = WC_Stripe_API::request(
				[
					'customer'       => $customer->get_id(),
					'confirm'        => 'true',
					'payment_method' => $source_id,
				],
				'setup_intents'
			);

			if ( ! empty( $setup_intent->error ) ) {
				$error_response_message = print_r( $setup_intent, true );
				WC_Stripe_Logger::log( 'Failed create Setup Intent while saving a card.' );
				WC_Stripe_Logger::log( "Response: $error_response_message" );
				throw new Exception( __( 'Your card could not be set up for future usage.', 'woocommerce-gateway-stripe' ) );
			}

			// 5. Respond.
			if ( 'requires_action' === $setup_intent->status ) {
				$response = [
					'status'        => 'requires_action',
					'client_secret' => $setup_intent->client_secret,
				];
			} elseif ( 'requires_payment_method' === $setup_intent->status
				|| 'requires_confirmation' === $setup_intent->status
				|| 'canceled' === $setup_intent->status ) {
				// These statuses should not be possible, as such we return an error.
				$response = [
					'status' => 'error',
					'error'  => [
						'type'    => 'setup_intent_error',
						'message' => __( 'Failed to save payment method.', 'woocommerce-gateway-stripe' ),
					],
				];
			} else {
				// This should only be reached when status is `processing` or `succeeded`, which are
				// the only statuses that we haven't explicitly handled.
				$response = [
					'status' => 'success',
				];
			}
		} catch ( Exception $e ) {
			$response = [
				'status' => 'error',
				'error'  => [
					'type'    => 'setup_intent_error',
					'message' => $e->getMessage(),
				],
			];
		}

		echo wp_json_encode( $response );
		exit;
	}

	/**
	 * Handle AJAX request for creating a payment intent for Stripe UPE.
	 *
	 * @throws Process_Payment_Exception - If nonce or setup intent is invalid.
	 */
	public function create_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( '_wc_stripe_nonce', false, false );
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
		$amount = WC()->cart->get_total( false );
		$order  = wc_get_order( $order_id );
		if ( is_a( $order, 'WC_Order' ) ) {
			$amount = $order->get_total();
		}

		$gateway = new WC_Stripe_UPE_Payment_Gateway();

		$currency       = get_woocommerce_currency();
		$payment_intent = WC_Stripe_API::request(
			[
				'amount'               => WC_Stripe_Helper::get_stripe_amount( $amount, strtolower( $currency ) ),
				'currency'             => strtolower( $currency ),
				'payment_method_types' => $gateway->get_upe_enabled_payment_method_ids(),
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
	 * Handle AJAX request for saving UPE appearance value to transient.
	 *
	 * @throws Exception - If nonce or setup intent is invalid.
	 */
	public function save_upe_appearance_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( '_wc_stripe_save_upe_appearance_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception(
					__( 'Unable to update UPE appearance values at this time.', 'woocommerce-gateway-stripe' )
				);
			}

			$appearance = isset( $_POST['appearance'] ) ? wc_clean( wp_unslash( $_POST['appearance'] ) ) : null;
			if ( null !== $appearance ) {
				set_transient( WC_Stripe_UPE_Payment_Gateway::UPE_APPEARANCE_TRANSIENT, $appearance, DAY_IN_SECONDS );
			}
			wp_send_json_success( $appearance, 200 );
		} catch ( Exception $e ) {
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
	 * Clear the saved UPE appearance transient value.
	 */
	public function clear_upe_appearance_transient() {
		delete_transient( WC_Stripe_UPE_Payment_Gateway::UPE_APPEARANCE_TRANSIENT );
	}

}

new WC_Stripe_Intent_Controller();
