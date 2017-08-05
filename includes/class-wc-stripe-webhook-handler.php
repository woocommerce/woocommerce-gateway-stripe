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
	 * @param object $notification
	 * @param bool $retry
	 */
	public function process_webhook_payment( $notification, $retry = true ) {
		$order = WC_Stripe_Helper::get_order_by_source_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via source ID: ' . $notification->data->object->id );
			return;
		}

		$order_id  = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		$source_id = $notification->data->object->id;

		try {
			if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ) {
				return;
			}

			// Result from Stripe API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				// This will throw exception if not valid.
				$this->validate_minimum_order_amount( $order );

				WC_Stripe_Logger::log( "Info: (Webhook) Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Prep source object.
				$source_object           = new stdClass();
				$source_object->token_id = '';
				$source_object->customer = $this->get_stripe_customer_id( $order );
				$source_object->source   = $source_id;

				// Make the request.
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source_object ) );

				if ( ! empty( $response->error ) ) {
					// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
					if ( 'customer' === $response->error->type && $retry ) {
						delete_user_meta( get_current_user_id(), '_stripe_customer_id' );
						return $this->process_payment( $order_id, false, $force_save_source );
					} elseif ( preg_match( '/No such customer/i', $response->error->message ) && $retry ) {
						delete_user_meta( WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id(), '_stripe_customer_id' );

						return $this->process_payment( $order_id, false, $force_save_source );
						// Source param wrong? The CARD may have been deleted on stripe's end. Remove token and show message.
					} elseif ( 'source' === $response->error->type && $prepared_source->token_id ) {
						$wc_token = WC_Payment_Tokens::get( $prepared_source->token_id );
						$wc_token->delete();
						$message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-stripe' );
						$order->add_order_note( $message );
						throw new Exception( $message );
					}

					$localized_messages = WC_Stripe_Helper::get_localized_messages();

					$message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;

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

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $e, $order );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}			
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
	 * Process webhook capture. This is used for an authorized only
	 * transaction that is later captured via Stripe not WC.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_capture( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		if ( 'stripe' === ( WC_Stripe_Helper::is_pre_30() ? $order->payment_method : $order->get_payment_method() ) ) {
			$charge   =  WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
			$captured = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_charge_captured', true ) : $order->get_meta( '_stripe_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_stripe_charge_captured', 'yes' ) : $order->update_meta_data( '_stripe_charge_captured', 'yes' );

				// Store other data such as fees
				WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_transaction_id', $notification->data->object->id ) : $order->set_transaction_id( $notification->data->object->id );

				$balance_transaction = WC_Stripe_API::retrieve( 'balance/history/' . $notification->data->object->balance_transaction );

				if ( empty( $balance_transaction->error ) ) {
					if ( isset( $balance_transaction ) && isset( $balance_transaction->fee ) ) {
						// Fees and Net needs to both come from Stripe to be accurate as the returned
						// values are in the local currency of the Stripe account, not from WC.
						$fee = ! empty( $balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'fee' ) : 0;
						$net = ! empty( $balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'net' ) : 0;

						WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, 'Stripe Fee', $fee ) : $order->update_meta_data( 'Stripe Fee', $fee );
						WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, 'Net Revenue From Stripe', $net ) : $order->update_meta_data( 'Net Revenue From Stripe', $net );
					}
				}

				if ( is_callable( array( $order, 'save' ) ) ) {
					$order->save();
				}

				$order->update_status( $order->needs_processing() ? 'processing' : 'completed', sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );

				// Check and see if capture is partial.
				if ( $this->is_partial_capture( $notification ) ) {
					$order->set_total( $this->get_partial_amount_to_charge( $notification ) );
					$order->add_note( __( 'This charge was partially captured via Stripe Dashboard', 'woocommerce-gateway-stripe' ) );
					$order->save();			
				}
			}
		}
	}

	/**
	 * Process webhook refund.
	 * Note currently only support 1 time refund.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_refund( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		if ( 'stripe' === ( WC_Stripe_Helper::is_pre_30() ? $order->payment_method : $order->get_payment_method() ) ) {
			$charge   =  WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
			$captured = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_charge_captured', true ) : $order->get_meta( '_stripe_charge_captured', true );

			// Only refund captured charge.
			if ( $charge && 'yes' === $captured ) {
				// Create the refund.
				$refund = wc_create_refund( array(
					'order_id'       => $order_id,
					'amount'         => $this->get_refund_amount( $notification ),
					'reason'         => __( 'Refunded via Stripe Dashboard', 'woocommerce-gateway-stripe' ),
				) );

				if ( is_wp_error( $refund ) ) {
					WC_Stripe_Logger::log( $refund->get_error_message() );
				}
			}
		}
	}

	/**
	 * Checks if capture is partial.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function is_partial_capture( $notification ) {
		return 0 < $notification->data->object->amount_refunded;
	}

	/**
	 * Gets the amount refunded.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function get_refund_amount( $notification ) {
		if ( $this->is_partial_capture( $notification ) ) {
			$amount = $notification->data->object->amount_refunded / 100;

			if ( in_array( strtolower( $notification->data->object->currency ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
				$amount = $notification->data->object->amount_refunded;
			}

			return $amount;
		}

		return false;
	}

	/**
	 * Gets the amount we actually charge.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function get_partial_amount_to_charge( $notification ) {
		if ( $this->is_partial_capture( $notification ) ) {
			$amount = ( $notification->data->object->amount - $notification->data->object->amount_refunded ) / 100;

			if ( in_array( strtolower( $notification->data->object->currency ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
				$amount = ( $notification->data->object->amount - $notification->data->object->amount_refunded );
			}

			return $amount;
		}

		return false;
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
				$this->process_webhook_capture( $notification );
				break;

			case 'charge.dispute.created':
				$this->process_webhook_dispute( $notification );
				break;

			case 'charge.refunded':
				$this->process_webhook_refund( $notification );
				break;

		}
	}
}

new WC_Stripe_Webhook_Handler();
