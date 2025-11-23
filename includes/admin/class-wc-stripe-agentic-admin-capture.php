<?php
/**
 * Class for handling manual capture of agentic checkout orders.
 *
 * @package WooCommerce_Stripe/Classes
 * @since   10.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles manual capture of agentic checkout orders.
 *
 * @since 10.2.0
 */
class WC_Stripe_Agentic_Admin_Capture {
	/**
	 * Constructor
	 *
	 * @since 10.2.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_wc_stripe_capture_agentic_payment', array( $this, 'process_capture_action' ) );
	}

	/**
	 * Add capture payment action to order actions dropdown.
	 *
	 * @since 10.2.0
	 * @param array    $actions List of order actions.
	 * @param WC_Order $order   The order object.
	 * @return array Modified list of actions.
	 */
	public function add_capture_action( $actions, $order ) {
		// Check if this is an agentic order with manual capture enabled.
		$is_agentic        = 'true' === $order->get_meta( '_wc_stripe_agentic_order' );
		$is_manual_capture = 'yes' === $order->get_meta( '_stripe_manual_capture' );
		$is_captured       = 'yes' === $order->get_meta( '_stripe_charge_captured' );
		$intent_id         = $order->get_meta( '_stripe_intent_id' );

		// Only show capture button if:.
		// 1. It's an agentic order OR has manual capture flag.
		// 2. Payment hasn't been captured yet.
		// 3. Has a valid payment intent ID.
		// 4. Payment method is Stripe.
		if ( ( $is_agentic || $is_manual_capture ) &&
			! $is_captured &&
			! empty( $intent_id ) &&
			'stripe' === $order->get_payment_method() ) {

			// Allow filtering to disable capture button.
			$enabled = apply_filters( 'wc_stripe_agentic_manual_capture_enabled', true, $order );

			if ( $enabled ) {
				$actions['wc_stripe_capture_agentic_payment'] = __( 'Capture Stripe Payment', 'woocommerce-gateway-stripe' );
			}
		}

		return $actions;
	}

	/**
	 * Process the capture payment action.
	 *
	 * @since 10.2.0
	 * @param WC_Order $order The order object.
	 * @throws Exception If capture fails.
	 */
	public function process_capture_action( $order ) {
		// Verify order has the necessary data.
		$intent_id = $order->get_meta( '_stripe_intent_id' );
		if ( empty( $intent_id ) ) {
			$order->add_order_note(
				__( 'Unable to capture payment: No payment intent ID found.', 'woocommerce-gateway-stripe' )
			);
			WC_Stripe_Logger::log(
				'Agentic capture failed: No intent ID',
				array( 'order_id' => $order->get_id() )
			);
			return;
		}

		// Check if already captured.
		$is_captured = 'yes' === $order->get_meta( '_stripe_charge_captured' );
		if ( $is_captured ) {
			$order->add_order_note(
				__( 'Payment has already been captured.', 'woocommerce-gateway-stripe' )
			);
			return;
		}

		WC_Stripe_Logger::log(
			'Attempting to capture agentic order payment',
			array(
				'order_id'  => $order->get_id(),
				'intent_id' => $intent_id,
			)
		);

		try {
			// Get order handler to use existing capture logic.
			$order_handler = new WC_Stripe_Order_Handler();

			// Get the order total, accounting for any refunds.
			$order_total = $order->get_total();
			if ( 0 < $order->get_total_refunded() ) {
				$order_total = $order_total - $order->get_total_refunded();
			}

			// Allow filtering the capture amount.
			$capture_amount = apply_filters( 'wc_stripe_agentic_capture_amount', $order_total, $order );

			// Retrieve the payment intent.
			$intent = WC_Stripe_API::retrieve( 'payment_intents/' . $intent_id );

			if ( ! empty( $intent->error ) ) {
				throw new Exception( $intent->error->message );
			}

			// Check if the intent requires capture.
			if ( WC_Stripe_Intent_Status::REQUIRES_CAPTURE !== $intent->status ) {
				if ( WC_Stripe_Intent_Status::SUCCEEDED === $intent->status ) {
					// Intent is already captured.
					$order->update_meta_data( '_stripe_charge_captured', 'yes' );
					$order->save();

					$order->add_order_note(
						__( 'Payment was already captured in Stripe.', 'woocommerce-gateway-stripe' )
					);

					// Update order status if needed.
					if ( Automattic\WooCommerce\Enums\OrderStatus::ON_HOLD === $order->get_status() ) {
						$order->payment_complete( $intent_id );
					}

					WC_Stripe_Logger::log(
						'Agentic order payment already captured',
						array(
							'order_id'  => $order->get_id(),
							'intent_id' => $intent_id,
						)
					);

					return;
				} else {
					throw new Exception(
						sprintf(
							/* translators: %s: payment intent status */
							__( 'Payment intent is in "%s" status and cannot be captured.', 'woocommerce-gateway-stripe' ),
							$intent->status
						)
					);
				}
			}

			// Capture the payment intent.
			$level3_data = $order_handler->get_level3_data_from_order( $order );
			$result      = WC_Stripe_API::request_with_level3_data(
				array(
					'amount'   => WC_Stripe_Helper::get_stripe_amount( $capture_amount ),
					'expand[]' => 'charges.data.balance_transaction',
				),
				'payment_intents/' . $intent_id . '/capture',
				$level3_data,
				$order
			);

			if ( ! empty( $result->error ) ) {
				throw new Exception( $result->error->message );
			}

			// Mark as captured.
			$order->update_meta_data( '_stripe_charge_captured', 'yes' );
			$order->save();

			// Add order note.
			$order->add_order_note(
				sprintf(
					/* translators: %s: captured amount */
					__( 'Stripe payment manually captured: %s', 'woocommerce-gateway-stripe' ),
					wc_price( $capture_amount, array( 'currency' => $order->get_currency() ) )
				)
			);

			// Update order status to processing/completed.
			if ( Automattic\WooCommerce\Enums\OrderStatus::ON_HOLD === $order->get_status() ) {
				$order->payment_complete( $intent_id );
			}

			WC_Stripe_Logger::log(
				'Agentic order payment captured successfully',
				array(
					'order_id'        => $order->get_id(),
					'intent_id'       => $intent_id,
					'captured_amount' => $capture_amount,
				)
			);

			// Fire action for custom handling.
			do_action( 'wc_stripe_agentic_payment_captured', $order, $result );

		} catch ( Exception $e ) {
			$error_message = $e->getMessage();

			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Unable to capture payment: %s', 'woocommerce-gateway-stripe' ),
					$error_message
				)
			);

			WC_Stripe_Logger::log(
				'Agentic capture failed',
				array(
					'order_id'  => $order->get_id(),
					'intent_id' => $intent_id,
					'error'     => $error_message,
				)
			);

			// Fire action for error handling.
			do_action( 'wc_stripe_agentic_payment_capture_failed', $order, $error_message );
		}
	}
}
