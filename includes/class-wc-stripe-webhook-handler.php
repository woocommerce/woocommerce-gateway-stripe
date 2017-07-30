<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Webhook_Handler.
 * 
 * Handles webhooks from Stripe on sources that are not immediately chargeable.
 * @since 4.0.0
 */
class WC_Stripe_Webhook_Handler extends WC_Stripe_Payment_Gateway {
	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		add_action( 'woocommerce_api_wc_stripe', array( $this, 'check_for_webhook' ) );
	}

	/**
	 * Check incoming requests for Stripe Webhook data and process them.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function check_for_webhook() {
		if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			|| ! isset( $_GET['wc-api'] )
			|| ( 'wc_stripe' !== $_GET['wc-api'] )
		) {
			return;
		}

		$request_body    = file_get_contents( 'php://input' );
		$request_headers = array_change_key_case( $this->get_request_headers(), CASE_UPPER );

		// Validate it to make sure it is legit.
		if ( $this->is_valid_request( $request_headers, $request_body ) ) {
			$this->process_webhook( $request_body );
			status_header( 200 );
			exit;
		} else {
			WC_Stripe_Logger::log( 'Incoming webhook failed validation: ' . print_r( $request_body, true ) );
			status_header( 400 );
			exit;
		}
	}

	/**
	 * Verify the incoming webhook notification to make sure it is legit.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @todo Implement proper webhook signature validation. Ref https://stripe.com/docs/webhooks#signatures
	 * @param string $request_headers The request headers from Stripe.
	 * @param string $request_body The request body from Stripe.
	 * @return bool
	 */
	public function is_valid_request( $request_headers = null, $request_body = null ) {
		if ( null === $request_headers || null === $request_body ) {
			return false;
		}

		if ( empty( $request_headers['STRIPE-SIGNATURE'] ) ) {
			return false;
		}

		if ( ! empty( $request_headers['USER-AGENT'] ) && ! preg_match( '/Stripe/', $request_headers['USER-AGENT'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_request_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = [];
			foreach ( $_SERVER as $name => $value ) {
				if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
				}
			}

			return $headers;
		} else {
			return getallheaders();
		}
	}

	/**
	 * Process webhook payments.
	 * This is where we charge the source.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $source
	 * @param bool $retry
	 */
	public function process_webhook_payment( $source, $retry = true ) {
		$order = WC_Stripe_Helper::get_order_by_source_id( $source->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via source ID: ' . $source->data->object->id );
			return;
		}

		$order_id  = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		$source_id = $source->data->object->id;

		try {
			if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ) {
				return;
			}

			// Result from Stripe API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
				}

				WC_Stripe_Logger::log( "Info: (Webhook) Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Prep source object.
				$source_object           = new stdClass();
				$source_object->token_id = '';
				$source_object->customer = $this->get_stripe_customer_id( $order );
				$source_object->source   = $source_id;

				// Make the request.
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source_object ) );

				if ( is_wp_error( $response ) ) {
					// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
					if ( 'customer' === $response->get_error_code() && $retry ) {
						delete_user_meta( $order->get_customer_id(), '_stripe_customer_id' );

						return $this->process_redirect_payment( $order_id, false, $source );
					} elseif ( preg_match( '/No such customer/i', $response->get_error_message() ) && $retry ) {
						delete_user_meta( $order->get_customer_id(), '_stripe_customer_id' );

						return $this->process_redirect_payment( $order_id, false, $source );
					// Source param wrong? The CARD may have been deleted on stripe's end. Remove token and show message.
					} elseif ( 'source' === $response->get_error_code() && $source->token_id ) {
						$token = WC_Payment_Tokens::get( $source->token_id );
						$token->delete();
						$message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-stripe' );
						$order->add_order_note( $message );
					}

					$localized_messages = WC_Stripe_Helper::get_localized_messages();

					$message = isset( $localized_messages[ $response->get_error_code() ] ) ? $localized_messages[ $response->get_error_code() ] : $response->get_error_message();

					$order->add_order_note( $message );

					throw new Exception( $message );
				}

				$this->process_response( $response, $order );
				
			} else {
				$order->payment_complete();
			}

			do_action( 'wc_gateway_stripe_process_webhook_payment', $response, $order );

		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $e, $order );
		}
	}

	/**
	 * Process webhook disputes that is created.
	 * This is trigger when a fraud is detected or customer processes chargeback.
	 * We want to put the order into on-hold and add an order note.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_dispute( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order->update_status( 'on-hold', __( 'A dispute was created for this order. Response is needed. Please go to your Stripe Dashboard to review this dispute.', 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Processes the incoming webhook.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $request_body
	 */
	public function process_webhook( $request_body ) {
		$notification = json_decode( $request_body );

		switch ( $notification->type ) {
			case 'source.chargeable':
				$this->process_webhook_payment( $notification );
				break;

			case 'charge.captured':
				break;

			case 'charge.dispute.created':
				$this->process_webhook_dispute( $notification );
				break;

			case 'charge.refunded':
				break;

		}
	}
}

new WC_Stripe_Webhook_Handler();
