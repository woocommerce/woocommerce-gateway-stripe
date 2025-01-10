<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Webhook_Handler.
 *
 * Handles webhooks from Stripe on sources that are not immediately chargeable.
 *
 * @since 4.0.0
 */
class WC_Stripe_Webhook_Handler extends WC_Stripe_Payment_Gateway {
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
	 * The Action Scheduler service.
	 *
	 * @var WC_Stripe_Action_Scheduler_Service
	 */
	protected $action_scheduler_service;

	/**
	 * How long to wait before processing a deferred webhook.
	 *
	 * @var int
	 */
	protected $deferred_webhook_delay = 2 * MINUTE_IN_SECONDS;

	/**
	 * The Action Scheduler hook to use when retrying a webhook.
	 *
	 * @var string
	 */
	protected $deferred_webhook_action = 'wc_stripe_deferred_webhook';

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 */
	public function __construct() {
		$this->retry_interval = 2;
		$stripe_settings      = WC_Stripe_Helper::get_stripe_settings();
		$this->testmode       = WC_Stripe_Mode::is_test();
		$secret_key           = ( $this->testmode ? 'test_' : '' ) . 'webhook_secret';
		$this->secret         = ! empty( $stripe_settings[ $secret_key ] ) ? $stripe_settings[ $secret_key ] : false;

		$this->action_scheduler_service = new WC_Stripe_Action_Scheduler_Service();

		add_action( 'woocommerce_api_wc_stripe', [ $this, 'check_for_webhook' ] );

		// Get/set the time we began monitoring the health of webhooks by fetching it.
		// This should be roughly the same as the activation time of the version of the
		// plugin when this code first appears.
		WC_Stripe_Webhook_State::get_monitoring_began_at();

		add_action( $this->deferred_webhook_action, [ $this, 'process_deferred_webhook' ], 10, 2 );
	}

	/**
	 * Check incoming requests for Stripe Webhook data and process them.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 */
	public function check_for_webhook() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
			|| ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			|| ! isset( $_GET['wc-api'] )
			|| ( 'wc_stripe' !== $_GET['wc-api'] )
		) {
			return;
		}

		try {
			$request_body = file_get_contents( 'php://input' );
			$event        = json_decode( $request_body );
			$event_type   = $event->type ?? 'No event type found';
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Webhook body could not be retrieved: ' . $e->getMessage() );
			return;
		}

		WC_Stripe_Logger::debug( 'Webhook received: ' . $event_type );

		// Validate it to make sure it is legit.
		$request_headers   = array_change_key_case( $this->get_request_headers(), CASE_UPPER );
		$validation_result = $this->validate_request( $request_headers, $request_body );

		if ( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED === $validation_result ) {
			WC_Stripe_Logger::debug( 'Webhook body: ' . print_r( $request_body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			$this->process_webhook( $request_body );

			WC_Stripe_Webhook_State::set_last_webhook_success_at( $event->created );

			status_header( 200 );
			exit;
		} else {
			WC_Stripe_Logger::error( 'Webhook failed validation. Reason: ' . $validation_result );
			WC_Stripe_Logger::error( 'Webhook body: ' . print_r( $request_body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			WC_Stripe_Webhook_State::set_last_webhook_failure_at( time() );

			if ( WC_Stripe_Webhook_State::VALIDATION_FAILED_SIGNATURE_MISMATCH === $validation_result && $this->has_duplicate_webhooks_setup() ) {
				WC_Stripe_Webhook_State::set_last_error_reason( WC_Stripe_Webhook_State::VALIDATION_FAILED_DUPLICATE_WEBHOOKS );

				// Return a 400 HTTP status code to notify Stripe about a misconfigured webhook when the signature does not match.
				// @see https://docs.stripe.com/webhooks#disable
				status_header( 400 );
				exit;
			}

			WC_Stripe_Webhook_State::set_last_error_reason( $validation_result );

			// A webhook endpoint must return a 2xx HTTP status code to prevent future webhook
			// delivery failures.
			// @see https://docs.stripe.com/webhooks#acknowledge-events-immediately
			status_header( 204 );
			exit;
		}
	}

	/**
	 * Check if the Stripe account has duplicate webhooks setup for this site.
	 *
	 * @since 9.1.0
	 */
	public function has_duplicate_webhooks_setup() {
		$webhook_url = WC_Stripe_Helper::get_webhook_url();
		$webhooks    = WC_Stripe_API::retrieve( 'webhook_endpoints' );

		if ( is_wp_error( $webhooks ) || ! isset( $webhooks->data ) || empty( $webhooks->data ) ) {
			return false;
		}

		$number_of_webhooks = 0;
		foreach ( $webhooks->data as $webhook ) {
			if ( ! isset( $webhook->url ) ) {
				continue;
			}

			if ( $webhook->url === $webhook_url ) {
				$number_of_webhooks++;
			}
		}

		return $number_of_webhooks > 1;
	}

	/**
	 * Verify the incoming webhook notification to make sure it is legit.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 * @param array $request_headers The request headers from Stripe.
	 * @param array $request_body    The request body from Stripe.
	 * @return string The validation result (e.g. self::VALIDATION_SUCCEEDED )
	 */
	public function validate_request( $request_headers, $request_body ) {
		if ( empty( $request_headers ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_EMPTY_HEADERS;
		}
		if ( empty( $request_body ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_EMPTY_BODY;
		}

		if ( empty( $this->secret ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_EMPTY_SECRET;
		}

		// Check for a valid signature.
		$signature_format = '/^t=(?P<timestamp>\d+)(?P<signatures>(,v\d+=[a-z0-9]+){1,2})$/';
		if ( empty( $request_headers['STRIPE-SIGNATURE'] ) || ! preg_match( $signature_format, $request_headers['STRIPE-SIGNATURE'], $matches ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_SIGNATURE_INVALID;
		}

		// Verify the timestamp.
		$timestamp = intval( $matches['timestamp'] );
		if ( abs( $timestamp - time() ) > 5 * MINUTE_IN_SECONDS ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_TIMESTAMP_MISMATCH;
		}

		// Generate the expected signature.
		$signed_payload     = $timestamp . '.' . $request_body;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $this->secret );

		// Check if the expected signature is present.
		if ( ! preg_match( '/,v\d+=' . preg_quote( $expected_signature, '/' ) . '/', $matches['signatures'] ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_SIGNATURE_MISMATCH;
		}

		return WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED;
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
	 * @param bool   $retry
	 */
	public function process_webhook_payment( $notification, $retry = true ) {
		// The following 3 payment methods are synchronous so does not need to be handle via webhook.
		if ( WC_Stripe_Payment_Methods::CARD === $notification->data->object->type || WC_Stripe_Payment_Methods::SEPA_DEBIT === $notification->data->object->type || 'three_d_secure' === $notification->data->object->type ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_source_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via source ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = $order->get_id();

		$is_pending_receiver = ( 'receiver' === $notification->data->object->flow );

		if ( $this->lock_order_payment( $order ) ) {
			return;
		}

		try {
			if ( $order->has_status( [ 'processing', 'completed' ] ) ) {
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
			$prepared_source = $this->prepare_order_source( $order );

			// Make the request.
			$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $prepared_source ), 'charges', 'POST', true );
			$headers  = $response['headers'];
			$response = $response['body'];

			if ( ! empty( $response->error ) ) {
				// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
				if ( $this->is_no_such_customer_error( $response->error ) ) {
					delete_user_option( $order->get_customer_id(), '_stripe_customer_id' );
					$order->delete_meta_data( '_stripe_customer_id' );
					$order->save();
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
					// Unlock the order before retrying.
					$this->unlock_order_payment( $order );

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

			$statuses = [ 'pending', 'failed' ];

			if ( $order->has_status( $statuses ) ) {
				$this->send_failed_order_email( $order_id );
			}
		}

		$this->unlock_order_payment( $order );
	}

	/**
	 * Process webhook dispute that is created.
	 * This is triggered when fraud is detected or customer processes chargeback.
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

		$this->set_stripe_order_status_before_hold( $order, $order->get_status() );

		$needs_response = in_array( $notification->data->object->status, [ 'needs_response', 'warning_needs_response' ], true );
		if ( $needs_response ) {
			$message = sprintf(
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
				__( 'A dispute was created for this order. Response is needed. Please go to your %1$sStripe Dashboard%2$s to review this dispute.', 'woocommerce-gateway-stripe' ),
				'<a href="' . esc_url( $this->get_transaction_url( $order ) ) . '" title="Stripe Dashboard" target="_blank">',
				'</a>'
			);
		} else {
			$message = __( 'A dispute was created for this order.', 'woocommerce-gateway-stripe' );
		}

		if ( ! $order->has_status( 'cancelled' ) && ! $order->get_meta( '_stripe_status_final', false ) ) {
			$order->update_status( 'on-hold', $message );
		} else {
			$order->add_order_note( $message );
			$order->save();
		}

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );

		$order_id = $order->get_id();
		$this->send_failed_order_email( $order_id );
	}

	/**
	 * Process webhook dispute that is closed.
	 *
	 * @since 4.4.1
	 * @param object $notification
	 */
	public function process_webhook_dispute_closed( $notification ) {
		$order  = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->charge );
		$status = $notification->data->object->status;

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->charge );
			return;
		}

		if ( 'lost' === $status ) {
			$message = __( 'The dispute was lost or accepted.', 'woocommerce-gateway-stripe' );
		} elseif ( 'won' === $status ) {
			$message = __( 'The dispute was resolved in your favor.', 'woocommerce-gateway-stripe' );
		} elseif ( 'warning_closed' === $status ) {
			$message = __( 'The inquiry or retrieval was closed.', 'woocommerce-gateway-stripe' );
		} else {
			return;
		}

		if ( apply_filters( 'wc_stripe_webhook_dispute_change_order_status', true, $order, $notification ) ) {
			// Mark final so that order status is not overridden by out-of-sequence events.
			$order->update_meta_data( '_stripe_status_final', true );

			// Fail order if dispute is lost, or else revert to pre-dispute status.
			$order_status = 'lost' === $status ? 'failed' : $this->get_stripe_order_status_before_hold( $order );

			// Do not re-send "Processing Order" email to customer after a dispute win.
			if ( 'processing' === $order_status ) {
				$emails = WC()->mailer()->get_emails();
				if ( isset( $emails['WC_Email_Customer_Processing_Order'] ) ) {
					$callback = [ $emails['WC_Email_Customer_Processing_Order'], 'trigger' ];
					remove_action(
						'woocommerce_order_status_on-hold_to_processing_notification',
						$callback
					);
				}
			}

			$order->update_status( $order_status, $message );
		} else {
			$order->add_order_note( $message );
		}
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

		if ( WC_Stripe_Helper::payment_method_allows_manual_capture( $order->get_payment_method() ) ) {
			$charge   = $order->get_transaction_id();
			$captured = $order->get_meta( '_stripe_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				$order->update_meta_data( '_stripe_charge_captured', 'yes' );

				// Store other data such as fees
				$order->set_transaction_id( $notification->data->object->id );

				if ( isset( $notification->data->object->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->balance_transaction );
				}

				// Check and see if capture is partial.
				if ( $this->is_partial_capture( $notification ) ) {
					$partial_amount = $this->get_partial_amount_to_charge( $notification );
					$order->set_total( $partial_amount );
					$refund_object = $this->get_refund_object( $notification );
					$this->update_fees( $order, $refund_object->balance_transaction );
					/* translators: partial captured amount */
					$order->add_order_note( sprintf( __( 'This charge was partially captured via Stripe Dashboard in the amount of: %s', 'woocommerce-gateway-stripe' ), $partial_amount ) );
				} else {
					$order->payment_complete( $notification->data->object->id );

					/* translators: transaction id */
					$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );
				}

				if ( is_callable( [ $order, 'save' ] ) ) {
					$order->save();
				}
			}
		}
	}

	/**
	 * Process webhook charge succeeded. This is used for payment methods
	 * that takes time to clear which is asynchronous. e.g. SEPA, Sofort.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_charge_succeeded( $notification ) {
		// The following payment methods are synchronous so does not need to be handle via webhook.
		if ( ( isset( $notification->data->object->source->type ) && WC_Stripe_Payment_Methods::CARD === $notification->data->object->source->type ) || ( isset( $notification->data->object->source->type ) && 'three_d_secure' === $notification->data->object->source->type ) ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		if ( ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		// When the plugin's "Issue an authorization on checkout, and capture later"
		// setting is enabled, Stripe API still sends a "charge.succeeded" webhook but
		// the payment has not been captured, yet. This ensures that the payment has been
		// captured, before completing the payment.
		if ( ! $notification->data->object->captured ) {
			return;
		}

		// Store other data such as fees
		$order->set_transaction_id( $notification->data->object->id );

		if ( isset( $notification->data->object->balance_transaction ) ) {
			$this->update_fees( $order, $notification->data->object->balance_transaction );
		}

		/**
		 * If the response has a succeeded status but also has a risk/fraud outcome that requires manual review, don't mark the order as
		 * processing/completed. This will be handled by the incoming review.open webhook.
		 *
		 * Depending on when Stripe sends their events and how quickly it is processed by the store, the review.open webhook (which marks orders as on-hold)
		 * can be processed before or after the payment_intent.success webhook. This difference can lead to orders being incorrectly marked as processing/completed
		 * in WooCommerce, but flagged for manual renewal in Stripe.
		 *
		 * If the review.open webhook was processed before the payment_intent.success, set the processing/completed status in `_stripe_status_before_hold`
		 * to ensure the review.closed event handler will update the status to the proper status.
		 */
		if ( 'manual_review' !== $this->get_risk_outcome( $notification ) ) {
			$order->payment_complete( $notification->data->object->id );

			/* translators: transaction id */
			$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );
		}

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
	}

	/**
	 * Process webhook charge failed.
	 *
	 * @since 4.0.0
	 * @since 4.1.5 Can handle any fail payments from any methods.
	 * @since 9.0.0 Can handle payment expiration.
	 * @param object $notification
	 */
	public function process_webhook_charge_failed( $notification ) {
		$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		// If order status is already in failed status don't continue.
		if ( $order->has_status( 'failed' ) ) {
			return;
		}

		if ( 'charge.expired' === $notification->type ) {
			$message = __( 'This payment has expired.', 'woocommerce-gateway-stripe' );
		} else {
			$message = __( 'This payment failed to clear.', 'woocommerce-gateway-stripe' );
		}
		if ( ! $order->get_meta( '_stripe_status_final', false ) ) {
			$order->update_status( 'failed', $message );
		} else {
			$order->add_order_note( $message );
		}

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

		$message = __( 'This payment was cancelled.', 'woocommerce-gateway-stripe' );
		if ( ! $order->has_status( 'cancelled' ) && ! $order->get_meta( '_stripe_status_final', false ) ) {
			$order->update_status( 'cancelled', $message );
		} else {
			$order->add_order_note( $message );
		}

		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );
	}

	/**
	 * Process webhook refund.
	 *
	 * @since 4.0.0
	 * @version 4.9.0
	 * @param object $notification
	 */
	public function process_webhook_refund( $notification ) {
		$refund_object = $this->get_refund_object( $notification );
		$order = WC_Stripe_Helper::get_order_by_refund_id( $refund_object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via refund ID: ' . $refund_object->id );
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );
		}

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = $order->get_id();

		if ( 'stripe' === substr( (string) $order->get_payment_method(), 0, 6 ) ) {
			$charge        = $order->get_transaction_id();
			$captured      = $order->get_meta( '_stripe_charge_captured' );
			$refund_id     = $order->get_meta( '_stripe_refund_id' );
			$currency      = $order->get_currency();
			$raw_amount    = $refund_object->amount;

			if ( ! in_array( strtolower( $currency ), WC_Stripe_Helper::no_decimal_currencies(), true ) ) {
				$raw_amount /= 100;
			}

			$amount = wc_price( $raw_amount, [ 'currency' => $currency ] );

			// If charge wasn't captured, skip creating a refund.
			if ( 'yes' !== $captured ) {
				// If the process was initiated from wp-admin,
				// the order was already cancelled, so we don't need a new note.
				if ( 'cancelled' !== $order->get_status() ) {
					/* translators: amount (including currency symbol) */
					$order->add_order_note( sprintf( __( 'Pre-Authorization for %s voided from the Stripe Dashboard.', 'woocommerce-gateway-stripe' ), $amount ) );
					$order->update_status( 'cancelled' );
				}

				return;
			}

			if ( $this->lock_order_refund( $order ) ) {
				return;
			}

			// If the refund ID matches, don't continue to prevent double refunding.
			if ( $refund_object->id === $refund_id ) {
				return;
			}

			if ( $charge ) {
				$reason = __( 'Refunded via Stripe Dashboard', 'woocommerce-gateway-stripe' );

				// Create the refund.
				$refund = wc_create_refund(
					[
						'order_id' => $order_id,
						'amount'   => $this->get_refund_amount( $notification ),
						'reason'   => $reason,
					]
				);

				if ( is_wp_error( $refund ) ) {
					WC_Stripe_Logger::log( $refund->get_error_message() );
				}

				$order->update_meta_data( '_stripe_refund_id', $refund_object->id );

				if ( isset( $refund_object->balance_transaction ) ) {
					$this->update_fees( $order, $refund_object->balance_transaction );
				}

				$this->unlock_order_refund( $order );

				/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund message */
				$order->add_order_note( sprintf( __( 'Refunded %1$s - Refund ID: %2$s - %3$s', 'woocommerce-gateway-stripe' ), $amount, $refund_object->id, $reason ) );
			}
		}
	}

	/**
	 * Process a refund update.
	 *
	 * @param object $notification
	 */
	public function process_webhook_refund_updated( $notification ) {
		$refund_object = $notification->data->object;
		$order         = WC_Stripe_Helper::get_order_by_charge_id( $refund_object->charge );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order to update refund via charge ID: ' . $refund_object->charge );
			return;
		}

		$order_id = $order->get_id();

		if ( 'stripe' === substr( (string) $order->get_payment_method(), 0, 6 ) ) {
			$charge     = $order->get_transaction_id();
			$refund_id  = $order->get_meta( '_stripe_refund_id' );
			$currency   = $order->get_currency();
			$raw_amount = $refund_object->amount;

			if ( ! in_array( strtolower( $currency ), WC_Stripe_Helper::no_decimal_currencies(), true ) ) {
				$raw_amount /= 100;
			}

			$amount = wc_price( $raw_amount, [ 'currency' => $currency ] );

			// If the refund IDs do not match stop.
			if ( $refund_object->id !== $refund_id ) {
				return;
			}

			if ( $charge ) {
				$refunds = wc_get_orders(
					[
						'limit'  => 1,
						'parent' => $order_id,
					]
				);

				if ( empty( $refunds ) ) {
					// No existing refunds nothing to update.
					return;
				}

				$refund = $refunds[0];

				if ( in_array( $refund_object->status, [ 'failed', 'canceled' ], true ) ) {
					if ( isset( $refund_object->failure_balance_transaction ) ) {
						$this->update_fees( $order, $refund_object->failure_balance_transaction );
					}
					$refund->delete( true );
					do_action( 'woocommerce_refund_deleted', $refund_id, $order_id );
					if ( 'failed' === $refund_object->status ) {
						/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund failure code */
						$note = sprintf( __( 'Refund failed for %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $amount, $refund_object->id, $refund_object->failure_reason );
					} else {
						/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund failure code */
						$note = sprintf( __( 'Refund canceled for %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $amount, $refund_object->id, $refund_object->failure_reason );
					}

					$order->add_order_note( $note );
				}
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

		$this->set_stripe_order_status_before_hold( $order, $order->get_status() );

		$message = sprintf(
		/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag 3) The reason type. */
			__( 'A review has been opened for this order. Action is needed. Please go to your %1$sStripe Dashboard%2$s to review the issue. Reason: (%3$s).', 'woocommerce-gateway-stripe' ),
			'<a href="' . esc_url( $this->get_transaction_url( $order ) ) . '" title="Stripe Dashboard" target="_blank">',
			'</a>',
			esc_html( $notification->data->object->reason )
		);

		if ( apply_filters( 'wc_stripe_webhook_review_change_order_status', true, $order, $notification ) && ! $order->get_meta( '_stripe_status_final', false ) ) {
			$order->update_status( 'on-hold', $message );
		} else {
			$order->add_order_note( $message );
			$order->save(); // update_status() calls save on the order, so make sure we manually call save() when not updating the status to ensure meta is saved.
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

		// Only change the status if the charge was captured, status is not final, the order is on-hold and the review was approved.
		if ( 'yes' === $order->get_meta( '_stripe_charge_captured' ) &&
			! $order->get_meta( '_stripe_status_final', false ) &&
			$order->has_status( 'on-hold' ) &&
			( ! empty( $notification->data->object->closed_reason ) && 'approved' === $notification->data->object->closed_reason ) &&
			apply_filters( 'wc_stripe_webhook_review_change_order_status', true, $order, $notification )
		) {
			// If the status we stored before hold is an incomplete status, restore the status to processing/completed instead.
			$status_after_review = $this->get_stripe_order_status_before_hold( $order );
			if ( in_array( $status_after_review, apply_filters( 'woocommerce_valid_order_statuses_for_payment_complete', [ 'on-hold', 'pending', 'failed', 'cancelled' ], $order ), true ) ) {
				$status_after_review = apply_filters( 'woocommerce_payment_complete_order_status', $order->needs_processing() ? 'processing' : 'completed', $order->get_id(), $order );
			}
			$order->update_status( $status_after_review, $message );
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
	 * Gets the first refund object from charge notification.
	 *
	 * @since 7.0.2
	 * @param object $notification
	 *
	 * @return object
	 */
	public function get_refund_object( $notification ) {
		// Since API version 2022-11-15, the Charge object no longer expands `refunds` by default.
		// We can remove this once we drop support for API versions prior to 2022-11-15.
		if ( ! empty( $notification->data->object->refunds->data[0] ) ) {
			return $notification->data->object->refunds->data[0];
		}

		$charge = $this->get_charge_object( $notification->data->object->id, [ 'expand' => [ 'refunds' ] ] );
		return $charge->refunds->data[0];
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
			$refund_object = $this->get_refund_object( $notification );
			$amount        = $refund_object->amount / 100;

			if ( in_array( strtolower( $notification->data->object->currency ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
				$amount = $refund_object->amount;
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
	 * Handles the processing of a payment intent webhook.
	 *
	 * @param stdClass $notification The webhook notification from Stripe.
	 */
	public function process_payment_intent( $notification ) {
		$intent = $notification->data->object;
		$order  = $this->get_order_from_intent( $intent );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status(
			apply_filters(
				'wc_stripe_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ],
				$order
			)
		) ) {
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		$order_id           = $order->get_id();
		$payment_type_meta  = $order->get_meta( '_stripe_upe_payment_type' );
		$is_voucher_payment = in_array( $payment_type_meta, WC_Stripe_Payment_Methods::VOUCHER_PAYMENT_METHODS, true );
		$is_wallet_payment  = in_array( $payment_type_meta, WC_Stripe_Payment_Methods::WALLET_PAYMENT_METHODS, true );

		switch ( $notification->type ) {
			case 'payment_intent.requires_action':
				do_action( 'wc_gateway_stripe_process_payment_intent_requires_action', $order, $notification->data->object );

				if ( $is_voucher_payment ) {
					$order->update_status( 'on-hold', __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
					wc_reduce_stock_levels( $order_id );
				}
				break;
			case 'payment_intent.succeeded':
			case 'payment_intent.amount_capturable_updated':
				WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );

				$process_webhook_async = apply_filters( 'wc_stripe_process_payment_intent_webhook_async', true, $order, $intent, $notification );
				$is_awaiting_action    = $order->get_meta( '_stripe_upe_waiting_for_redirect' ) ?? false;

				// Process the webhook now if it's for a voucher or wallet payment , or if filtered to process immediately and order is not awaiting action.
				if ( $is_voucher_payment || $is_wallet_payment || ( ! $process_webhook_async && ! $is_awaiting_action ) ) {
					$charge = $this->get_latest_charge_from_intent( $intent );

					do_action( 'wc_gateway_stripe_process_payment', $charge, $order );

					$this->process_response( $charge, $order );
				} else {
					WC_Stripe_Logger::log( "Processing $notification->type ($intent->id) asynchronously for order $order_id." );

					// Schedule a job to check on the status of this intent.
					$this->defer_webhook_processing(
						$notification,
						[
							'order_id'  => $order_id,
							'intent_id' => $intent->id,
						]
					);

					if ( $is_awaiting_action ) {
						do_action( 'wc_gateway_stripe_process_payment_intent_incomplete', $order );
					}
				}

				break;
			default:
				if ( $is_voucher_payment && 'payment_intent.payment_failed' === $notification->type ) {
					$order->update_status( 'failed', __( 'Payment not completed in time', 'woocommerce-gateway-stripe' ) );
					wc_increase_stock_levels( $order_id );
					break;
				}

				$error_message = $intent->last_payment_error->message ?? '';

				/* translators: 1) The error message that was received from Stripe. */
				$message = sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'woocommerce-gateway-stripe' ), $error_message );

				$status_update = [];
				if ( ! $order->get_meta( '_stripe_status_final', false ) ) {
					$status_update['from'] = $order->get_status();
					$status_update['to']   = 'failed';
					$order->update_status( 'failed', $message );
				} else {
					$order->add_order_note( $message );
				}

				do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );

				$this->send_failed_order_email( $order_id, $status_update );
				break;
		}

		$this->unlock_order_payment( $order );
	}

	public function process_setup_intent( $notification ) {
		$intent = $notification->data->object;
		$order  = WC_Stripe_Helper::get_order_by_setup_intent_id( $intent->id );

		if ( ! $order ) {
			WC_Stripe_Logger::log( 'Could not find order via setup intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status(
			apply_filters(
				'wc_gateway_stripe_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ]
			)
		) ) {
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		$order_id = $order->get_id();
		if ( 'setup_intent.succeeded' === $notification->type ) {
			WC_Stripe_Logger::log( "Stripe SetupIntent $intent->id succeeded for order $order_id" );
			if ( $this->has_pre_order( $order ) ) {
				$this->mark_order_as_pre_ordered( $order );
			} else {
				$order->payment_complete();
			}
		} else {
			$error_message = $intent->last_setup_error ? $intent->last_setup_error->message : '';

			/* translators: 1) The error message that was received from Stripe. */
			$message = sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'woocommerce-gateway-stripe' ), $error_message );

			$status_update = [];
			if ( ! $order->get_meta( '_stripe_status_final', false ) ) {
				$status_update['from'] = $order->get_status();
				$status_update['to']   = 'failed';
				$order->update_status( 'failed', $message );
			} else {
				$order->add_order_note( $message );
			}

			$this->send_failed_order_email( $order_id, $status_update );
		}

		$this->unlock_order_payment( $order );
	}

	/**
	 * Schedules a job to run in the future to check on the status of a webhook.
	 *
	 * Each Webhook type which is deferred should be supported by @see process_deferred_webhook().
	 *
	 * @param stdClass $webhook_notification The webhook payload received from Stripe.
	 * @param array    $additional_data      Additional data to pass to the scheduled job.
	 */
	protected function defer_webhook_processing( $webhook_notification, $additional_data ) {
		$this->action_scheduler_service->schedule_job(
			time() + $this->deferred_webhook_delay,
			$this->deferred_webhook_action,
			[
				'type' => $webhook_notification->type,
				'data' => $additional_data,
			]
		);
	}

	/**
	 * Processes a deferred webhook event.
	 *
	 * Deferred webhooks are scheduled by @see defer_webhook_processing().
	 *
	 * @param string $webhook_type    The webhook event name/type.
	 * @param array  $additional_data Additional data passed to the scheduled job.
	 */
	public function process_deferred_webhook( $webhook_type, $additional_data ) {
		try {
			switch ( $webhook_type ) {
				case 'payment_intent.succeeded':
				case 'payment_intent.amount_capturable_updated':
					$order     = isset( $additional_data['order_id'] ) ? wc_get_order( $additional_data['order_id'] ) : null;
					$intent_id = $additional_data['intent_id'] ?? '';

					if ( empty( $order ) ) {
						throw new Exception( "Missing required data. 'order_id' is invalid or not found for the deferred '{$webhook_type}' event." );
					}

					if ( empty( $intent_id ) ) {
						throw new Exception( "Missing required data. 'intent_id' is missing for the deferred '{$webhook_type}' event." );
					}

					// Check if the order is still in a valid state to process the webhook.
					if ( ! $order->has_status( apply_filters( 'wc_stripe_allowed_payment_processing_statuses', [ 'pending', 'failed' ], $order ) ) ) {
						WC_Stripe_Logger::log( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - payment already complete." );
						return;
					}

					$this->handle_deferred_payment_intent_succeeded( $order, $intent_id );
					break;
				default:
					throw new Exception( "Unsupported webhook type: {$webhook_type}" );
					break;
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Error processing deferred webhook: ' . $e->getMessage() );

			// This will be caught by Action Scheduler and logged as an error.
			throw $e;
		}
	}

	/**
	 * Handles a deferred payment_intent.succeeded event.
	 *
	 * @param WC_Order $order     The order object.
	 * @param string   $intent_id The payment intent ID.
	 */
	protected function handle_deferred_payment_intent_succeeded( $order, $intent_id ) {
		$intent = $this->get_intent_from_order( $order );

		if ( ! $intent || $intent->id !== $intent_id ) {
			WC_Stripe_Logger::log( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - intent ID stored on order ({$intent->id}) doesn't match." );
			return;
		}

		$charge = $this->get_latest_charge_from_intent( $intent );

		if ( ! $charge ) {
			WC_Stripe_Logger::log( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - no charge found." );
			return;
		}

		WC_Stripe_Logger::log( "Processing Stripe PaymentIntent {$intent_id} for order {$order->get_id()} via deferred webhook." );

		do_action( 'wc_gateway_stripe_process_payment', $charge, $order );
		$this->process_response( $charge, $order );

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
			case 'charge.expired':
				$this->process_webhook_charge_failed( $notification );
				break;

			case 'charge.captured':
				$this->process_webhook_capture( $notification );
				break;

			case 'charge.dispute.created':
				$this->process_webhook_dispute( $notification );
				break;

			case 'charge.dispute.closed':
				$this->process_webhook_dispute_closed( $notification );
				break;

			case 'charge.refunded':
				$this->process_webhook_refund( $notification );
				break;

			case 'charge.refund.updated':
				$this->process_webhook_refund_updated( $notification );
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
			case 'payment_intent.requires_action':
				$this->process_payment_intent( $notification );
				break;

			case 'setup_intent.succeeded':
			case 'setup_intent.setup_failed':
				$this->process_setup_intent( $notification );

		}
	}

	/**
	 * Fetches an order from a payment intent.
	 *
	 * @param stdClass $intent The Stripe PaymentIntent object.
	 * @return WC_Order|false The order object, or false if not found.
	 */
	private function get_order_from_intent( $intent ) {
		// Attempt to get the order from the intent metadata.
		if ( isset( $intent->metadata ) ) {
			// Try to retrieve from the signature
			if ( isset( $intent->metadata->signature ) ) {
				$signature = wc_clean( $intent->metadata->signature );
				$data      = explode( ':', $signature );

				// Verify we received the order ID and signature (hash).
				$order = isset( $data[0], $data[1] ) ? wc_get_order( absint( $data[0] ) ) : false;

				if ( $order ) {
					$intent_id = WC_Stripe_Helper::get_intent_id_from_order( $order );

					// Return the order if the intent ID matches.
					if ( $intent->id === $intent_id ) {
						return $order;
					}

					/**
					 * If the order has no intent ID stored, we may have failed to store it during the initial payment request.
					 * Confirm that the signature matches the order, otherwise fall back to finding the order via the intent ID.
					 */
					if ( empty( $intent_id ) && $this->get_order_signature( $order ) === $signature ) {
						return $order;
					}
				}
			}

			// Try to retrieve from the metadata order ID.
			if ( isset( $intent->metadata->order_id ) ) {
				return wc_get_order( absint( $intent->metadata->order_id ) );
			}
		}

		// Try to retrieve from the charges array.
		if ( ! empty( $intent->charges ) ) {
			$charge   = $intent->charges[0] ?? [];
			$order_id = $charge->metadata->order_id ?? null;
			return $order_id ? wc_get_order( $order_id ) : false;
		}

		// Fall back to finding the order via the intent ID.
		return WC_Stripe_Helper::get_order_by_intent_id( $intent->id );
	}
}

new WC_Stripe_Webhook_Handler();
