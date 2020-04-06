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
	 * Delay of retries.
	 *
	 * @var int
	 */
	public $retry_interval;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * The secret to use when verifying webhooks.
	 *
	 * @var string
	 */
	protected $secret;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		$this->retry_interval = 2;
		$stripe_settings      = get_option( 'woocommerce_stripe_settings', array() );
		$this->testmode       = ( ! empty( $stripe_settings['testmode'] ) && 'yes' === $stripe_settings['testmode'] ) ? true : false;
		$secret_key           = ( $this->testmode ? 'test_' : '' ) . 'webhook_secret';
		$this->secret         = ! empty( $stripe_settings[ $secret_key ] ) ? $stripe_settings[ $secret_key ] : false;

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

		if ( ! empty( $this->secret ) ) {
			// Check for a valid signature.
			$signature_format = '/^t=(?P<timestamp>\d+)(?P<signatures>(,v\d+=[a-z0-9]+){1,2})$/';
			if ( empty( $request_headers['STRIPE-SIGNATURE'] ) || ! preg_match( $signature_format, $request_headers['STRIPE-SIGNATURE'], $matches ) ) {
				return false;
			}

			// Verify the timestamp.
			$timestamp = intval( $matches['timestamp'] );
			if ( abs( $timestamp - time() ) > 5 * MINUTE_IN_SECONDS ) {
				return;
			}

			// Generate the expected signature.
			$signed_payload     = $timestamp . '.' . $request_body;
			$expected_signature = hash_hmac( 'sha256', $signed_payload, $this->secret );

			// Check if the expected signature is present.
			if ( ! preg_match( '/,v\d+=' . preg_quote( $expected_signature, '/' ) . '/', $matches['signatures'] ) ) {
				return false;
			}
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
			$headers = array();

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
		// The following 3 payment methods are synchronous so does not need to be handle via webhook.
		if ( 'card' === $notification->data->object->type || 'sepa_debit' === $notification->data->object->type || 'three_d_secure' === $notification->data->object->type ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_source_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via source ID: ' . $notification->data->object->id );
			return;
		}

		$order_id  = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		$source_id = $notification->data->object->id;

		$is_pending_receiver = ( 'receiver' === $notification->data->object->flow );

		try {
			if ( $order->has_status( array( 'processing', 'completed' ) ) ) {
				return;
			}

			if ( $order->has_status( 'on-hold' ) && ! $is_pending_receiver ) {
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
			$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source_object ), 'charges', 'POST', true );
			$headers  = $response['headers'];
			$response = $response['body'];

			if ( ! empty( $response->error ) ) {
				// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
				if ( $this->is_no_such_customer_error( $response->error ) ) {
					if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
						delete_user_option( $order->customer_user, '_stripe_customer_id' );
						delete_post_meta( $order_id, '_stripe_customer_id' );
					} else {
						delete_user_option( $order->get_customer_id(), '_stripe_customer_id' );
						$order->delete_meta_data( '_stripe_customer_id' );
						$order->save();
					}
				}

				if ( $this->is_no_such_token_error( $response->error ) && $prepared_source->token_id ) {
					// Source param wrong? The CARD may have been deleted on stripe's end. Remove token and show message.
					$wc_token = WC_Payment_Tokens::get( $prepared_source->token_id );
					$wc_token->delete();
					$localized_message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-stripe' );
					$order->add_order_note( $localized_message );
					throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
				}

				// We want to retry.
				if ( $this->is_retryable_error( $response->error ) ) {
					if ( $retry ) {
						// Don't do anymore retries after this.
						if ( 5 <= $this->retry_interval ) {

							return $this->process_webhook_payment( $notification, false );
						}

						sleep( $this->retry_interval );

						$this->retry_interval++;
						return $this->process_webhook_payment( $notification, true );
					} else {
						$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' );
						$order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}
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

			// To prevent double processing the order on WC side.
			if ( ! $this->is_original_request( $headers ) ) {
				return;
			}

			do_action( 'wc_gateway_stripe_process_webhook_payment', $response, $order );

			$this->process_response( $response, $order );

		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, $e );

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
	 * @param object $notification
	 */
	public function process_webhook_dispute( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->charge );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->charge );
			return;
		}

		/* translators: 1) The URL to the order. */
		$order->update_status( 'on-hold', sprintf( __( 'A dispute was created for this order. Response is needed. Please go to your <a href="%s" title="Stripe Dashboard" target="_blank">Stripe Dashboard</a> to review this dispute.', 'woocommerce-gateway-stripe' ), $this->get_transaction_url( $order ) ) );

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
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

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		if ( 'stripe' === ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			$charge   = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
			$captured = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_stripe_charge_captured', true ) : $order->get_meta( '_stripe_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_stripe_charge_captured', 'yes' ) : $order->update_meta_data( '_stripe_charge_captured', 'yes' );

				// Store other data such as fees
				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $notification->data->object->id ) : $order->set_transaction_id( $notification->data->object->id );

				if ( isset( $notification->data->object->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->balance_transaction );
				}

				// Check and see if capture is partial.
				if ( $this->is_partial_capture( $notification ) ) {
					$partial_amount = $this->get_partial_amount_to_charge( $notification );
					$order->set_total( $partial_amount );
					$this->update_fees( $order, $notification->data->object->refunds->data[0]->balance_transaction );
					/* translators: partial captured amount */
					$order->add_order_note( sprintf( __( 'This charge was partially captured via Stripe Dashboard in the amount of: %s', 'woocommerce-gateway-stripe' ), $partial_amount ) );
				} else {
					$order->payment_complete( $notification->data->object->id );

					/* translators: transaction id */
					$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );
				}

				if ( is_callable( array( $order, 'save' ) ) ) {
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
		// Ignore the notification for charges, created through PaymentIntents.
		if ( isset( $notification->data->object->payment_intent ) && $notification->data->object->payment_intent ) {
			return;
		}

		// The following payment methods are synchronous so does not need to be handle via webhook.
		if ( ( isset( $notification->data->object->source->type ) && 'card' === $notification->data->object->source->type ) || ( isset( $notification->data->object->source->type ) && 'three_d_secure' === $notification->data->object->source->type ) ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		if ( ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		// Store other data such as fees
		WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $notification->data->object->id ) : $order->set_transaction_id( $notification->data->object->id );

		if ( isset( $notification->data->object->balance_transaction ) ) {
			$this->update_fees( $order, $notification->data->object->balance_transaction );
		}

		$order->payment_complete( $notification->data->object->id );

		/* translators: transaction id */
		$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}
	}

	/**
	 * Process webhook charge failed.
	 *
	 * @since 4.0.0
	 * @since 4.1.5 Can handle any fail payments from any methods.
	 * @param object $notification
	 */
	public function process_webhook_charge_failed( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		// If order status is already in failed status don't continue.
		if ( $order->has_status( 'failed' ) ) {
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
	 * @since 4.1.15 Add check to make sure order is processed by Stripe.
	 * @param object $notification
	 */
	public function process_webhook_source_canceled( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		// If can't find order by charge ID, try source ID.
		if ( ! $order ) {
			$order = WC_Stripe_Helper::get_order_by_source_id( $notification->data->object->id );

			if ( ! $order ) {
				WC_Stripe_Logger::log( 'Could not find order via charge/source ID: ' . $notification->data->object->id );
				return;
			}
		}

		// Don't proceed if payment method isn't Stripe.
		if ( 'stripe' !== $order->get_payment_method() ) {
			WC_Stripe_Logger::log( 'Canceled webhook abort: Order was not processed by Stripe: ' . $order->get_id() );
			return;
		}

		if ( ! $order->has_status( 'cancelled' ) ) {
			$order->update_status( 'cancelled', __( 'This payment has cancelled.', 'woocommerce-gateway-stripe' ) );
		}

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );
	}

	/**
	 * Process webhook refund.
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

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		if ( 'stripe' === ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method() ) ) {
			$charge    = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
			$captured  = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_stripe_charge_captured', true ) : $order->get_meta( '_stripe_charge_captured', true );
			$refund_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_stripe_refund_id', true ) : $order->get_meta( '_stripe_refund_id', true );

			// If the refund ID matches, don't continue to prevent double refunding.
			if ( $notification->data->object->refunds->data[0]->id === $refund_id ) {
				return;
			}

			// Only refund captured charge.
			if ( $charge ) {
				$reason = ( isset( $captured ) && 'yes' === $captured ) ? __( 'Refunded via Stripe Dashboard', 'woocommerce-gateway-stripe' ) : __( 'Pre-Authorization Released via Stripe Dashboard', 'woocommerce-gateway-stripe' );

				// Create the refund.
				$refund = wc_create_refund(
					array(
						'order_id' => $order_id,
						'amount'   => $this->get_refund_amount( $notification ),
						'reason'   => $reason,
					)
				);

				if ( is_wp_error( $refund ) ) {
					WC_Stripe_Logger::log( $refund->get_error_message() );
				}

				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_stripe_refund_id', $notification->data->object->refunds->data[0]->id ) : $order->update_meta_data( '_stripe_refund_id', $notification->data->object->refunds->data[0]->id );

				$amount = wc_price( $notification->data->object->refunds->data[0]->amount / 100 );

				if ( in_array( strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->get_order_currency() : $order->get_currency() ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
					$amount = wc_price( $notification->data->object->refunds->data[0]->amount );
				}

				if ( isset( $notification->data->object->refunds->data[0]->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->refunds->data[0]->balance_transaction );
				}

				/* translators: 1) dollar amount 2) transaction id 3) refund message */
				$refund_message = ( isset( $captured ) && 'yes' === $captured ) ? sprintf( __( 'Refunded %1$s - Refund ID: %2$s - %3$s', 'woocommerce-gateway-stripe' ), $amount, $notification->data->object->refunds->data[0]->id, $reason ) : __( 'Pre-Authorization Released via Stripe Dashboard', 'woocommerce-gateway-stripe' );

				$order->add_order_note( $refund_message );
			}
		}
	}

	/**
	 * Process webhook reviews that are opened. i.e Radar.
	 *
	 * @since 4.0.6
	 * @param object $notification
	 */
	public function process_review_opened( $notification ) {
		if ( isset( $notification->data->object->payment_intent ) ) {
			$order = WC_Stripe_Helper::get_order_by_intent_id( $notification->data->object->payment_intent );

			if ( ! $order ) {
				WC_Stripe_Logger::log( '[Review Opened] Could not find order via intent ID: ' . $notification->data->object->payment_intent );
				return;
			}
		} else {
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->charge );

			if ( ! $order ) {
				WC_Stripe_Logger::log( '[Review Opened] Could not find order via charge ID: ' . $notification->data->object->charge );
				return;
			}
		}

		/* translators: 1) The URL to the order. 2) The reason type. */
		$message = sprintf( __( 'A review has been opened for this order. Action is needed. Please go to your <a href="%1$s" title="Stripe Dashboard" target="_blank">Stripe Dashboard</a> to review the issue. Reason: (%2$s)', 'woocommerce-gateway-stripe' ), $this->get_transaction_url( $order ), $notification->data->object->reason );

		if ( apply_filters( 'wc_stripe_webhook_review_change_order_status', true, $order, $notification ) ) {
			$order->update_status( 'on-hold', $message );
		} else {
			$order->add_order_note( $message );
		}
	}

	/**
	 * Process webhook reviews that are closed. i.e Radar.
	 *
	 * @since 4.0.6
	 * @param object $notification
	 */
	public function process_review_closed( $notification ) {
		if ( isset( $notification->data->object->payment_intent ) ) {
			$order = WC_Stripe_Helper::get_order_by_intent_id( $notification->data->object->payment_intent );

			if ( ! $order ) {
				WC_Stripe_Logger::log( '[Review Closed] Could not find order via intent ID: ' . $notification->data->object->payment_intent );
				return;
			}
		} else {
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->charge );

			if ( ! $order ) {
				WC_Stripe_Logger::log( '[Review Closed] Could not find order via charge ID: ' . $notification->data->object->charge );
				return;
			}
		}

		/* translators: 1) The reason type. */
		$message = sprintf( __( 'The opened review for this order is now closed. Reason: (%s)', 'woocommerce-gateway-stripe' ), $notification->data->object->reason );

		if ( $order->has_status( 'on-hold' ) ) {
			if ( apply_filters( 'wc_stripe_webhook_review_change_order_status', true, $order, $notification ) ) {
				$order->update_status( 'processing', $message );
			} else {
				$order->add_order_note( $message );
			}
		} else {
			$order->add_order_note( $message );
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
			$amount = $notification->data->object->refunds->data[0]->amount / 100;

			if ( in_array( strtolower( $notification->data->object->currency ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
				$amount = $notification->data->object->refunds->data[0]->amount;
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

	public function process_payment_intent_success( $notification ) {
		$intent = $notification->data->object;
		$order = WC_Stripe_Helper::get_order_by_intent_id( $intent->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status( array( 'pending', 'failed' ) ) ) {
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		if ( 'payment_intent.succeeded' === $notification->type || 'payment_intent.amount_capturable_updated' === $notification->type ) {
			$charge = end( $intent->charges->data );
			WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );

			do_action( 'wc_gateway_stripe_process_payment', $charge, $order );

			// Process valid response.
			$this->process_response( $charge, $order );

		} else {
			$error_message = $intent->last_payment_error ? $intent->last_payment_error->message : "";

			/* translators: 1) The error message that was received from Stripe. */
			$order->update_status( 'failed', sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'woocommerce-gateway-stripe' ), $error_message ) );

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );

			$this->send_failed_order_email( $order_id );
		}

		$this->unlock_order_payment( $order );
	}

	public function process_setup_intent( $notification ) {
		$intent = $notification->data->object;
		$order = WC_Stripe_Helper::get_order_by_setup_intent_id( $intent->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via setup intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status( array( 'pending', 'failed' ) ) ) {
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		if ( 'setup_intent.succeeded' === $notification->type ) {
			WC_Stripe_Logger::log( "Stripe SetupIntent $intent->id succeeded for order $order_id" );
			if ( WC_Stripe_Helper::is_pre_orders_exists() && WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			} else {
				$order->payment_complete();
			}
		} else {
			$error_message = $intent->last_setup_error ? $intent->last_setup_error->message : "";

			/* translators: 1) The error message that was received from Stripe. */
			$order->update_status( 'failed', sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'woocommerce-gateway-stripe' ), $error_message ) );

			$this->send_failed_order_email( $order_id );
		}

		$this->unlock_order_payment( $order );
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

			case 'review.opened':
				$this->process_review_opened( $notification );
				break;

			case 'review.closed':
				$this->process_review_closed( $notification );
				break;

			case 'payment_intent.succeeded':
			case 'payment_intent.payment_failed':
			case 'payment_intent.amount_capturable_updated':
				$this->process_payment_intent_success( $notification );
				break;

			case 'setup_intent.succeeded':
			case 'setup_intent.setup_failed':
				$this->process_setup_intent( $notification );

		}
	}
}

new WC_Stripe_Webhook_Handler();
