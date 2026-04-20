<?php

use Automattic\WooCommerce\Enums\OrderStatus;

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
	 * How long to wait before processing checkout session metadata after a webhook.
	 *
	 * @var int
	 */
	protected $process_checkout_session_metadata_delay = 2 * MINUTE_IN_SECONDS;

	/**
	 * The Action Scheduler hook to use when processing checkout session metadata after a webhook.
	 *
	 * @var string
	 */
	protected $process_checkout_session_metadata_action = 'wc_stripe_process_checkout_session_metadata';

	/**
	 * The order object being processed.
	 *
	 * @var WC_Order|null
	 */
	protected $resolved_order = null;

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

		add_action( $this->deferred_webhook_action, [ $this, 'process_deferred_webhook' ], 10, 3 );
		add_action( $this->process_checkout_session_metadata_action, [ $this, 'process_checkout_session_metadata' ], 10, 2 );
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
			WC_Stripe_Logger::error( 'Webhook body could not be retrieved', [ 'error' => $e ] );
			return;
		}

		WC_Stripe_Webhook_State::set_pending_webhooks_count( $event->pending_webhooks ?? 0 );

		$is_agentic_hook = 0 === strpos( $event_type, 'v1.delegated_checkout.' );

		$secret = $is_agentic_hook
			? ( defined( 'AGENTIC_COMMERCE_WEBHOOK_SECRET' ) ? AGENTIC_COMMERCE_WEBHOOK_SECRET : '' )
			: $this->secret;

		// Validate it to make sure it is legit.
		$request_headers   = array_change_key_case( $this->get_request_headers(), CASE_UPPER );
		$validation_result = $this->validate_request( $request_headers, $request_body, $secret );

		if ( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED !== $validation_result ) {
			WC_Stripe_Logger::error(
				'Webhook validation failed (' . $validation_result . ')',
				[
					'request_headers' => $request_headers,
					'event'           => $event,
				]
			);

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

		if ( $is_agentic_hook ) {
			$this->process_agentic_hook( $event );
			return;
		}

		WC_Stripe_Logger::debug( 'Webhook received (' . $event_type . ')', [ 'event' => $event ] );
		$this->process_webhook( $request_body );
		WC_Stripe_Webhook_State::set_last_webhook_success_at( $event->created );
		status_header( 200 );
		exit;
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
				++$number_of_webhooks;
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
	 * @param string $secret         The secret key for the webhook.
	 * @return string The validation result (e.g. self::VALIDATION_SUCCEEDED )
	 */
	public function validate_request( $request_headers, $request_body, $secret ) {
		if ( empty( $request_headers ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_EMPTY_HEADERS;
		}
		if ( empty( $request_body ) ) {
			return WC_Stripe_Webhook_State::VALIDATION_FAILED_EMPTY_BODY;
		}

		// Skip validation for E2E tests in test mode.
		if (
			filter_var( getenv( 'E2E_TESTING' ), FILTER_VALIDATE_BOOLEAN )
			&& WC_Stripe_Mode::is_test()
		) {
			return WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED;
		}

		if ( empty( $secret ) ) {
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
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $secret );

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
			WC_Stripe_Logger::warning( 'Could not find order via source ID: ' . $notification->data->object->id );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_id = $order->get_id();

		$is_pending_receiver = ( 'receiver' === $notification->data->object->flow );

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( $order_helper->lock_order_payment( $order ) ) {
			return;
		}

		try {
			if ( $order->has_status( [ OrderStatus::PROCESSING, OrderStatus::COMPLETED ] ) ) {
				return;
			}

			if ( $order->has_status( OrderStatus::ON_HOLD ) && ! $is_pending_receiver ) {
				return;
			}

			// Result from Stripe API request.
			$response = null;

			// This will throw exception if not valid.
			$order_helper->validate_minimum_order_amount( $order );

			WC_Stripe_Logger::info( "Info: (Webhook) Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

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
					$order_helper->delete_stripe_customer_id( $order );
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
					$order_helper->unlock_order_payment( $order );

					if ( $retry ) {
						// Don't do anymore retries after this.
						if ( 5 <= $this->retry_interval ) {

							return $this->process_webhook_payment( $notification, false );
						}

						sleep( $this->retry_interval );

						++$this->retry_interval;
						return $this->process_webhook_payment( $notification, true );
					} else {
						$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' );
						$order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}
				}

				$localized_message = WC_Stripe_Helper::get_localized_error_message_from_response( $response );

				$order->add_order_note( $localized_message );

				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}

			// To prevent double processing the order on WC side.
			if ( ! $this->is_original_request( $headers ) ) {
				return;
			}

			do_action_deprecated(
				'wc_gateway_stripe_process_webhook_payment',
				[ $response, $order ],
				'9.7.0',
				'wc_gateway_stripe_process_payment_charge',
				'The wc_gateway_stripe_process_webhook_payment action is deprecated. Use wc_gateway_stripe_process_payment_charge instead.'
			);

			$response->is_webhook_response = true;
			$this->process_response( $response, $order );

		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::error( 'Error processing webhook payment for order: ' . $order_id, [ 'error_message' => $e->getMessage() ] );

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, $e );

			$statuses = [ OrderStatus::PENDING, OrderStatus::FAILED ];

			if ( $order->has_status( $statuses ) ) {
				$this->send_failed_order_email( $order_id );
			}
		}

		$order_helper->unlock_order_payment( $order );
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
			WC_Stripe_Logger::warning( 'Could not find order via charge ID: ' . $notification->data->object->charge );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

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

		if ( ! $order->has_status( OrderStatus::CANCELLED ) && ! WC_Stripe_Order_Helper::get_instance()->is_stripe_status_final( $order ) ) {
			$order->update_status( OrderStatus::ON_HOLD, $message );
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
			WC_Stripe_Logger::warning( 'Could not find order via charge ID: ' . $notification->data->object->charge );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

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
			WC_Stripe_Order_Helper::get_instance()->set_stripe_status_final( $order, true );

			// Fail order if dispute is lost, or else revert to pre-dispute status.
			$order_status = 'lost' === $status ? OrderStatus::FAILED : $this->get_stripe_order_status_before_hold( $order );

			// Do not re-send "Processing Order" email to customer after a dispute win.
			if ( OrderStatus::PROCESSING === $order_status ) {
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
		$order        = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( ! $order ) {
			WC_Stripe_Logger::warning( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		if ( WC_Stripe_Helper::payment_method_allows_manual_capture( $order->get_payment_method() ) ) {
			$charge   = $order->get_transaction_id();
			$captured = $order_helper->is_stripe_charge_captured( $order );

			if ( $charge && ! $captured ) {
				$order_helper->set_stripe_charge_captured( $order, true );

				// Store other data such as fees
				$order->set_transaction_id( $notification->data->object->id );

				if ( isset( $notification->data->object->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->balance_transaction, true );
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
					$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s) (via webhook)', 'woocommerce-gateway-stripe' ), $notification->data->object->id ) );
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
		if ( empty( $notification->data->object ) ) {
			WC_Stripe_Logger::error( 'Missing charge object in charge.succeeded webhook, Event ID: %s', $notification->id ?? 'unknown' );
			return;
		}

		// https://docs.stripe.com/api/events/types#event_types-charge.succeeded
		$charge = $notification->data->object;

		// The following payment methods are synchronous so does not need to be handled via webhook.
		$payment_method_type = $this->get_payment_method_type_from_charge( $charge );
		$synchronous_methods = [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			'three_d_secure',
		];

		if ( in_array( $payment_method_type, $synchronous_methods, true ) ) {
			return;
		}

		$order = WC_Stripe_Helper::get_order_by_charge_id( $charge->id );

		if ( ! $order ) {
			WC_Stripe_Logger::debug( 'Could not find order via charge ID: ' . $charge->id );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		if ( ! $order->has_status( OrderStatus::ON_HOLD ) ) {
			return;
		}

		// When the plugin's "Issue an authorization on checkout, and capture later"
		// setting is enabled, Stripe API still sends a "charge.succeeded" webhook but
		// the payment has not been captured, yet. This ensures that the payment has been
		// captured, before completing the payment.
		if ( ! $charge->captured ) {
			return;
		}

		// Store other data such as fees
		$order->set_transaction_id( $charge->id );

		if ( isset( $charge->balance_transaction ) ) {
			$this->update_fees( $order, $charge->balance_transaction, true );
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
			$order->payment_complete( $charge->id );

			/* translators: transaction id */
			$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s) (via webhook)', 'woocommerce-gateway-stripe' ), $charge->id ) );
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
			WC_Stripe_Logger::warning( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		// If order status is already in failed status don't continue.
		if ( $order->has_status( OrderStatus::FAILED ) ) {
			return;
		}

		if ( 'charge.expired' === $notification->type ) {
			$message = __( 'This payment has expired.', 'woocommerce-gateway-stripe' );
		} else {
			$message = __( 'This payment failed to clear.', 'woocommerce-gateway-stripe' );
		}
		if ( ! WC_Stripe_Order_Helper::get_instance()->is_stripe_status_final( $order ) ) {
			$order->update_status( OrderStatus::FAILED, $message );
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
				WC_Stripe_Logger::warning( 'Could not find order via charge/source ID: ' . $notification->data->object->id );
				return;
			}
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		// Don't proceed if payment method isn't Stripe.
		if ( 'stripe' !== $order->get_payment_method() ) {
			WC_Stripe_Logger::warning( 'Canceled webhook abort: Order was not processed by Stripe: ' . $order->get_id() );
			return;
		}

		$message = __( 'This payment was cancelled.', 'woocommerce-gateway-stripe' );
		if ( ! $order->has_status( OrderStatus::CANCELLED ) && ! WC_Stripe_Order_Helper::get_instance()->is_stripe_status_final( $order ) ) {
			$order->update_status( OrderStatus::CANCELLED, $message );
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
		$order         = WC_Stripe_Helper::get_order_by_refund_id( $refund_object->id );

		if ( ! $order ) {
			WC_Stripe_Logger::debug( 'Could not find order via refund ID: ' . $refund_object->id );
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );
		}

		if ( ! $order ) {
			WC_Stripe_Logger::warning( "Could not find order via refund ID ({$refund_object->id}) or charge ID ({$notification->data->object->id})" );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_id = $order->get_id();

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( $order_helper->is_stripe_gateway_order( $order ) ) {
			$charge     = $order->get_transaction_id();
			$captured   = $order_helper->is_stripe_charge_captured( $order );
			$refund_id  = $order_helper->get_stripe_refund_id( $order );
			$currency   = $order->get_currency();
			$raw_amount = $refund_object->amount;

			if ( ! in_array( strtoupper( $currency ), WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES, true ) ) {
				$raw_amount /= 100;
			}

			$amount = wc_price( $raw_amount, [ 'currency' => $currency ] );

			// If charge wasn't captured, skip creating a refund.
			if ( ! $captured ) {
				// If the process was initiated from wp-admin,
				// the order was already cancelled, so we don't need a new note.
				if ( OrderStatus::CANCELLED !== $order->get_status() ) {
					/* translators: amount (including currency symbol) */
					$order->add_order_note( sprintf( __( 'Pre-Authorization for %s voided from the Stripe Dashboard.', 'woocommerce-gateway-stripe' ), $amount ) );
					$order->update_status( OrderStatus::CANCELLED );
				}

				return;
			}

			if ( $order_helper->lock_order_refund( $order ) ) {
				return;
			}

			// If the refund ID matches, don't continue to prevent double refunding.
			if ( $refund_object->id === $refund_id ) {
				return;
			}

			if ( $charge ) {
				$reason = __( 'Refunded via Stripe Dashboard', 'woocommerce-gateway-stripe' );

				$this->set_stripe_order_status_before_refund( $order, $order->get_status() );

				// Create the refund.
				$refund = wc_create_refund(
					[
						'order_id' => $order_id,
						'amount'   => $this->get_refund_amount( $notification ),
						'reason'   => $reason,
					]
				);

				if ( is_wp_error( $refund ) ) {
					WC_Stripe_Logger::error( 'Error creating refund for order: ' . $order_id, [ 'error_message' => $refund->get_error_message() ] );
				}

				$order_helper->update_stripe_refund_id( $order, $refund_object->id );

				if ( isset( $refund_object->balance_transaction ) ) {
					$this->update_fees( $order, $refund_object->balance_transaction );
				}

				$order_helper->unlock_order_refund( $order );

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
			WC_Stripe_Logger::warning( 'Could not find order to update refund via charge ID: ' . $refund_object->charge );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_id     = $order->get_id();
		$order_helper = WC_Stripe_Order_Helper::get_instance();
		if ( $order_helper->is_stripe_gateway_order( $order ) ) {
			$charge     = $order->get_transaction_id();
			$refund_id  = $order_helper->get_stripe_refund_id( $order );
			$currency   = $order->get_currency();
			$raw_amount = $refund_object->amount;

			if ( ! in_array( strtoupper( $currency ), WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES, true ) ) {
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

					$order_helper->update_stripe_refund_status( $order, $refund_object->status );

					$friendly_failure_reason = WC_Stripe_Helper::get_refund_reason_description( $refund_object->failure_reason );
					if ( 'failed' === $refund_object->status ) {
						/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund failure code */
						$note = sprintf( __( 'Refund failed for %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $amount, $refund_object->id, $friendly_failure_reason );
					} else {
						/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund failure code */
						$note = sprintf( __( 'Refund canceled for %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $amount, $refund_object->id, $friendly_failure_reason );
					}

					// Store the raw failure reason
					if ( isset( $refund_object->failure_reason ) ) {
						$order_helper->update_stripe_refund_failure_reason( $order, $refund_object->failure_reason );
					} else {
						$order_helper->delete_stripe_refund_failure_reason( $order );
					}

					// Revert to previous status
					$status_before_refund            = $this->get_stripe_order_status_before_refund( $order );
					$valid_payment_complete_statuses = apply_filters( 'woocommerce_valid_order_statuses_for_payment_complete', [ OrderStatus::ON_HOLD, OrderStatus::PENDING, OrderStatus::FAILED, OrderStatus::CANCELLED ], $order );
					if ( ! in_array( $status_before_refund, $valid_payment_complete_statuses, true ) ) {
						$default_status       = $order->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED;
						$status_before_refund = apply_filters( 'woocommerce_payment_complete_order_status', $default_status, $order->get_id(), $order );
					}

					// If the order has the same status before refund, just add a note.
					if ( $order->has_status( $status_before_refund ) ) {
						$order->add_order_note( $note );
					} else {
						$order->update_status( $status_before_refund, $note );
					}

					$this->send_failed_refund_emails( $order );
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
				WC_Stripe_Logger::warning( '[Review Opened] Could not find order via intent ID: ' . $notification->data->object->payment_intent );
				return;
			}
		} else {
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->charge );

			if ( ! $order ) {
				WC_Stripe_Logger::warning( '[Review Opened] Could not find order via charge ID: ' . $notification->data->object->charge );
				return;
			}
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$this->set_stripe_order_status_before_hold( $order, $order->get_status() );

		$message = sprintf(
		/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag 3) The reason type. */
			__( 'A review has been opened for this order. Action is needed. Please go to your %1$sStripe Dashboard%2$s to review the issue. Reason: (%3$s).', 'woocommerce-gateway-stripe' ),
			'<a href="' . esc_url( $this->get_transaction_url( $order ) ) . '" title="Stripe Dashboard" target="_blank">',
			'</a>',
			esc_html( $notification->data->object->reason )
		);

		if ( apply_filters( 'wc_stripe_webhook_review_change_order_status', true, $order, $notification ) && ! WC_Stripe_Order_Helper::get_instance()->is_stripe_status_final( $order ) ) {
			$order->update_status( OrderStatus::ON_HOLD, $message );
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
				WC_Stripe_Logger::warning( '[Review Closed] Could not find order via intent ID: ' . $notification->data->object->payment_intent );
				return;
			}
		} else {
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->charge );

			if ( ! $order ) {
				WC_Stripe_Logger::warning( '[Review Closed] Could not find order via charge ID: ' . $notification->data->object->charge );
				return;
			}
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		/* translators: 1) The reason type. */
		$message = sprintf( __( 'The opened review for this order is now closed. Reason: (%s)', 'woocommerce-gateway-stripe' ), $notification->data->object->reason );

		// Only change the status if the charge was captured, status is not final, the order is on-hold and the review was approved.
		if ( $order_helper->is_stripe_charge_captured( $order ) &&
			! $order_helper->is_stripe_status_final( $order ) &&
			$order->has_status( OrderStatus::ON_HOLD ) &&
			( ! empty( $notification->data->object->closed_reason ) && 'approved' === $notification->data->object->closed_reason ) &&
			apply_filters( 'wc_stripe_webhook_review_change_order_status', true, $order, $notification )
		) {
			// If the status we stored before hold is an incomplete status, restore the status to processing/completed instead.
			$status_after_review = $this->get_stripe_order_status_before_hold( $order );
			if ( in_array( $status_after_review, apply_filters( 'woocommerce_valid_order_statuses_for_payment_complete', [ OrderStatus::ON_HOLD, OrderStatus::PENDING, OrderStatus::FAILED, OrderStatus::CANCELLED ], $order ), true ) ) {
				$status_after_review = apply_filters( 'woocommerce_payment_complete_order_status', $order->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED, $order->get_id(), $order );
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

			if ( in_array( strtoupper( $notification->data->object->currency ), WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES, true ) ) {
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

			if ( in_array( strtoupper( $notification->data->object->currency ), WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES, true ) ) {
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
			WC_Stripe_Logger::warning( 'Could not find order via intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status(
			apply_filters(
				'wc_stripe_allowed_payment_processing_statuses',
				[ OrderStatus::PENDING, OrderStatus::FAILED ],
				$order
			)
		) ) {
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( $order_helper->lock_order_payment( $order ) ) {
			return;
		}

		$order_id           = $order->get_id();
		$payment_type_meta  = $order_helper->get_stripe_upe_payment_type( $order );
		$is_voucher_payment = in_array( $payment_type_meta, WC_Stripe_Payment_Methods::VOUCHER_PAYMENT_METHODS, true );
		$is_wallet_payment  = in_array( $payment_type_meta, WC_Stripe_Payment_Methods::WALLET_PAYMENT_METHODS, true );
		$is_blik_payment    = WC_Stripe_Payment_Methods::BLIK === $payment_type_meta;

		switch ( $notification->type ) {
			// Asynchronous payment methods such as bank debits will only provide a charge ID at `payment_intent.processing`, once the required actions are taken by the customer.
			// We need to update the order transaction ID, so that the `payment_intent.succeeded` webhook is able to process the order.
			case 'payment_intent.processing':
				$charge = $this->get_latest_charge_from_intent( $intent );
				if ( $charge ) {
					$order->set_transaction_id( $charge->id );
					/* translators: transaction id */
					$order->update_status( OrderStatus::ON_HOLD, sprintf( __( 'Stripe charge awaiting payment: %s.', 'woocommerce-gateway-stripe' ), $charge->id ) );
				}
				break;
			case 'payment_intent.requires_action':
				do_action( 'wc_gateway_stripe_process_payment_intent_requires_action', $order, $notification->data->object );

				if ( $is_voucher_payment ) {
					$order->update_status( OrderStatus::ON_HOLD, __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
					wc_reduce_stock_levels( $order_id );
				}
				break;
			case 'payment_intent.succeeded':
			case 'payment_intent.amount_capturable_updated':
				WC_Stripe_Logger::debug( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );

				$process_webhook_async = apply_filters( 'wc_stripe_process_payment_intent_webhook_async', true, $order, $intent, $notification );
				$is_awaiting_action    = $order_helper->get_stripe_upe_waiting_for_redirect( $order ) ?? false;

				// Process the webhook now if it's for a voucher, wallet, or BLIK payment, or if filtered to process immediately and order is not awaiting action.
				if ( $is_voucher_payment || $is_wallet_payment || $is_blik_payment || ( ! $process_webhook_async && ! $is_awaiting_action ) ) {
					$charge = $this->get_latest_charge_from_intent( $intent );

					do_action_deprecated(
						'wc_gateway_stripe_process_payment',
						[ $charge, $order ],
						'9.7.0',
						'wc_gateway_stripe_process_payment_charge',
						'The wc_gateway_stripe_process_payment action is deprecated. Use wc_gateway_stripe_process_payment_charge instead.'
					);

					$charge->is_webhook_response = true;
					$this->process_response( $charge, $order );

					$this->run_webhook_received_action( (string) $notification->type, $notification );
				} else {
					WC_Stripe_Logger::debug( "Processing $notification->type ($intent->id) asynchronously for order $order_id." );

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
					$order->update_status( OrderStatus::FAILED, __( 'Payment not completed in time', 'woocommerce-gateway-stripe' ) );
					wc_increase_stock_levels( $order_id );
					break;
				}

				$error_message = $intent->last_payment_error->message ?? '';

				/* translators: 1) The error message that was received from Stripe. */
				$message = sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'woocommerce-gateway-stripe' ), $error_message );

				$status_update = [];
				if ( ! $order_helper->is_stripe_status_final( $order ) ) {
					$status_update['from'] = $order->get_status();
					$status_update['to']   = OrderStatus::FAILED;
					$order->update_status( OrderStatus::FAILED, $message );
				} else {
					$order->add_order_note( $message );
				}

				do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );

				$this->send_failed_order_email( $order_id, $status_update );
				break;
		}

		$order_helper->unlock_order_payment( $order );
	}

	public function process_setup_intent( $notification ) {
		$intent = $notification->data->object;
		$order  = WC_Stripe_Helper::get_order_by_setup_intent_id( $intent->id );

		if ( ! $order ) {
			WC_Stripe_Logger::warning( 'Could not find order via setup intent ID: ' . $intent->id );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$allowed_payment_processing_statuses = [ OrderStatus::PENDING, OrderStatus::FAILED ];

		$allowed_payment_processing_statuses = apply_filters_deprecated(
			'wc_gateway_stripe_allowed_payment_processing_statuses',
			[ $allowed_payment_processing_statuses ],
			'9.7.0',
			'wc_stripe_allowed_payment_processing_statuses',
			'The wc_gateway_stripe_allowed_payment_processing_statuses filter is deprecated since WooCommerce Stripe Gateway 9.7.0, and will be removed in a future version.'
		);

		/**
		 * Filters the valid order statuses for payment processing.
		 *
		 * @since 9.7.0
		 *
		 * @param array $allowed_payment_processing_statuses The allowed payment processing statuses.
		 * @param WC_Order $order The order object.
		 */
		$allowed_payment_processing_statuses = apply_filters(
			'wc_stripe_allowed_payment_processing_statuses',
			$allowed_payment_processing_statuses,
			$order
		);

		if ( ! $order->has_status( $allowed_payment_processing_statuses ) ) {
			return;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( $order_helper->lock_order_payment( $order ) ) {
			return;
		}

		$order_id = $order->get_id();
		if ( 'setup_intent.succeeded' === $notification->type ) {
			WC_Stripe_Logger::info( "Stripe SetupIntent $intent->id succeeded for order $order_id" );
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
			if ( ! $order_helper->is_stripe_status_final( $order ) ) {
				$status_update['from'] = $order->get_status();
				$status_update['to']   = OrderStatus::FAILED;
				$order->update_status( OrderStatus::FAILED, $message );
			} else {
				$order->add_order_note( $message );
			}

			$this->send_failed_order_email( $order_id, $status_update );
		}

		$order_helper->unlock_order_payment( $order );
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
				'type'         => $webhook_notification->type,
				'data'         => $additional_data,
				'notification' => $webhook_notification,
			]
		);
	}

	/**
	 * Restores a webhook notification to an object after Action Scheduler deserialization.
	 *
	 * Action Scheduler in defer_webhook_processing() serializes job args; stdClass becomes a nested array. process_deferred_webhook() expects Stripe's
	 * object-shaped payload.
	 *
	 * @param array|object $notification Raw notification from the scheduled job.
	 * @return object      The normalized notification object.
	 * @throws Exception When the payload cannot be normalized.
	 */
	private function normalize_deferred_webhook_notification_to_object( $notification ) {
		if ( is_object( $notification ) ) {
			return $notification;
		}

		if ( is_array( $notification ) ) {
			$json = wp_json_encode( $notification );
			if ( false === $json ) {
				throw new Exception( 'Failed to encode deferred webhook notification for object restoration.' );
			}

			$object = json_decode( $json );
			if ( ! is_object( $object ) ) {
				throw new Exception( 'Failed to restore deferred webhook notification to an object.' );
			}

			return $object;
		}

		throw new Exception( 'Deferred webhook notification data is missing or invalid.' );
	}

	/**
	 * Processes a deferred webhook event.
	 *
	 * Deferred webhooks are scheduled by @see defer_webhook_processing().
	 *
	 * @param string          $webhook_type    The webhook event name/type.
	 * @param array           $additional_data Additional data passed to the scheduled job.
	 * @param array|object|null $notification  The webhook notification payload (arrays after AS deserialization).
	 */
	public function process_deferred_webhook( $webhook_type, $additional_data, $notification = null ) {
		try {
			if ( null === $notification ) {
				throw new Exception( 'Missing deferred webhook notification.' );
			}

			$notification = $this->normalize_deferred_webhook_notification_to_object( $notification );

			switch ( $webhook_type ) {
				case 'payment_intent.succeeded':
				case 'payment_intent.amount_capturable_updated':
					$order     = isset( $additional_data['order_id'] ) ? wc_get_order( $additional_data['order_id'] ) : null;
					$intent_id = $additional_data['intent_id'] ?? '';

					if ( ! $order instanceof \WC_Order ) {
						throw new Exception( "Missing required data. 'order_id' is invalid or not found for the deferred '{$webhook_type}' event." );
					}

					// Set the order being processed for the `wc_stripe_webhook_received` action later.
					$this->resolved_order = $order;

					if ( empty( $intent_id ) ) {
						throw new Exception( "Missing required data. 'intent_id' is missing for the deferred '{$webhook_type}' event." );
					}

					// Check if the order is still in a valid state to process the webhook.
					if ( ! $order->has_status( apply_filters( 'wc_stripe_allowed_payment_processing_statuses', [ OrderStatus::PENDING, OrderStatus::FAILED ], $order ) ) ) {
						WC_Stripe_Logger::debug( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - payment already complete." );
						return;
					}

					$this->handle_deferred_payment_intent_succeeded( $order, $intent_id );
					break;
				case 'checkout.session.completed':
				case 'checkout.session.async_payment_succeeded':
					$this->handle_checkout_session_success( $notification );
					break;
				case 'checkout.session.expired':
				case 'checkout.session.async_payment_failed':
					$this->handle_checkout_session_failure( $notification );
					break;
				default:
					throw new Exception( "Unsupported webhook type: {$webhook_type}" );
					break;
			}

			$this->run_webhook_received_action( (string) $webhook_type, $notification );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Error processing deferred webhook.',
				[
					'webhook_type'    => $webhook_type,
					'additional_data' => $additional_data,
					'error_message'   => $e->getMessage(),
				]
			);

			// This will be caught by Action Scheduler and logged as an error.
			throw $e;
		}
	}

	/**
	 * Processes the checkout session metadata update event to store additional metadata on the checkout session object.
	 *
	 * @param string $checkout_session_id The checkout session ID.
	 * @param array $metadata The metadata from the checkout session.
	 * @return void
	 */
	public function process_checkout_session_metadata( string $checkout_session_id, array $metadata ): void {
		try {
			$response = WC_Stripe_API::request( [ 'metadata' => $metadata ], 'checkout/sessions/' . $checkout_session_id, 'POST' );
			if ( ! empty( $response->error->message ) ) {
				throw new WC_Stripe_Exception( $response->error->message );
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Failed to update checkout session metadata: ' . $e->getMessage() );

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
			WC_Stripe_Logger::debug( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - intent ID stored on order ({$intent->id}) doesn't match." );
			return;
		}

		$charge = $this->get_latest_charge_from_intent( $intent );

		if ( ! $charge ) {
			WC_Stripe_Logger::debug( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - no charge found." );
			return;
		}

		WC_Stripe_Logger::info( "Processing Stripe PaymentIntent {$intent_id} for order {$order->get_id()} via deferred webhook." );

		do_action_deprecated(
			'wc_gateway_stripe_process_payment',
			[ $charge, $order ],
			'9.7.0',
			'wc_gateway_stripe_process_payment_charge',
			'The wc_gateway_stripe_process_payment action is deprecated. Use wc_gateway_stripe_process_payment_charge instead.'
		);

		$charge->is_webhook_response = true;
		$this->process_response( $charge, $order );
	}

	/**
	 * Process webhook account updated event.
	 * This is triggered when the account details are updated in Stripe's end.
	 * We want to clear the cached account data to fetch fresh data on next request.
	 *
	 * @param object $notification The notification from Stripe
	 */
	public function process_account_updated( $notification ) {
		WC_Stripe::get_instance()->account->clear_cache();
		WC_Stripe_Logger::debug( 'Cleared account cache after receiving account.updated webhook.' );
	}

	/**
	 * Processes the checkout session completed event.
	 *
	 * This webhook exists for both standard post-payment checkout
	 * sessions, as well as agentic checkout sessions.
	 *
	 * @param object $notification The notification from Stripe
	 * @return void
	 *
	 * @deprecated 10.6.0 Use process_checkout_session_success instead.
	 */
	public function process_checkout_session( object $notification ): void {
		wc_deprecated_function( __METHOD__, '10.6.0', 'process_checkout_session_success' );
		$this->process_checkout_session_success( $notification );
	}

	/**
	 * Processes the checkout session success events.
	 * This includes:
	 * - checkout.session.completed event; Fires when a Stripe Checkout session is completed.
	 * - checkout.session.async_payment_succeeded event; Fires when an asynchronous payment method on a Stripe Checkout session succeeds.
	 *
	 * This webhook exists for both standard post-payment checkout
	 * sessions, as well as agentic checkout sessions.
	 *
	 * @param object $notification The notification from Stripe
	 * @return bool True if the event was deferred for async processing, false if handled inline.
	 */
	public function process_checkout_session_success( object $notification ): bool {
		$checkout_session = $notification->data->object;

		if ( ! isset( $checkout_session->id ) ) {
			WC_Stripe_Logger::error( 'Checkout session ID is missing from the event data.' );
			return false;
		}

		$session_id = $checkout_session->id;

		// Look for an order. If order exists, process the webhook immediately.
		$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $session_id );

		// If order does not exist, defer the webhook processing.
		// This is either an agentic hook or a webhook arrived before the order metadata was stored.
		if ( ! $order instanceof \WC_Order ) {
			WC_Stripe_Logger::debug( "Deferring processing of {$notification->type} ($session_id) asynchronously." );

			$this->defer_webhook_processing(
				$notification,
				[
					'session_id' => $session_id,
				]
			);
			return true;
		}

		// If order exists, process the webhook immediately.
		$this->handle_checkout_session_success( $notification );
		return false;
	}

	/**
	 * Handles a deferred checkout session success event.
	 *
	 * @param object        $notification The Stripe notification containing the checkout session data.
	 * @return void
	 */
	protected function handle_checkout_session_success( object $notification ): void {
		$checkout_session = $notification->data->object;

		$session_id = $checkout_session->id;

		// Refresh the cached checkout session with the latest data from the webhook so that
		// subsequent reads (e.g. presentment details on the order page) reflect the final state.
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $session_id, $checkout_session, HOUR_IN_SECONDS );

		// Acquire a lock to prevent duplicate order creation from concurrent agentic sessions.
		$lock_key = 'checkout_session_lock_' . $session_id;
		if ( null !== WC_Stripe_Database_Cache::get( $lock_key ) ) {
			WC_Stripe_Logger::info(
				'Checkout session is already being processed.',
				[ 'session_id' => $session_id ]
			);
			return;
		}
		WC_Stripe_Database_Cache::set( $lock_key, time(), 5 * MINUTE_IN_SECONDS );

		// Look for an order. If one does not exists, this is probably an agentic hook.
		$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $checkout_session->id );
		if ( ! $order instanceof \WC_Order ) {
			try {
				$this->handle_agentic_checkout_session( $notification );
			} finally {
				WC_Stripe_Database_Cache::delete( $lock_key );
				return;
			}
		} else {
			WC_Stripe_Database_Cache::delete( $lock_key );
		}

		/**
		 * Filters the valid order statuses for payment processing.
		 *
		 * @since 9.7.0
		 *
		 * @param array $allowed_payment_processing_statuses The allowed payment processing statuses.
		 * @param WC_Order $order The order object.
		 */
		$allowed_payment_processing_statuses = apply_filters(
			'wc_stripe_allowed_payment_processing_statuses',
			[ OrderStatus::PENDING, OrderStatus::FAILED, OrderStatus::ON_HOLD ],
			$order
		);

		if ( ! $order->has_status( $allowed_payment_processing_statuses ) ) {
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Lock the order
		if ( $order_helper->lock_order_payment( $order ) ) {
			return;
		}

		try {

			$intent_id = isset( $checkout_session->payment_intent ) ? $checkout_session->payment_intent : null;

			// Store the payment intent ID on the order.
			if ( ! empty( $intent_id ) ) {
				$order_helper->add_payment_intent_to_order( $intent_id, $order );
			}

			// Add presentment details if available.
			$presentment_details = $checkout_session->presentment_details ?? null;
			if ( $presentment_details && isset( $presentment_details->presentment_currency, $presentment_details->presentment_amount ) ) {
				$order_helper->update_stripe_presentment_currency( $order, $presentment_details->presentment_currency );
				$order_helper->update_stripe_presentment_amount( $order, $presentment_details->presentment_amount );

				$amount = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount(
					$presentment_details->presentment_amount,
					$presentment_details->presentment_currency
				);

				$order->add_order_note(
					sprintf(
						/* translators: 1) presentment currency 2) presentment amount */
						__( 'Local currency purchase via Adaptive Pricing. Amount paid was: %1$s %2$s', 'woocommerce-gateway-stripe' ),
						strtoupper( $presentment_details->presentment_currency ),
						$amount
					)
				);
			}

			$intent = $this->get_intent_from_order( $order );

			if ( ! $intent ) {
				WC_Stripe_Logger::error( 'Could not find intent for order: ' . $order->get_id() );
				return;
			}

			$payment_method_id = is_object( $intent->payment_method ) ? $intent->payment_method->id : $intent->payment_method;

			// Update the order with the payment method ID if it's not already set.
			if ( ! $order_helper->get_stripe_source_id( $order ) && ! empty( $payment_method_id ) ) {
				$order_helper->update_stripe_source_id( $order, $payment_method_id );
			}

			// Save payment method to store if the customer requested it during checkout.
			if ( $order_helper->get_should_save_stripe_payment_method( $order ) && ! empty( $payment_method_id ) ) {
				$upe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

				$payment_method_object = is_object( $intent->payment_method ) ? $intent->payment_method : WC_Stripe_API::retrieve( 'payment_methods/' . $payment_method_id );
				if ( ! is_wp_error( $payment_method_object ) && empty( $payment_method_object->error ) && ! empty( $payment_method_object ) ) {
					$upe_gateway->handle_saving_payment_method( $order, $payment_method_object, $payment_method_object->type );

					// Clear the flag so it does not run again on webhook retries.
					$order_helper->delete_should_save_stripe_payment_method( $order );
				}
			}

			// Set the payment method title on the order based on the actual payment method used.
			$upe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
			if ( $upe_gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
				$payment_method_type = is_object( $intent->payment_method ) && isset( $intent->payment_method->type ) ? $intent->payment_method->type : '';
				if ( ! empty( $payment_method_type ) ) {
					$upe_gateway->set_payment_method_title_for_order( $order, $payment_method_type, $intent->payment_method ?? false );
				}
			}

			$order->save();

			$charge = $this->get_latest_charge_from_intent( $intent );

			$charge->is_webhook_response = true;
			$this->process_response( $charge, $order );

			// Schedule a job to store the remaining metadata to the checkout session.
			$this->action_scheduler_service->schedule_job(
				time() + $this->process_checkout_session_metadata_delay,
				$this->process_checkout_session_metadata_action,
				[
					'checkout_session_id' => $checkout_session->id,
					'metadata'            => [
						'order_id'   => $order->get_order_number(),
						'order_key'  => $order->get_order_key(),
						'signature'  => $this->get_order_signature( $order ),
						'tax_amount' => WC_Stripe_Helper::get_stripe_amount( $order->get_total_tax(), strtolower( $order->get_currency() ) ),
					],
				]
			);
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Error processing checkout session for order: ' . $order->get_id(),
				[ 'error_message' => $e->getMessage() ]
			);

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, $e );

			$status_update = [];
			if ( ! $order_helper->is_stripe_status_final( $order ) ) {
				/* translators: 1) Error message from the exception */
				$message               = sprintf( __( 'Checkout session could not be processed. %s', 'woocommerce-gateway-stripe' ), $e->getMessage() );
				$status_update['from'] = $order->get_status();
				$status_update['to']   = OrderStatus::FAILED;
				$order->update_status( OrderStatus::FAILED, $message );
			} else {
				$order->add_order_note(
					sprintf(
						/* translators: 1) Error message from the exception */
						__( 'Checkout session processing error: %s', 'woocommerce-gateway-stripe' ),
						$e->getMessage()
					)
				);
			}

			$this->send_failed_order_email( $order->get_id(), $status_update );
		} finally {
			// Unlock the order
			$order_helper->unlock_order_payment( $order );
		}
	}

	/**
	 * Processes checkout session failure events.
	 * This includes:
	 * - checkout.session.expired event; Fires when a Stripe Checkout session expires before the customer completes payment.
	 * - checkout.session.async_payment_failed event; Fires when an asynchronous payment method on a Stripe Checkout session fails.
	 * Marks the associated WooCommerce order as failed.
	 *
	 * @param object $notification The Stripe notification containing the checkout session data.
	 */
	public function process_checkout_session_failure( object $notification ): bool {
		$checkout_session = $notification->data->object;

		if ( ! isset( $checkout_session->id ) ) {
			WC_Stripe_Logger::debug( 'Checkout session ID is missing from the event data.' );
			return false;
		}

		$session_id = $checkout_session->id;

		// Look for an order. If order exists, process the webhook immediately.
		$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $session_id );

		// If order does not exist, defer the webhook processing.
		// This might happen if a webhook arrived before the order metadata was stored.
		if ( ! $order instanceof \WC_Order ) {
			WC_Stripe_Logger::debug( "Deferring processing of {$notification->type} ($session_id) asynchronously." );

			$this->defer_webhook_processing(
				$notification,
				[
					'session_id' => $session_id,
				]
			);
			return true;
		}

		// If order exists, process the webhook immediately.
		$this->handle_checkout_session_failure( $notification );
		return false;
	}

	/**
	 * Handles a deferred checkout session failure event.
	 *
	 * @param object $notification The Stripe notification containing the checkout session data.
	 * @return void
	 */
	protected function handle_checkout_session_failure( object $notification ): void {
		$checkout_session = $notification->data->object;

		$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $checkout_session->id );

		if ( ! $order instanceof \WC_Order ) {
			WC_Stripe_Logger::debug( 'Could not find order via checkout session ID: ' . $checkout_session->id );
			return;
		}

		$this->resolved_order = $order;

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( $order_helper->lock_order_payment( $order ) ) {
			return;
		}

		try {
			if ( $order_helper->is_stripe_status_final( $order ) ) {
				return;
			}

			if ( $order->has_status( [ OrderStatus::PROCESSING, OrderStatus::COMPLETED, OrderStatus::FAILED ] ) ) {
				return;
			}

			$message = 'checkout.session.expired' === $notification->type ? __( 'The checkout session has expired.', 'woocommerce-gateway-stripe' ) : __( 'The async payment for this checkout session has failed.', 'woocommerce-gateway-stripe' );

			$status_update         = [];
			$status_update['from'] = $order->get_status();
			$status_update['to']   = OrderStatus::FAILED;
			$order->update_status( OrderStatus::FAILED, $message );

			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification );

			$this->send_failed_order_email( $order->get_id(), $status_update );
		} finally {
			$order_helper->unlock_order_payment( $order );
		}
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

		$this->resolved_order = null;

		switch ( $notification->type ) {
			case 'account.updated':
				$this->process_account_updated( $notification );
				break;

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

			case 'payment_intent.processing':
			case 'payment_intent.succeeded':
			case 'payment_intent.payment_failed':
			case 'payment_intent.amount_capturable_updated':
			case 'payment_intent.requires_action':
				$this->process_payment_intent( $notification );
				break;

			case 'setup_intent.succeeded':
			case 'setup_intent.setup_failed':
				$this->process_setup_intent( $notification );
				break;
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
				$checkout_session_deferred = $this->process_checkout_session_success( $notification );
				break;
			case 'checkout.session.expired':
			case 'checkout.session.async_payment_failed':
				$checkout_session_deferred = $this->process_checkout_session_failure( $notification );
				break;
		}

		// payment_intent.succeeded and payment_intent.amount_capturable_updated are always deferred via
		// process_payment_intent(). checkout.session.* events may be handled inline or deferred depending
		// on whether the order exists at webhook arrival time. Only skip the action when actually deferred.
		$always_deferred_types = [
			'payment_intent.succeeded',
			'payment_intent.amount_capturable_updated',
		];
		if ( ( $checkout_session_deferred ?? false ) || in_array( $notification->type, $always_deferred_types, true ) ) {
			return;
		}

		$this->run_webhook_received_action( $notification->type, $notification );
	}

	/**
	 * Helper function to run the `wc_stripe_webhook_received` action consistently.
	 *
	 * @param string $webhook_type The type of webhook that was processed.
	 * @param object $notification The webhook data sent from Stripe.
	 */
	private function run_webhook_received_action( string $webhook_type, object $notification ): void {
		try {
			/**
			 * Fires after a webhook has been processed, but before we respond to Stripe.
			 * This allows for custom processing of the webhook after it has been processed.
			 * Note that the $order parameter may be null in various cases, especially when processing
			 * webhooks unrelated to orders, such as account updates.
			 *
			 * @since 9.8.0
			 *
			 * @param string $webhook_type The type of webhook that was processed.
			 * @param object $notification The webhook data sent from Stripe.
			 * @param WC_Order|null $order The order being processed by the webhook.
			 */
			do_action( 'wc_stripe_webhook_received', $webhook_type, $notification, $this->resolved_order );
		} catch ( Throwable $e ) {
			WC_Stripe_Logger::error( 'Error in wc_stripe_webhook_received action: ' . $e->getMessage(), [ 'error' => $e ] );
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

					// Ensure we have a valid order, not a refund or other object.
					if ( ! $order instanceof WC_Order ) {
						return false;
					}

					$intent_id = WC_Stripe_Order_Helper::get_instance()->get_intent_id_from_order( $order );

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
		if ( ! empty( $intent->charges ) && is_array( $intent->charges ) ) {
			$charge   = $intent->charges[0] ?? [];
			$order_id = $charge->metadata->order_id ?? null;
			return $order_id ? wc_get_order( $order_id ) : false;
		}

		// Fall back to finding the order via the intent ID.
		return WC_Stripe_Helper::get_order_by_intent_id( $intent->id );
	}

	/**
	 * Get the payment method type from the charge object.
	 * https://docs.stripe.com/api/charges/object
	 *
	 * @param object $charge The charge object from Stripe
	 * @return string|null The payment method type, or null if not found
	 */
	private function get_payment_method_type_from_charge( $charge ) {
		// We don't expect $charge->source to be set,
		// but we keep it here to ensure backwards compatibility.
		if ( isset( $charge->source->type ) ) {
			return $charge->source->type;
		}

		if ( isset( $charge->payment_method_details->type ) ) {
			return $charge->payment_method_details->type;
		}

		return null;
	}

	/**
	 * Processes an agentic hook.
	 *
	 * @since 10.6.0
	 * @param stdClass $event The webhook event from Stripe.
	 * @return void
	 */
	private function process_agentic_hook( stdClass $event ) {
		$event_type = $event->type ?? 'No event type found';

		try {
			switch ( $event_type ) {
				case 'v1.delegated_checkout.customize_checkout':
					$response = $this->process_agentic_customization_hook( $event );
					break;
				case 'v1.delegated_checkout.finalize_checkout':
					$response = $this->process_agentic_finalize_checkout_hook( $event );
					break;
				default:
					WC_Stripe_Logger::error( 'Unsupported agentic hook type: ' . $event_type );
					status_header( 400 );
					exit;
			}

			if ( ! headers_sent() ) {
				header( 'Content-Type: application/json' );
			}
			status_header( 200 );
			echo wp_json_encode( $response );
		} catch ( Throwable $e ) {
			WC_Stripe_Logger::error(
				'Agentic hook failed.',
				[
					'error' => $e->getMessage(),
					'event' => $event,
				]
			);

			status_header( 400 );
		}
		exit;
	}

	/**
	 * Handle the Agentic Checkout customization hook.
	 *
	 * This parameter is expected to generate both an HTTP status code and a JSON response.
	 *
	 * @since 10.6.0
	 * @param stdClass $event The webhook event from Stripe.
	 * @return array
	 * @throws Exception
	 */
	private function process_agentic_customization_hook( stdClass $event ): array {
		$event               = new WC_Stripe_Agentic_Customize_Checkout_Event( $event );
		$tax_calculator      = new WC_Stripe_Agentic_Commerce_Tax_Calculator();
		$shipping_calculator = new WC_Stripe_Agentic_Shipping_Calculator();

		$line_items_with_tax = $tax_calculator->calculate(
			$event,
			$tax_calculator->extract_line_items_from_customization_hook( $event )
		);

		$shipping_options = $shipping_calculator->calculate( $event, $event->get_currency() );

		return array_merge( $line_items_with_tax, $shipping_options );
	}

	/**
	 * Handle the Agentic Checkout finalize (manual approval) hook.
	 *
	 * @since 10.6.0
	 * @param stdClass $event The webhook event from Stripe.
	 * @return array
	 * @throws Exception When product resolution fails.
	 */
	private function process_agentic_finalize_checkout_hook( stdClass $event ): array {
		$event           = new WC_Stripe_Agentic_Customize_Checkout_Event( $event );
		$manual_approval = new WC_Stripe_Agentic_Commerce_Manual_Approval();

		return $manual_approval->validate( $event );
	}

	/**
	 * Processes an agentic checkout session after the concurrency lock is acquired.
	 *
	 * @since 10.6.0
	 * @param object $notification The webhook notification from Stripe.
	 */
	private function handle_agentic_checkout_session( $notification ): void {
		$checkout_session = $notification->data->object;

		if ( ! WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			WC_Stripe_Logger::error( 'Agentic commerce is disabled, skipping agentic checkout session: ' . $checkout_session->id );
			return;
		}

		WC_Stripe_Logger::info(
			'Webhook checkout.session.completed received.',
			[
				'session_id'        => $notification->data->object->id ?? 'unknown',
				'payment_intent_id' => $notification->data->object->payment_intent ?? 'unknown',
			]
		);

		// Temporarily override the API version to get the right fields.
		$override_version = function ( $headers ) {
			$headers['Stripe-Version'] = WC_Stripe_API::AGENTIC_COMMERCE_API_VERSION;
			return $headers;
		};
		add_filter( 'wc_stripe_request_headers', $override_version );

		try {
			$url         = $this->build_checkout_session_retrieve_url(
				$notification->data->object->id,
				WC_Stripe_Agentic_Checkout_Session::get_fields_to_expand()
			);
			$raw_session = WC_Stripe_API::retrieve( $url );

			if ( is_wp_error( $raw_session ) || ! is_object( $raw_session ) ) {
				WC_Stripe_Logger::error(
					'Failed to retrieve checkout session with expand params.',
					[
						'url'   => $url,
						'error' => is_wp_error( $raw_session ) ? $raw_session->get_error_message() : 'Unexpected response from Stripe API.',
					]
				);
				return;
			}

			assert( $raw_session instanceof stdClass );
			$session = new WC_Stripe_Agentic_Checkout_Session( $raw_session );

			if ( ! $session->is_agentic() ) {
				WC_Stripe_Logger::info(
					'Checkout session is not agentic, skipping agentic processing: ' . $session->get_id()
				);
				return;
			}

			$payment_intent_id = $session->get_payment_intent_id();
			if ( null === $payment_intent_id || empty( $payment_intent_id ) ) {
				WC_Stripe_Logger::error(
					'Checkout session is missing the payment intent id.',
					[
						'session_id' => $session->get_id(),
					]
				);
				return;
			}

			try {
				$order_mapper         = new WC_Stripe_Agentic_Commerce_Order_Mapper();
				$order                = $order_mapper->create_order_from_checkout_session( $session );
				$this->resolved_order = $order;

				WC_Stripe_Logger::info(
					'Agentic order created from checkout session.',
					[
						'session_id' => $session->get_id(),
						'order_id'   => $order->get_id(),
					]
				);

				/**
				 * Fires after an agentic commerce order is created from a checkout session.
				 *
				 * @since 10.6.0
				 * @param WC_Order                           $order   The created order.
				 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
				 */
				do_action( 'wc_stripe_agentic_order_created', $order, $session );
			} catch ( Exception $e ) {
				WC_Stripe_Logger::error(
					'Failed to create agentic order from checkout session.',
					[
						'session_id' => $session->get_id(),
						'error'      => $e->getMessage(),
					]
				);

				/**
				 * Fires when agentic commerce order creation fails.
				 *
				 * @since 10.6.0
				 * @param Exception                          $e       The exception that was thrown.
				 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
				 */
				do_action( 'wc_stripe_agentic_order_creation_failed', $e, $session );
			}
		} finally {
			remove_filter( 'wc_stripe_request_headers', $override_version );
		}
	}

	/**
	 * Builds the Stripe API URL for retrieving a checkout session with expanded fields.
	 *
	 * Expands the payment intent's agent_details (to detect agentic sessions)
	 * and any additional fields required by the checkout session wrapper.
	 *
	 * @since 10.6.0
	 * @param string   $session_id       The Stripe checkout session ID.
	 * @param string[] $additional_expand Additional fields to expand beyond payment_intent.agent_details.
	 * @return string The API URL with expand query parameters.
	 *
	 * @see https://docs.stripe.com/agentic-commerce/enable-in-context-selling-on-ai-agents?order-monitoring=webhooks#checkout-session-field-reference
	 */
	private function build_checkout_session_retrieve_url( string $session_id, array $additional_expand = [] ): string {
		$url    = 'checkout/sessions/' . rawurlencode( $session_id );
		$expand = array_merge( [ 'payment_intent.agent_details' ], $additional_expand );

		$params = [];
		foreach ( $expand as $field ) {
			$params[] = 'expand[]=' . rawurlencode( $field );
		}

		return $url . '?' . implode( '&', $params );
	}
}
