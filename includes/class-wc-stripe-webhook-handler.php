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
		// The following 2 payment methods are synchronous so does not need to be handle via webhook.
		if ( 'card' === $notification->data->object->type || 'sepa_debit' === $notification->data->object->type ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_source_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via source ID: ' . $notification->data->object->id );
			return;
		}

		$order_id  = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		$source_id = $notification->data->object->id;

		$is_pending_receiver = ( 'receiver' === $notification->data->object->flow );

		try {
			if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ) {
				return;
			}

			if ( 'on-hold' === $order->get_status() && ! $is_pending_receiver ) {
				return;
			}

			// Result from Stripe API request.
			$response = null;

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
				// If it is an API error such connection or server, let's retry.
				if ( 'api_connection_error' === $response->error->type || 'api_error' === $response->error->type ) {
					if ( $retry ) {
						sleep( 5 );
						return $this->process_payment( $order_id, false );
					} else {
						$localized_message = 'API connection error and retries exhausted.';
						$order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}
				}

				// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
				if ( preg_match( '/No such customer/i', $response->error->message ) && $retry ) {
					delete_user_meta( WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id(), '_stripe_customer_id' );

					return $this->process_payment( $order_id, false );

				} elseif ( preg_match( '/No such token/i', $response->error->message ) && $source_object->token_id ) {
					// Source param wrong? The CARD may have been deleted on stripe's end. Remove token and show message.
					$wc_token = WC_Payment_Tokens::get( $source_object->token_id );
					$wc_token->delete();
					$message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-stripe' );
					$order->add_order_note( $message );
					throw new WC_Stripe_Exception( print_r( $response, true ), $message );
				}

				$localized_messages = WC_Stripe_Helper::get_localized_messages();

				if ( 'card_error' === $response->error->type ) {
					$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
				} else {
					$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
				}

				$order->add_order_note( $localized_message );

				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}

			do_action( 'wc_gateway_stripe_process_webhook_payment', $response, $order );

			$this->process_response( $response, $order );

		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $e, $order );

			$statuses = array( 'pending', 'failed' );

			if ( $order->has_status( $statuses ) ) {
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

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );
		$this->send_failed_order_email( $order_id );
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
			$charge   = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
			$captured = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_charge_captured', true ) : $order->get_meta( '_stripe_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_stripe_charge_captured', 'yes' ) : $order->update_meta_data( '_stripe_charge_captured', 'yes' );

				// Store other data such as fees
				WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_transaction_id', $notification->data->object->id ) : $order->set_transaction_id( $notification->data->object->id );

				if ( isset( $notification->data->object->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->balance_transaction );
				}

				if ( is_callable( array( $order, 'save' ) ) ) {
					$order->save();
				}

				/* translators: transaction id */
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
	 * Process webhook charge succeeded. This is used for payment methods
	 * that takes time to clear which is asynchronous. e.g. SEPA, SOFORT.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_charge_succeeded( $notification ) {
		// The following payment methods are synchronous so does not need to be handle via webhook.
		if ( ( isset( $notification->data->object->source->type ) && 'card' === $notification->data->object->source->type ) || ( isset( $notification->data->object->source->type ) && 'three_d_secure' === $notification->data->object->source->type ) ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		if ( 'on-hold' !== $order->get_status() ) {
			return;
		}

		// Store other data such as fees
		WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_transaction_id', $notification->data->object->id ) : $order->set_transaction_id( $notification->data->object->id );

		if ( isset( $notification->data->object->balance_transaction ) ) {
			$this->update_fees( $order, $notification->data->object->balance_transaction );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		/* translators: transaction id */
		$order->update_status( $order->needs_processing() ? 'processing' : 'completed', sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );
	}

	/**
	 * Process webhook charge failed. This is used for payment methods
	 * that takes time to clear which is asynchronous. e.g. SEPA, SOFORT.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_charge_failed( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		if ( 'on-hold' !== $order->get_status() ) {
			return;
		}

		$order->update_status( 'failed', __( 'This payment failed to clear.', 'woocommerce-gateway-stripe' ) );

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );
	}

	/**
	 * Process webhook source canceled. This is used for payment methods
	 * that redirects and awaits payments from customer.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_source_canceled( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		if ( 'on-hold' !== $order->get_status() || 'cancelled' !== $order->get_status() ) {
			return;
		}

		$order->update_status( 'cancelled', __( 'This payment has cancelled.', 'woocommerce-gateway-stripe' ) );

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );
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
			$charge    = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
			$captured  = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_charge_captured', true ) : $order->get_meta( '_stripe_charge_captured', true );
			$refund_id = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_refund_id', true ) : $order->get_meta( '_stripe_refund_id', true );

			// If the refund ID matches, don't continue to prevent double refunding.
			if ( $notification->data->object->refunds->data[0]->id === $refund_id ) {
				return;
			}

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

			case 'source.canceled':
				$this->process_webhook_source_canceled( $notification );
				break;

			case 'charge.succeeded':
				$this->process_webhook_charge_succeeded( $notification );
				break;

			case 'charge.failed':
				$this->process_webhook_charge_failed( $notification );
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
