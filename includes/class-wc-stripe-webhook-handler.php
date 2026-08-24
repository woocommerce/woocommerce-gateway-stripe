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
	 * Cache key prefix marking an agentic checkout session as owned by this site.
	 *
	 * Stripe allows a single agentic-commerce endpoint per account, so only the owning site
	 * receives the synchronous customize/finalize hooks and records a claim under this prefix.
	 *
	 * @var string
	 */
	protected const AGENTIC_SESSION_CLAIM_CACHE_PREFIX = 'agentic_session_claim_';

	/**
	 * How long an agentic session claim is retained, long enough to cover the gap between the
	 * sync hook and the broadcast checkout.session.completed event for a pending checkout.
	 *
	 * @var int
	 */
	protected const AGENTIC_SESSION_CLAIM_TTL = DAY_IN_SECONDS;

	/**
	 * Order meta flag recording that a late Stripe payment on a cancelled order was already
	 * refunded/voided. Deferred webhooks are retried by Action Scheduler, and refunds are not
	 * idempotent, so this guards against issuing a second refund on a retry.
	 *
	 * @var string
	 */
	protected const META_REFUNDED_AFTER_CANCELLATION = '_stripe_refunded_after_cancellation';

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
	 * How long to wait before retrying a webhook that lost the order-payment lock race.
	 *
	 * The order-received redirect handler holds the lock only across a single Stripe API
	 * call (~1s), so a short backoff settles the order quickly instead of leaving it pending
	 * for the full deferred delay. Kept well above the typical hold to avoid a busy re-queue loop.
	 *
	 * @var int
	 */
	protected $locked_order_retry_delay = 10;

	/**
	 * The Action Scheduler hook to use when retrying a webhook.
	 *
	 * @var string
	 */
	protected $deferred_webhook_action = 'wc_stripe_deferred_webhook';

	/**
	 * How long to wait before updating the payment intent description and metadata after a webhook.
	 *
	 * @var int
	 */
	protected $process_payment_intent_metadata_delay = 2 * MINUTE_IN_SECONDS;

	/**
	 * The Action Scheduler hook to use when updating the payment intent description and metadata after a webhook.
	 *
	 * @var string
	 */
	protected $process_payment_intent_metadata_action = 'wc_stripe_process_payment_intent_metadata';

	/**
	 * The legacy Action Scheduler hook that updated checkout session metadata after a webhook.
	 * Kept registered so jobs queued before the switch to {@see $process_payment_intent_metadata_action} still run to completion.
	 *
	 * @deprecated 10.8.0 Replaced by {@see $process_payment_intent_metadata_action}.
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
		add_action( $this->process_payment_intent_metadata_action, [ $this, 'process_payment_intent_metadata' ], 10, 2 );
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

		$is_agentic_hook = 0 === strpos( $event_type, 'v1.delegated_checkout.' );

		$secret = $is_agentic_hook
			? (string) get_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION, '' )
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

		// Ignore events that belong to a different Stripe account than the one this store is connected to.
		// Acting on them could update the wrong orders or trigger mismatched payment/charge lookups.
		if ( ! $this->event_belongs_to_connected_account( $event ) ) {
			WC_Stripe_Logger::error(
				'Webhook ignored: the event\'s Stripe account does not match the connected account.',
				[
					'event_id'          => $event->id ?? null,
					'event_type'        => $event_type,
					'event_account'     => $this->get_event_account_id( $event ),
					'connected_account' => $this->get_connected_account_id(),
				]
			);

			// Acknowledge the event so Stripe does not keep retrying delivery of an event meant for another account.
			// @see https://docs.stripe.com/webhooks#acknowledge-events-immediately
			status_header( 200 );
			exit;
		}

		if ( $is_agentic_hook ) {
			$this->process_agentic_hook( $event );
			return;
		}

		WC_Stripe_Webhook_State::set_pending_webhooks_count( $event->pending_webhooks ?? 0 );

		WC_Stripe_Logger::debug( 'Webhook received (' . $event_type . ')', [ 'event' => $event ] );
		$this->process_webhook( $request_body );
		WC_Stripe_Webhook_State::set_last_webhook_success_at( $event->created );
		status_header( 200 );
		exit;
	}

	/**
	 * Whether the event's Stripe account matches the connected account.
	 *
	 * Fails open when the event carries no account or the connected account is unknown.
	 *
	 * @param object $event The decoded webhook event.
	 * @return bool True when the event may be processed, false when it must be skipped.
	 */
	protected function event_belongs_to_connected_account( $event ): bool {
		$event_account = $this->get_event_account_id( $event );

		// No account context on the payload: cannot verify, so allow processing to continue.
		if ( '' === $event_account ) {
			return true;
		}

		$connected_account = $this->get_connected_account_id();

		// Connected account is unknown: avoid dropping legitimate events.
		if ( '' === $connected_account ) {
			return true;
		}

		return $event_account === $connected_account;
	}

	/**
	 * The Stripe account an event originated from.
	 *
	 * Connect events expose it as `account`; agentic delegated-checkout events use `context`.
	 *
	 * @param object $event The decoded webhook event.
	 * @return string Account ID (e.g. `acct_123`), or an empty string when absent.
	 */
	protected function get_event_account_id( $event ): string {
		if ( ! is_object( $event ) ) {
			return '';
		}

		foreach ( [ 'account', 'context' ] as $field ) {
			if ( ! empty( $event->$field ) ) {
				return (string) $event->$field;
			}
		}

		return '';
	}

	/**
	 * The connected Stripe account ID for the active mode.
	 *
	 * @return string Account ID (e.g. `acct_123`), or an empty string when unknown.
	 */
	protected function get_connected_account_id(): string {
		$account_data = WC_Stripe::get_instance()->account->get_cached_account_data();

		return isset( $account_data['id'] ) ? (string) $account_data['id'] : '';
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

			/**
			 * Fires after a webhook payment is processed.
			 * Deprecated in favor of wc_gateway_stripe_process_payment_charge.
			 *
			 * @deprecated 9.7.0
			 * @param object   $response The response object.
			 * @param WC_Order $order    The order object.
			*/
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

			/**
			 * Fires after webhook payment processing fails.
			 *
			 * @param WC_Order                 $order        Order that failed webhook payment processing.
			 * @param object                   $notification Stripe webhook notification.
			 * @param WC_Stripe_Exception|null $e            When available, the exception raised during webhook processing.
			 */
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
				WC_Stripe_Helper::get_external_link_open_tag( $this->get_transaction_url( $order ) ),
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

		/**
		 * This action is documented in includes/class-wc-stripe-webhook-handler.php.
		 */
		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, null );

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

		/**
		 * Filters whether a dispute webhook should change the order status.
		 *
		 * @param bool     $change_status Whether the webhook should change the order status.
		 * @param WC_Order $order         Order associated with the dispute.
		 * @param object   $notification  Stripe webhook notification.
		 */
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
		$charge       = $notification->data->object;
		$order        = WC_Stripe_Helper::get_order_by_charge_id( $charge->id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( ! $order ) {
			// Detect an "unexpected charge": this captured charge isn't recorded on any order (the
			// lookup above found nothing), so recover the order from the charge's parent PaymentIntent
			// and flag it if the shopper has since settled that order through a different gateway.
			if ( ! empty( $charge->payment_intent ) ) {
				$intent_order = WC_Stripe_Helper::get_order_by_intent_id( (string) $charge->payment_intent );
				if ( $intent_order instanceof WC_Order
					&& $this->maybe_flag_unexpected_charge_on_order(
						$intent_order,
						(string) $charge->payment_intent,
						$charge,
						'charge.captured'
					)
				) {
					return;
				}
			}

			WC_Stripe_Logger::warning( 'Could not find order via charge ID: ' . $charge->id );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		if ( WC_Stripe_Helper::payment_method_allows_manual_capture( $order->get_payment_method() ) ) {
			$charge_id = $order->get_transaction_id();
			$captured  = $order_helper->is_stripe_charge_captured( $order );

			if ( $charge_id && ! $captured ) {
				$order_helper->sync_stripe_charge_captured( $order, $charge );

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

		$order = WC_Stripe_Helper::get_order_by_charge_id( $charge->id );

		// Detect an "unexpected charge": a Stripe charge that succeeds for an order the shopper has
		// since settled through a different gateway (e.g. they abandoned the Stripe flow after 3DS,
		// then paid the same order with another method). The order never stored this charge as its
		// transaction_id, so the lookup above misses it and we recover the order from the charge's
		// parent PaymentIntent.
		if ( ! $order && ! empty( $charge->payment_intent ) && ! empty( $charge->captured ) ) {
			$intent_order = WC_Stripe_Helper::get_order_by_intent_id( (string) $charge->payment_intent );
			if ( $intent_order instanceof WC_Order
				&& $this->maybe_flag_unexpected_charge_on_order(
					$intent_order,
					(string) $charge->payment_intent,
					$charge,
					'charge.succeeded'
				)
			) {
				return;
			}
		}

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

		// Record the captured flag while the charge is in hand; for async-confirmed orders this
		// webhook may be the first time one exists (see sync_stripe_charge_captured()'s docblock).
		WC_Stripe_Order_Helper::get_instance()->sync_stripe_charge_captured( $order, $charge );

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

		/**
		 * This action is documented in includes/class-wc-stripe-webhook-handler.php.
		 */
		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, null );
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

		/**
		 * This action is documented in includes/class-wc-stripe-webhook-handler.php.
		 */
		do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, null );
	}

	/**
	 * Process webhook refund.
	 *
	 * @since 4.0.0
	 * @version 4.9.0
	 * @param stdClass $notification The decoded charge.refunded event.
	 */
	public function process_webhook_refund( $notification ) {
		// The charge.refunded payload object is the charge itself.
		$charge_payload = $notification->data->object;
		$refund_object  = $this->get_refund_object( $notification );
		$order          = WC_Stripe_Helper::get_order_by_refund_id( $refund_object->id );
		$order_helper   = WC_Stripe_Order_Helper::get_instance();

		if ( ! $order ) {
			WC_Stripe_Logger::debug( 'Could not find order via refund ID: ' . $refund_object->id );
			$order = WC_Stripe_Helper::get_order_by_charge_id( $notification->data->object->id );
		}

		// Fallback for orders missing the stored charge ID: match via the intent ID and back-fill the charge.
		if ( ! $order && ! empty( $notification->data->object->payment_intent ) ) {
			$order = WC_Stripe_Helper::get_order_by_intent_id( $notification->data->object->payment_intent );

			if ( $order instanceof WC_Order && $order_helper->is_stripe_gateway_order( $order ) && ! $order->get_transaction_id() ) {
				$order->set_transaction_id( $charge_payload->id );
				$order_helper->sync_stripe_charge_captured( $order, $charge_payload );
				$order->save();
			}
		}

		if ( ! $order ) {
			WC_Stripe_Logger::warning( "Could not find order via refund ID ({$refund_object->id}) or charge ID ({$notification->data->object->id})" );
			return;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_id = $order->get_id();

		if ( $order_helper->is_stripe_gateway_order( $order ) ) {
			$charge = $order->get_transaction_id();

			// Repair the stored captured flag from the payload (the charge itself) when absent
			// or stale, so the uncaptured branch below can't cancel a paid order as a voided
			// pre-authorization. The save decision compares the stored strings, not booleans:
			// '' and 'no' both read as uncaptured, but a repaired ''->'no' must still persist —
			// nothing below is guaranteed to save an already-cancelled order.
			$was_recorded = (string) $order_helper->get_stripe_charge_captured( $order );
			$captured     = $order_helper->sync_stripe_charge_captured( $order, $charge_payload ) ?? wc_string_to_bool( $was_recorded );

			if ( (string) $order_helper->get_stripe_charge_captured( $order ) !== $was_recorded ) {
				$order->save();
			}

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

				$stripe_refund_id = $refund_object->id;

				if ( is_wp_error( $refund ) ) {
					WC_Stripe_Logger::error( 'Error creating refund for order: ' . $order_id, [ 'error_message' => $refund->get_error_message() ] );
				} else {
					// The parent-order meta below only tracks the latest refund, so the record keeps
					// its own ID for per-refund reconciliation.
					$order_helper->update_stripe_refund_id_for_refund( $refund, $stripe_refund_id );
					$refund->save_meta_data();
				}

				$order_helper->update_stripe_refund_id( $order, $stripe_refund_id );

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
			WC_Stripe_Helper::get_external_link_open_tag( $this->get_transaction_url( $order ) ),
			'</a>',
			esc_html( $notification->data->object->reason )
		);

		/**
		 * Filters whether a review webhook should change the order status.
		 *
		 * @param bool     $change_status Whether the webhook should change the order status.
		 * @param WC_Order $order         Order associated with the review.
		 * @param object   $notification  Stripe webhook notification.
		 */
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
			/** This filter is documented in includes/class-wc-stripe-webhook-handler.php. */
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

		$checkout_type = $intent->metadata->checkout_type ?? '';

		// For AP, attempt to find the order via the checkout session.
		if ( ! $order
			&& 'payment_intent.payment_failed' === $notification->type
			&& WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE === $checkout_type ) {
			$order = $this->get_order_by_intent_checkout_session( isset( $intent->id ) ? (string) $intent->id : '' );
		}

		if ( ! $order ) {
			WC_Stripe_Logger::warning( 'Could not find order via intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status(
			/** This filter is documented in includes/class-wc-stripe-webhook-handler.php. */
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

					// Record the captured flag while the charge is in hand; for async-confirmed
					// orders this webhook delivers the first charge that ever exists for them
					// (see sync_stripe_charge_captured()'s docblock).
					$order_helper->sync_stripe_charge_captured( $order, $charge );

					/* translators: transaction id */
					$order->update_status( OrderStatus::ON_HOLD, sprintf( __( 'Stripe charge awaiting payment: %s.', 'woocommerce-gateway-stripe' ), $charge->id ) );
				}
				break;
			case 'payment_intent.requires_action':
				/**
				 * Fires when a PaymentIntent webhook reports that payment requires action.
				 *
				 * @param WC_Order $order  Order associated with the PaymentIntent.
				 * @param object   $intent Stripe PaymentIntent object.
				 */
				do_action( 'wc_gateway_stripe_process_payment_intent_requires_action', $order, $notification->data->object );

				if ( $is_voucher_payment ) {
					$order->update_status( OrderStatus::ON_HOLD, __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
					wc_reduce_stock_levels( $order_id );
				}
				break;
			case 'payment_intent.succeeded':
			case 'payment_intent.amount_capturable_updated':
				WC_Stripe_Logger::debug( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );

				/**
				 * Filters whether successful PaymentIntent webhook processing should be deferred.
				 *
				 * @param bool     $process_async Whether to process the webhook asynchronously.
				 * @param WC_Order $order         Order associated with the PaymentIntent.
				 * @param object   $intent        Stripe PaymentIntent object.
				 * @param object   $notification  Stripe webhook notification.
				 */
				$process_webhook_async = apply_filters( 'wc_stripe_process_payment_intent_webhook_async', true, $order, $intent, $notification );
				$is_awaiting_action    = $order_helper->get_stripe_upe_waiting_for_redirect( $order ) ?? false;

				// Process the webhook now if it's for a voucher, wallet, or BLIK payment, or if filtered to process immediately and order is not awaiting action.
				if ( $is_voucher_payment || $is_wallet_payment || $is_blik_payment || ( ! $process_webhook_async && ! $is_awaiting_action ) ) {
					$charge = $this->get_latest_charge_from_intent( $intent );

					/**
					 * Fires after a webhook charge is processed.
					 * Deprecated in favor of wc_gateway_stripe_process_payment_charge.
					 *
					 * @deprecated 9.7.0
					 * @param object   $charge The charge object.
					 * @param WC_Order $order  The order object.
					*/
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
						/**
						 * Fires when PaymentIntent webhook processing is deferred while the order awaits action.
						 *
						 * @param WC_Order $order Order associated with the PaymentIntent.
						 */
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

				/**
				 * This action is documented in includes/class-wc-stripe-webhook-handler.php.
				 */
				do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, null );

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

		/**
		 * Filters the valid order statuses for payment processing.
		 * Deprecated in favor of wc_stripe_allowed_payment_processing_statuses, which also includes the order object.
		 *
		 * @deprecated 9.7.0
		 *
		 * @param array    $allowed_payment_processing_statuses The allowed payment processing statuses.
		 */
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
			$this->complete_order_for_succeeded_setup_intent( $order, $intent );
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
	 * Completes an order whose SetupIntent has succeeded.
	 *
	 * Delegates to the gateway so the token, mandate and payment-method-title work that follows a
	 * confirmed intent runs here too. A SetupIntent that only reaches `succeeded` asynchronously
	 * (bank microdeposits, SEPA) never passes through the redirect handler, so completing the order
	 * without that step would leave the shopper paid up but without a saved payment method.
	 *
	 * @param WC_Order $order  The order to complete.
	 * @param stdClass $intent The SetupIntent from the webhook payload.
	 * @return void
	 */
	private function complete_order_for_succeeded_setup_intent( $order, $intent ) {
		try {
			WC_Stripe::get_instance()->get_main_stripe_gateway()->process_order_for_confirmed_intent( $order, $intent->id, true );
			return;
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Could not finalize the payment method for a succeeded SetupIntent; completing the order without it.',
				[
					'order_id'        => $order->get_id(),
					'setup_intent_id' => $intent->id ?? '',
					'error_message'   => $e->getMessage(),
				]
			);
		}

		// Fall back to the previous behaviour so a failure above can't leave a verified payment unsettled.
		if ( $this->has_pre_order( $order ) ) {
			$this->mark_order_as_pre_ordered( $order );
			return;
		}

		$order->payment_complete();
	}

	/**
	 * Schedules a job to run in the future to check on the status of a webhook.
	 *
	 * Each Webhook type which is deferred should be supported by @see process_deferred_webhook().
	 *
	 * @param stdClass $webhook_notification The webhook payload received from Stripe.
	 * @param array    $additional_data      Additional data to pass to the scheduled job.
	 * @param int|null $delay                Seconds to wait before retrying. Defaults to $deferred_webhook_delay.
	 */
	protected function defer_webhook_processing( $webhook_notification, $additional_data, $delay = null ) {
		$delay = null === $delay ? $this->deferred_webhook_delay : $delay;
		$this->action_scheduler_service->schedule_job(
			time() + $delay,
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

					// Cancelled orders route to a refund instead of the mark-as-paid path below.
					// @see handle_deferred_payment_for_cancelled_order() for why.
					$order_cancelled = $order->has_status( OrderStatus::CANCELLED );

					// Check if the order is still in a valid state to process the webhook.
					/** This filter is documented in includes/class-wc-stripe-webhook-handler.php. */
					if ( ! $order_cancelled && ! $order->has_status( apply_filters( 'wc_stripe_allowed_payment_processing_statuses', [ OrderStatus::PENDING, OrderStatus::FAILED ], $order ) ) ) {
						WC_Stripe_Logger::debug( "Skipped processing deferred webhook for Stripe PaymentIntent {$intent_id} for order {$order->get_id()} - payment already complete." );
						return;
					}

					$order_helper = WC_Stripe_Order_Helper::get_instance();

					// Serialize against the order-received redirect handler, which holds this same
					// lock. Without it both paths settle concurrently; the loser no-ops on an
					// already-paid order, so the initial paid transition's emails never fire. Re-queue
					// while locked — the lock's 5-minute TTL bounds the retry.
					if ( $order_helper->lock_order_payment( $order ) ) {
						$this->defer_webhook_processing( $notification, $additional_data, $this->locked_order_retry_delay );
						return;
					}

					try {
						if ( $order_cancelled ) {
							$this->handle_deferred_payment_for_cancelled_order( $order, $intent_id );
						} else {
							$this->handle_deferred_payment_intent_succeeded( $order, $intent_id );
						}
					} finally {
						$order_helper->unlock_order_payment( $order );
					}
					break;
				case 'checkout.session.completed':
				case 'checkout.session.async_payment_succeeded':
					// If the order is still locked, this re-queues itself again; don't fire the
					// action now — the next retry fires it once settlement actually runs.
					if ( $this->handle_checkout_session_success( $notification ) ) {
						return;
					}
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
	 * Returns true when the order was paid via a Stripe gateway (the main `stripe` gateway or a
	 * `stripe_*` payment method).
	 *
	 * @param WC_Order $order
	 */
	protected function order_uses_stripe_gateway( WC_Order $order ): bool {
		$payment_method = (string) $order->get_payment_method();
		return 'stripe' === $payment_method || str_starts_with( $payment_method, 'stripe_' );
	}

	/**
	 * Flags an unexpected Stripe charge on an order that was paid via a different gateway.
	 *
	 * Adds a visible order note linking to the Stripe dashboard and fires an action so site-specific
	 * integrations can react (for example, to auto-refund). Idempotent per intent — repeat calls for
	 * the same PaymentIntent on the same order are no-ops, so it's safe to invoke from every webhook
	 * path that can detect the unexpected charge (charge.succeeded fallback, charge.captured fallback).
	 *
	 * @param WC_Order $order        The order to evaluate.
	 * @param string   $intent_id    The Stripe PaymentIntent ID.
	 * @param object   $charge       The Stripe Charge object.
	 * @param string   $webhook_type The Stripe webhook event type (e.g. 'charge.succeeded' or 'charge.captured').
	 *
	 * @return bool True when an unexpected-charge note was added, false when skipped.
	 */
	protected function maybe_flag_unexpected_charge_on_order( WC_Order $order, string $intent_id, object $charge, string $webhook_type ): bool {
		if ( $this->order_uses_stripe_gateway( $order ) ) {
			return false;
		}
		$dedup_meta_key = '_stripe_unexpected_charge_flagged_' . $intent_id;
		if ( '' !== (string) $order->get_meta( $dedup_meta_key ) ) {
			return false;
		}

		$currency        = strtoupper( (string) $charge->currency );
		$decimal_amount  = WC_Stripe_Helper::convert_from_stripe_amount( (int) $charge->amount, $currency );
		$formatted_price = wc_price( $decimal_amount, [ 'currency' => $currency ] );
		$dashboard_url   = WC_Stripe_Helper::get_transaction_url_for_id( $intent_id, empty( $charge->livemode ) );

		$message = sprintf(
			/* translators: 1) formatted amount with currency, 2) Stripe PaymentIntent ID, 3) Stripe charge ID, 4) opening anchor tag for the Stripe dashboard link, 5) closing anchor tag. */
			__( 'Stripe captured a charge of %1$s (PaymentIntent %2$s, charge %3$s) after this order was already paid by another gateway. This unexpected charge needs to be refunded manually from the %4$sStripe dashboard%5$s.', 'woocommerce-gateway-stripe' ),
			wp_kses_post( $formatted_price ),
			esc_html( $intent_id ),
			esc_html( (string) $charge->id ),
			WC_Stripe_Helper::get_external_link_open_tag( $dashboard_url ),
			'</a>'
		);

		$order->add_order_note( $message );
		$order->update_meta_data( $dedup_meta_key, current_time( 'mysql' ) );
		$order->save();

		/**
		 * Fires when an asynchronously-confirmed Stripe charge is detected on an order that was
		 * already paid via a different gateway, leaving an unexpected charge that the merchant needs
		 * to remediate. Triggered at most once per PaymentIntent per order.
		 *
		 * @since 10.8.0
		 *
		 * @param WC_Order $order        The order paid via a non-Stripe gateway.
		 * @param object   $charge       The Stripe Charge object.
		 * @param string   $webhook_type The Stripe webhook event type ('charge.succeeded' or 'charge.captured').
		 */
		do_action( 'wc_stripe_unexpected_charge_detected', $order, $charge, $webhook_type );

		return true;
	}

	/**
	 * Updates a Checkout Session payment intent with the order description and metadata.
	 *
	 * @since 10.8.0
	 *
	 * @param string $payment_intent_id The payment intent ID.
	 * @param array  $request           The request payload (description and metadata) computed at scheduling time.
	 * @return void
	 */
	public function process_payment_intent_metadata( string $payment_intent_id, array $request ): void {
		try {
			$response = WC_Stripe_API::request( $request, 'payment_intents/' . $payment_intent_id, 'POST' );
			if ( ! empty( $response->error->message ) ) {
				throw new WC_Stripe_Exception( $response->error->message );
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Failed to update payment intent description and metadata: ' . $e->getMessage() );

			// This will be caught by Action Scheduler and logged as an error.
			throw $e;
		}
	}

	/**
	 * Processes the checkout session metadata update event to store additional metadata on the checkout session object.
	 *
	 * @deprecated 10.8.0 Replaced by {@see process_payment_intent_metadata()}; kept so jobs queued before the switch still run to completion.
	 *
	 * @param string $checkout_session_id The checkout session ID.
	 * @param array $metadata The metadata from the checkout session.
	 * @return void
	 */
	public function process_checkout_session_metadata( string $checkout_session_id, array $metadata ): void {
		// No runtime deprecation notice yet: legacy jobs queued before the 10.8.0 switch can still run in this
		// release, and we don't want to emit notices for those. The notice will be added in a future release.
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

		/** This action is documented in includes/class-wc-stripe-webhook-handler.php. */
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
	 * Handles a deferred payment webhook for an order the shopper already cancelled.
	 *
	 * A shopper can cancel an order while its payment is still settling in Stripe - for
	 * example a slow 3DS confirmation that fails the return to checkout, after which the
	 * shopper cancels the still-pending order. Stripe can then capture the payment and fire
	 * payment_intent.succeeded (or authorise it and fire amount_capturable_updated) against
	 * a cancelled order. Marking that order paid would silently run payment_complete(),
	 * granting downloads, reducing stock and firing fulfilment hooks for an order nobody
	 * wants. Instead we return the money: refund a captured charge, or void an uncaptured
	 * authorisation, and record the details on the order for the merchant.
	 *
	 * @param WC_Order $order     The cancelled order.
	 * @param string   $intent_id The PaymentIntent ID from the webhook payload.
	 * @return void
	 */
	protected function handle_deferred_payment_for_cancelled_order( $order, $intent_id ) {
		/**
		 * Filters whether a late Stripe payment on a cancelled order is automatically refunded.
		 *
		 * Return false to leave the payment in place (e.g. to reconcile it manually) instead of
		 * refunding the charge or voiding the authorisation.
		 *
		 * @since 10.9.0
		 *
		 * @param bool     $auto_refund Whether to refund the payment automatically. Default true.
		 * @param WC_Order $order       The cancelled order that received the payment.
		 */
		if ( ! apply_filters( 'wc_stripe_auto_refund_cancelled_order', true, $order ) ) {
			WC_Stripe_Logger::debug( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - auto-refund disabled by the wc_stripe_auto_refund_cancelled_order filter." );
			return;
		}

		$intent = $this->get_intent_from_order( $order );

		if ( ! $intent || $intent->id !== $intent_id ) {
			WC_Stripe_Logger::debug( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - intent ID stored on the order doesn't match." );
			return;
		}

		// Idempotency: Action Scheduler can retry this job, and issuing a refund twice would
		// take the money from the shopper twice. The meta flag is the deterministic guard; the
		// Stripe-state checks below catch a retry that lands before the flag was persisted.
		if ( wc_string_to_bool( $order->get_meta( self::META_REFUNDED_AFTER_CANCELLATION ) ) ) {
			WC_Stripe_Logger::debug( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - already refunded after cancellation." );
			return;
		}

		if ( WC_Stripe_Intent_Status::CANCELED === $intent->status ) {
			WC_Stripe_Logger::debug( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - the intent is already cancelled." );
			return;
		}

		$charge    = $this->get_latest_charge_from_intent( $intent );
		$charge_id = isset( $charge->id ) ? $charge->id : '';

		if ( $charge && ! empty( $charge->refunded ) ) {
			$this->mark_cancelled_order_refunded( $order );
			WC_Stripe_Logger::debug( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - the charge is already refunded." );
			return;
		}

		$is_authorization = WC_Stripe_Intent_Status::REQUIRES_CAPTURE === $intent->status;

		// process_refund() returns the money either way: it refunds a captured charge, or cancels a
		// requires_capture intent to release the authorisation hold, recovering the charge ID from
		// the stored intent (a cancelled order has no recorded transaction).
		//
		// For a captured charge, refund the charge's remaining balance (amount minus any prior
		// refund) rather than the order total, which can drift from what Stripe actually holds after
		// a partial capture, an earlier partial refund, or an edited order total. process_refund()
		// treats a null amount as a no-op for a captured charge, so a real amount is required; the
		// authorisation-void path instead needs null (any amount makes it throw).
		$refund_amount = null;
		if ( ! $is_authorization ) {
			// Skip if no charge amount to refund against.
			if ( ! is_object( $charge ) || ! isset( $charge->amount ) ) {
				WC_Stripe_Logger::warning( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - no charge amount available to refund." );
				return;
			}

			$refundable = (int) $charge->amount - (int) ( $charge->amount_refunded ?? 0 );
			if ( $refundable <= 0 ) {
				WC_Stripe_Logger::warning( "Skipped refunding cancelled order {$order->get_id()} for Stripe PaymentIntent {$intent_id} - the charge has no refundable balance." );
				return;
			}

			// $refundable is in the smallest unit; convert with the order currency because process_refund()
			// reconverts with the order currency. Using the charge currency here would corrupt the amount
			// whenever the two currencies have different decimal exponents.
			$refund_amount = WC_Stripe_Helper::convert_from_stripe_amount( $refundable, $order->get_currency() );
		}

		$reason = __( 'Payment received in Stripe after the order was cancelled by the customer. Automatically refunded.', 'woocommerce-gateway-stripe' );
		$result = $this->process_refund( $order->get_id(), $refund_amount, $reason );

		// The success contract is path-dependent. A captured refund must return true. An authorisation
		// void produces no refund object, so process_refund() returns false or null either way — an
		// ambiguous signal — so re-read the intent and require a Stripe-confirmed cancellation before
		// treating the void as settled. A WP_Error is always a failure.
		if ( $is_authorization ) {
			$confirmed_intent = is_wp_error( $result ) ? null : $this->get_intent_from_order( $order );
			$succeeded        = $confirmed_intent && WC_Stripe_Intent_Status::CANCELED === $confirmed_intent->status;
		} else {
			$succeeded = ( true === $result );
		}
		// Link the payment in the mode it was actually made in, which isn't necessarily the gateway's current mode.
		$is_test_mode = empty( $intent->livemode );
		$intent_url   = WC_Stripe_Helper::get_transaction_url_for_id( $intent_id, $is_test_mode );
		$charge_url   = WC_Stripe_Helper::get_transaction_url_for_id( $charge_id, $is_test_mode );
		$reference    = sprintf(
			/* translators: 1: opening anchor tag for the PaymentIntent, 2: Stripe PaymentIntent ID, 3: opening anchor tag for the charge, 4: Stripe charge ID, 5: closing anchor tag. */
			__( 'PaymentIntent: %1$s%2$s%5$s. Charge: %3$s%4$s%5$s.', 'woocommerce-gateway-stripe' ),
			WC_Stripe_Helper::get_external_link_open_tag( $intent_url ),
			esc_html( $intent_id ),
			WC_Stripe_Helper::get_external_link_open_tag( $charge_url ),
			esc_html( $charge_id ),
			'</a>'
		);

		if ( ! $succeeded ) {
			$error_message = is_wp_error( $result ) ? $result->get_error_message() : '';
			$order->add_order_note(
				sprintf(
					/* translators: %s: Stripe error message (may be empty) followed by the PaymentIntent and charge IDs linked to the Stripe dashboard. */
					__( 'Stripe took a payment after this order was cancelled, but the automatic refund failed. Please refund it manually in the Stripe dashboard. %s', 'woocommerce-gateway-stripe' ),
					trim( $error_message . ' ' . $reference )
				)
			);
			WC_Stripe_Logger::error( "Failed to refund cancelled order {$order->get_id()} (PaymentIntent {$intent_id}) after a late Stripe payment: {$error_message}" );
			return;
		}

		// Record the outcome with the Stripe identifiers so the merchant can trace it. process_refund()
		// adds its own note for a captured refund; this note also covers the voided-authorisation case
		// and always carries the intent and charge IDs.
		if ( $is_authorization ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: Stripe PaymentIntent and charge IDs, linked to the Stripe dashboard. */
					__( 'Stripe authorised this payment after the order was cancelled, so the authorisation was voided to release the shopper\'s funds. %s', 'woocommerce-gateway-stripe' ),
					$reference
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					/* translators: %s: Stripe PaymentIntent and charge IDs, linked to the Stripe dashboard. */
					__( 'This payment was received in Stripe after the order was cancelled, so it was automatically refunded. %s', 'woocommerce-gateway-stripe' ),
					$reference
				)
			);
		}
		$this->mark_cancelled_order_refunded( $order );

		WC_Stripe_Logger::info( "Returned the payment for cancelled order {$order->get_id()} (PaymentIntent {$intent_id}) after a late Stripe payment." );
	}

	/**
	 * Records that a late Stripe payment on a cancelled order has been refunded/voided.
	 *
	 * @param WC_Order $order The cancelled order.
	 * @return void
	 */
	private function mark_cancelled_order_refunded( $order ) {
		$order->update_meta_data( self::META_REFUNDED_AFTER_CANCELLATION, 'yes' );
		$order->save();
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

		// The order exists, so process the webhook immediately — unless it re-queues itself because the
		// order is locked, in which case propagate that signal so the caller skips firing the
		// `wc_stripe_webhook_received` action before settlement actually happens.
		return $this->handle_checkout_session_success( $notification );
	}

	/**
	 * Handles a deferred checkout session success event.
	 *
	 * @param object        $notification The Stripe notification containing the checkout session data.
	 * @return bool True if the event was re-queued for async processing, false if handled inline.
	 */
	protected function handle_checkout_session_success( object $notification ): bool {
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
			return false;
		}
		WC_Stripe_Database_Cache::set( $lock_key, time(), 5 * MINUTE_IN_SECONDS );

		// Look for an order. If one does not exists, this is probably an agentic hook.
		$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $checkout_session->id );
		if ( ! $order instanceof \WC_Order ) {
			// An Adaptive Pricing session is tagged at creation. It is never agentic,
			// so don't route it into the agentic handler — that would silently drop a
			// paid session whenever agentic commerce is disabled.
			$checkout_type = $checkout_session->metadata->checkout_type ?? '';
			if ( WC_Stripe_Checkout_Sessions_Ajax_Handler::ADAPTIVE_PRICING_CHECKOUT_TYPE === $checkout_type ) {
				WC_Stripe_Logger::warning(
					'Completed Adaptive Pricing checkout session has no matching order: ' . $checkout_session->id
				);
				WC_Stripe_Database_Cache::delete( $lock_key );
				return false;
			}

			try {
				if ( WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
					$this->handle_agentic_checkout_session( $notification );
				}
			} finally {
				WC_Stripe_Database_Cache::delete( $lock_key );
			}
			return false;
		}

		WC_Stripe_Database_Cache::delete( $lock_key );

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
			return false;
		}

		$order_currency = strtolower( $order->get_currency() );
		$order_amount   = WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $order_currency );

		// Add conditional checks to make sure we handle situations where the webhook is sent using an
		// older API version and schema. Before 2025-03-31.basil an Adaptive Pricing Session
		// put the buyer's figures at the top level and the store's under currency_conversion; from Basil
		// on it is the other way round, with the buyer's in presentment_details.
		// When $checkout_session->currency_conversion is present, we assume we have the older schema.
		if ( isset( $checkout_session->currency_conversion ) ) {
			$settlement_currency = isset( $checkout_session->currency_conversion->source_currency ) ? strtolower( (string) $checkout_session->currency_conversion->source_currency ) : '';
			$settlement_amount   = isset( $checkout_session->currency_conversion->amount_total ) ? (int) $checkout_session->currency_conversion->amount_total : null;
		} else {
			$settlement_currency = isset( $checkout_session->currency ) ? strtolower( (string) $checkout_session->currency ) : '';
			$settlement_amount   = isset( $checkout_session->amount_total ) ? (int) $checkout_session->amount_total : null;
		}

		if ( $settlement_amount !== $order_amount || $settlement_currency !== $order_currency ) {
			WC_Stripe_Logger::error(
				'Refusing to settle Checkout Session: settlement amount/currency does not match the order.',
				[
					'checkout_session_id' => $session_id,
					'order_id'            => $order->get_id(),
					'settlement_amount'   => $settlement_amount,
					'order_amount'        => $order_amount,
					'settlement_currency' => $settlement_currency,
					'order_currency'      => $order_currency,
					'api_version'         => isset( $notification->api_version ) ? (string) $notification->api_version : '',
				]
			);

			if ( null === $settlement_amount ) {
				$session_display = __( 'unknown', 'woocommerce-gateway-stripe' );
			} elseif ( ! $settlement_currency ) {
				/* translators: 1) Stripe numeric amount, e.g. '1500'. */
				$session_display = sprintf( __( 'unknown currency; Stripe amount: %1$d', 'woocommerce-gateway-stripe' ), $settlement_amount );
			} else {
				$session_display = strtoupper( $settlement_currency ) . ' ' . WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( $settlement_amount, $settlement_currency );
			}

			// The identifiers are the only handle on the payment this order gets: settlement stops here,
			// so neither the intent nor the charge is ever recorded on the order for the admin to follow.
			$session_intent_id = isset( $checkout_session->payment_intent ) ? (string) $checkout_session->payment_intent : '';
			$intent_reference  = '';
			if ( '' !== $session_intent_id ) {
				$intent_reference = ' ' . sprintf(
					/* translators: 1) opening anchor tag linking to the Stripe dashboard, 2) Stripe PaymentIntent ID, 3) closing anchor tag */
					__( 'PaymentIntent: %1$s%2$s%3$s.', 'woocommerce-gateway-stripe' ),
					'<a href="' . esc_url( WC_Stripe_Helper::get_transaction_url_for_id( $session_intent_id, empty( $checkout_session->livemode ) ) ) . '" target="_blank" rel="noopener noreferrer">',
					esc_html( $session_intent_id ),
					'</a>'
				);
			}

			$order->add_order_note(
				sprintf(
					/* translators: 1) Checkout Session settlement amount and currency 2) expected order total and currency 3) Stripe Checkout Session ID */
					__( 'Stripe payment was not marked complete: the Checkout Session settlement amount (%1$s) does not match the order total (%2$s). The order was placed on hold for manual review. Checkout Session: %3$s.', 'woocommerce-gateway-stripe' ),
					$session_display,
					strtoupper( $order_currency ) . ' ' . $order->get_total(),
					esc_html( $session_id )
				) . $intent_reference
			);

			// Hold the order rather than leaving it pending: wc_cancel_unpaid_orders() cancels pending
			// orders and restores their stock once woocommerce_hold_stock_minutes elapses, which would
			// discard an order whose payment Stripe already captured.
			if ( ! $order->has_status( OrderStatus::ON_HOLD ) ) {
				$order->update_status( OrderStatus::ON_HOLD );
			}

			return false;
		}

		// Set the order being processed for the `wc_stripe_webhook_received` action later.
		$this->resolved_order = $order;

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Lock the order. The order-received redirect handler briefly holds this same lock across a
		// Stripe API call without settling; dropping the event here would leave a paid order stuck
		// pending. Re-queue instead so settlement runs once the lock is released — the lock's 5-minute
		// TTL guarantees a wedged holder clears, so the retry terminates. Return the deferred signal so
		// the caller skips firing `wc_stripe_webhook_received` now: settlement hasn't happened yet, and
		// the retry fires the action itself once it does.
		if ( $order_helper->lock_order_payment( $order ) ) {
			$this->defer_webhook_processing( $notification, [ 'session_id' => $session_id ], $this->locked_order_retry_delay );
			return true;
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
				return false;
			}

			$payment_method_id = is_object( $intent->payment_method ) ? $intent->payment_method->id : $intent->payment_method;

			// Update the order with the payment method ID if it's not already set.
			if ( ! $order_helper->get_stripe_source_id( $order ) && ! empty( $payment_method_id ) ) {
				$order_helper->update_stripe_source_id( $order, $payment_method_id );
			}

			// Fetch the charge once; reused below.
			$charge = $this->get_latest_charge_from_intent( $intent );

			// Save payment method to store if the customer requested it during checkout.
			if ( $order_helper->get_should_save_stripe_payment_method( $order ) && ! empty( $payment_method_id ) ) {
				$upe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

				$payment_method_object = is_object( $intent->payment_method ) ? $intent->payment_method : WC_Stripe_API::retrieve( 'payment_methods/' . $payment_method_id );
				if ( $upe_gateway instanceof WC_Stripe_UPE_Payment_Gateway && ! is_wp_error( $payment_method_object ) && empty( $payment_method_object->error ) && ! empty( $payment_method_object ) ) {
					// Get the payment method details that should be saved. That may be different from the
					// original payment method, e.g. for Bancontact and iDEAL/Wero, which are saved as SEPA.
					$payment_method_to_save = $upe_gateway->get_reusable_payment_method_for_saving( $payment_method_object, $charge );

					if ( is_object( $payment_method_to_save ) && ! empty( $payment_method_to_save->type ) ) {
						$upe_gateway->handle_saving_payment_method( $order, $payment_method_to_save, $payment_method_to_save->type );
					}

					// Clear the flag so retries don't re-run this, even when nothing was saved.
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

			$charge->is_webhook_response = true;
			$this->process_response( $charge, $order );

			// The checkout session is created from the cart before the order exists, so the intent starts
			// without order description or metadata. Backfill them here with the same values the standard
			// non-session flow attaches at intent creation, so Adaptive Pricing transactions aren't missing
			// the order/customer identifiers merchants rely on.
			if ( ! empty( $intent_id ) ) {
				$this->action_scheduler_service->schedule_job(
					time() + $this->process_payment_intent_metadata_delay,
					$this->process_payment_intent_metadata_action,
					[
						'payment_intent_id' => $intent_id,
						'request'           => [
							'description' => WC_Stripe_Helper::get_payment_intent_description( $order ),
							'metadata'    => $this->get_metadata_from_order( $order ),
						],
					]
				);
			} else {
				WC_Stripe_Logger::error( 'Empty intent ID, so cannot add order details and metadata.' );
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Error processing checkout session for order: ' . $order->get_id(),
				[ 'error_message' => $e->getMessage() ]
			);

			/**
			 * This action is documented in includes/class-wc-stripe-webhook-handler.php.
			 */
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

		return false;
	}

	/**
	 * Processes checkout session failure events.
	 * This includes:
	 * - checkout.session.expired event; Fires when a Stripe Checkout session expires before the customer completes payment.
	 * - checkout.session.async_payment_failed event; Fires when an asynchronous payment method on a Stripe Checkout session fails.
	 * When the checkout session has expired, we mark the order as cancelled.
	 * When the checkout session has a failed payment method, we mark the order as failed.
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

		$is_expired = 'checkout.session.expired' === $notification->type;

		try {
			if ( $order_helper->is_stripe_status_final( $order ) ) {
				return;
			}

			if ( $order->is_paid() || $order->has_status( [ OrderStatus::PROCESSING, OrderStatus::COMPLETED, OrderStatus::FAILED ] ) ) {
				return;
			}

			// Core's wc_cancel_unpaid_orders() usually cancels the pending order at the hold-stock
			// timeout, well inside Stripe's 24h session TTL, so the expiry event routinely arrives on an
			// already-cancelled order. Async failures must still be able to mark such an order failed.
			if ( $is_expired && $order->has_status( OrderStatus::CANCELLED ) ) {
				return;
			}

			if ( $is_expired ) {
				$this->cancel_expired_checkout_session_order( $order );
			} else {
				$status_update         = [];
				$status_update['from'] = $order->get_status();
				$status_update['to']   = OrderStatus::FAILED;

				$order->update_status( OrderStatus::FAILED, __( 'The async payment for this checkout session has failed.', 'woocommerce-gateway-stripe' ) );
			}

			/**
			 * This action is documented in includes/class-wc-stripe-webhook-handler.php.
			 */
			do_action( 'wc_gateway_stripe_process_webhook_payment_error', $order, $notification, null );

			if ( ! $is_expired ) {
				$this->send_failed_order_email( $order->get_id(), $status_update );
			}
		} finally {
			$order_helper->unlock_order_payment( $order );
		}
	}

	/**
	 * Cancels an order whose Stripe Checkout Session expired.
	 *
	 * No payment was ever attempted, so neither party is notified by default. WooCommerce only fires
	 * its cancelled-order emails on some transitions — never on pending to cancelled for the customer
	 * email — so its automatic send is suppressed here and the emails are dispatched explicitly, which
	 * keeps both filters meaningful whichever status the order was in.
	 *
	 * @param WC_Order $order The order to cancel.
	 * @return void
	 */
	private function cancel_expired_checkout_session_order( WC_Order $order ): void {
		/**
		 * Whether to email the customer when an order is cancelled because its Stripe Checkout
		 * Session expired. This only applies to immediate, non-deferred WooCommerce email sends when the
		 * customer cancelled order email is enabled.
		 *
		 * @param bool     $should_send Whether to send the email. Default false.
		 * @param WC_Order $order       The cancelled order.
		 *
		 * @since 10.9.0
		 */
		$send_to_customer = (bool) apply_filters( 'wc_stripe_checkout_session_expired_should_send_cancelled_order_email_to_customer', false, $order );

		/**
		 * Whether to email the merchant when an order is cancelled because its Stripe Checkout
		 * Session expired. This only applies to immediate, non-deferred WooCommerce email sends when the
		 * merchant cancelled order email is enabled.
		 *
		 * @param bool     $should_send Whether to send the email. Default false.
		 * @param WC_Order $order       The cancelled order.
		 *
		 * @since 10.9.0
		 */
		$send_to_merchant = (bool) apply_filters( 'wc_stripe_checkout_session_expired_should_send_cancelled_order_email_to_merchant', false, $order );

		$order_id = $order->get_id();

		$return_false_for_order = static function ( $enabled, $email_order ) use ( $order_id ) {
			if ( $email_order instanceof WC_Order && $email_order->get_id() === $order_id ) {
				return false;
			}
			return $enabled;
		};
		add_filter( 'woocommerce_email_enabled_customer_cancelled_order', $return_false_for_order, 10, 2 );
		add_filter( 'woocommerce_email_enabled_cancelled_order', $return_false_for_order, 10, 2 );

		try {
			$order->update_status( OrderStatus::CANCELLED, __( 'The checkout session has expired.', 'woocommerce-gateway-stripe' ) );
		} finally {
			remove_filter( 'woocommerce_email_enabled_customer_cancelled_order', $return_false_for_order, 10 );
			remove_filter( 'woocommerce_email_enabled_cancelled_order', $return_false_for_order, 10 );
		}

		if ( ! $send_to_customer && ! $send_to_merchant ) {
			return;
		}

		$emails         = WC()->mailer()->get_emails();
		$customer_email = $emails['WC_Email_Customer_Cancelled_Order'] ?? null;
		$merchant_email = $emails['WC_Email_Cancelled_Order'] ?? null;

		if ( $send_to_customer && $customer_email instanceof WC_Email_Customer_Cancelled_Order ) {
			$customer_email->trigger( $order->get_id(), $order );
		}

		if ( $send_to_merchant && $merchant_email instanceof WC_Email_Cancelled_Order ) {
			$merchant_email->trigger( $order->get_id(), $order );
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
				$order = wc_get_order( absint( $intent->metadata->order_id ) );

				if ( $order instanceof WC_Order ) {
					return $order;
				}
				return false;
			}
		}

		// Try to retrieve from the charges array.
		if ( ! empty( $intent->charges ) && is_array( $intent->charges ) ) {
			$charge   = $intent->charges[0] ?? [];
			$order_id = $charge->metadata->order_id ?? null;
			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				if ( $order instanceof WC_Order ) {
					return $order;
				}
			}
			return false;
		}

		// Fall back to finding the order via the intent ID.
		return WC_Stripe_Helper::get_order_by_intent_id( $intent->id );
	}

	/**
	 * Resolves the order behind a PaymentIntent via its Checkout Session.
	 *
	 * @param string $intent_id PaymentIntent ID from the failed event.
	 * @return WC_Order|null
	 */
	private function get_order_by_intent_checkout_session( string $intent_id ): ?WC_Order {
		if ( '' === $intent_id ) {
			return null;
		}

		try {
			$sessions   = WC_Stripe_API::request( [], 'checkout/sessions?payment_intent=' . $intent_id . '&limit=1', 'GET' );
			$session    = $sessions->data[0] ?? null;
			$session_id = isset( $session->id ) ? (string) $session->id : '';
			if ( '' === $session_id ) {
				WC_Stripe_Logger::warning( 'No checkout session found for intent ' . $intent_id . '; order left unresolved.' );
				return null;
			}

			$order = WC_Stripe_Helper::get_order_by_checkout_session_id( $session_id );
			return $order instanceof WC_Order ? $order : null;
		} catch ( Exception $e ) {
			WC_Stripe_Logger::warning( 'Unable to resolve order from checkout session for intent ' . $intent_id . ': ' . $e->getMessage() );
			return null;
		}
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

		// Stripe calls only this site's single agentic endpoint for these synchronous hooks, so
		// recording the session here marks it as belonging to this site. The order-creating
		// checkout.session.completed event later broadcasts to every site sharing the account;
		// the claim lets us skip sessions that originated on a sibling site. See STRIPE-968.
		$checkout_session_id = isset( $event->data->checkout_session ) ? (string) $event->data->checkout_session : '';

		try {
			switch ( $event_type ) {
				case 'v1.delegated_checkout.customize_checkout':
					$this->claim_agentic_session( $checkout_session_id );
					$response = $this->process_agentic_customization_hook( $event );
					break;
				case 'v1.delegated_checkout.finalize_checkout':
					$this->claim_agentic_session( $checkout_session_id );
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
	 * Records that this site owns an agentic checkout session.
	 *
	 * Called from the synchronous customize/finalize hooks, which Stripe delivers only to the
	 * account's single agentic endpoint — so a claim identifies sessions this site produced.
	 *
	 * @since 10.9.0
	 * @param string $checkout_session_id The `cs_…` id from the sync hook payload.
	 */
	protected function claim_agentic_session( string $checkout_session_id ): void {
		if ( '' === $checkout_session_id ) {
			return;
		}

		WC_Stripe_Database_Cache::set(
			self::AGENTIC_SESSION_CLAIM_CACHE_PREFIX . $checkout_session_id,
			1,
			self::AGENTIC_SESSION_CLAIM_TTL
		);
	}

	/**
	 * Whether this site previously claimed the given agentic checkout session via a sync hook.
	 *
	 * @since 10.9.0
	 * @param string $checkout_session_id The `cs_…` id from checkout.session.completed.
	 * @return bool
	 */
	protected function is_agentic_session_claimed( string $checkout_session_id ): bool {
		if ( '' === $checkout_session_id ) {
			return false;
		}

		return null !== WC_Stripe_Database_Cache::get( self::AGENTIC_SESSION_CLAIM_CACHE_PREFIX . $checkout_session_id );
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

			// checkout.session.completed broadcasts to every site connected to the same Stripe
			// account. Only the site whose agentic endpoint produced this checkout claimed it via
			// the sync hook; any other site must not create a duplicate/wrong order. See STRIPE-968.
			if ( ! $this->is_agentic_session_claimed( (string) $session->get_id() ) ) {
				WC_Stripe_Logger::info(
					'Agentic checkout session was not claimed by this site; skipping order creation (likely owned by another site on the same Stripe account).',
					[ 'session_id' => $session->get_id() ]
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
			} catch ( Throwable $e ) {
				// Cap trace length to avoid overwhelming log handlers that may
				// truncate or reject very large context fields.
				$trace = $e->getTraceAsString();
				if ( strlen( $trace ) > 4000 ) {
					$trace = substr( $trace, 0, 4000 ) . '... [truncated]';
				}

				WC_Stripe_Logger::error(
					'Failed to create agentic order from checkout session.',
					[
						'session_id' => $session->get_id(),
						'error'      => $e->getMessage(),
						'exception'  => get_class( $e ),
						'file'       => $e->getFile(),
						'line'       => $e->getLine(),
						'trace'      => $trace,
					]
				);

				/**
				 * Fires when agentic commerce order creation fails.
				 *
				 * @since 10.6.0
				 * @param Throwable                          $e       The throwable that was thrown.
				 * @param WC_Stripe_Agentic_Checkout_Session $session The checkout session wrapper.
				 */
				do_action( 'wc_stripe_agentic_order_creation_failed', $e, $session );

				// Re-throw so Action Scheduler marks the job as failed. The inner
				// catch exists to log with full context and fire the failure hook;
				// swallowing here would make AS report the run as complete.
				throw $e;
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
