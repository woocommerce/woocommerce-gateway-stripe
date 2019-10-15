<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Pre-Orders.
 *
 */
class WC_Stripe_Pre_Orders_Compat extends WC_Stripe_Payment_Gateway {
	public $saved_cards;

	public function __construct() {
		$this->saved_cards = WC_Stripe_Helper::get_settings( 'stripe', 'saved_cards' );
	}

	/**
	 * Is $order_id a pre-order?
	 * @param  int  $order_id
	 * @return boolean
	 */
	public function is_pre_order( $order_id ) {
		return WC_Pre_Orders_Order::order_contains_pre_order( $order_id );
	}

	/**
	 * Remove order meta
	 * @param object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
			delete_post_meta( $order->id, '_stripe_source_id' );
			// For BW compat will remove in the future.
			delete_post_meta( $order->id, '_stripe_card_id' );
		} else {
			$order->delete_meta_data( '_stripe_source_id' );
			$order->delete_meta_data( '_stripe_card_id' );
			$order->save();
		}
	}

	/**
	 * Process the pre-order when pay upon release is used.
	 * @param int $order_id
	 */
	public function process_pre_order( $order_id ) {
		try {
			$order = wc_get_order( $order_id );

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			$prepared_source = $this->prepare_source( get_current_user_id(), true );

			// We need a source on file to continue.
			if ( empty( $prepared_source->customer ) || empty( $prepared_source->source ) ) {
				throw new WC_Stripe_Exception( __( 'Unable to store payment details. Please try again.', 'woocommerce-gateway-stripe' ) );
			}

			// Setup the response early to allow later modifications.
			$response = array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

			$this->save_source_to_order( $order, $prepared_source );

			// Try setting up a payment intent.
			$intent_secret = $this->setup_intent( $order, $prepared_source );
			if ( ! empty( $intent_secret ) ) {
				$response['setup_intent_secret'] = $intent_secret;
				return $response;
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Is pre ordered!
			WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

			// Return thank you page redirect
			return $response;
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Pre Orders Error: ' . $e->getMessage() );

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released.
	 *
	 * @param WC_Order $order
	 * @param bool $retry
	 *
	 * @return void
	 */
	public function process_pre_order_release_payment( $order, $retry = true ) {
		try {
			$source   = $this->prepare_order_source( $order );
			$response = $this->create_and_confirm_intent_for_off_session( $order, $source );

			$is_authentication_required = $this->is_authentication_required_for_payment( $response );

			if ( ! empty( $response->error ) && ! $is_authentication_required ) {
				if ( ! $retry ) {
					throw new Exception( $response->error->message );
				}
				$this->remove_order_source_before_retry( $order );
				$this->process_pre_order_release_payment( $order, false );
			} else if ( $is_authentication_required ) {
				$charge = end( $response->error->payment_intent->charges->data );
				$id = $charge->id;
				$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $id ) : $order->set_transaction_id( $id );
				$order->update_status( 'failed', sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'woocommerce-gateway-stripe' ), $id ) );
				if ( is_callable( array( $order, 'save' ) ) ) {
					$order->save();
				}

				WC_Emails::instance();

				do_action( 'wc_gateway_stripe_process_payment_authentication_required', $order );

				throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
			} else {
				// Successful
				$this->process_response( end( $response->charges->data ), $order );
			}
		} catch ( Exception $e ) {
			$error_message = is_callable( array( $e, 'getLocalizedMessage' ) ) ? $e->getLocalizedMessage() : $e->getMessage();
			/* translators: error message */
			$order_note = sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $error_message );

			// Mark order as failed if not already set,
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			if ( ! $order->has_status( 'failed' ) ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}
	}
}
