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
		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_stripe_source_id' );
		// For BW compat will remove in the future.
		delete_post_meta( $order_id, '_stripe_card_id' );
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_customer_before_retry( $order ) {
		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_stripe_customer_id' );
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

			$this->save_source_to_order( $order, $prepared_source );

			// Remove cart
			WC()->cart->empty_cart();

			// Is pre ordered!
			WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
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
	 * @param WC_Order $order
	 * @return void
	 */
	public function process_pre_order_release_payment( $order ) {
		add_filter( 'wc_stripe_idempotency_key', array( $this, 'change_idempotency_key' ), 10, 2 );

		try {
			// Define some callbacks if the first attempt fails.
			$retry_callbacks = array(
				'remove_order_source_before_retry',
				'remove_order_customer_before_retry',
			);

			while ( 1 ) {
				$source   = $this->prepare_order_source( $order );
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source ) );

				if ( ! empty( $response->error ) ) {
					if ( 0 === sizeof( $retry_callbacks ) ) {
						throw new Exception( $response->error->message );
					} else {
						$retry_callback = array_shift( $retry_callbacks );
						call_user_func( array( $this, $retry_callback ), $order );
					}
				} else {
					// Successful
					$this->process_response( $response, $order );
					break;
				}
			}
		} catch ( Exception $e ) {
			/* translators: error message */
			$order_note = sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $e->getMessage() );

			// Mark order as failed if not already set,
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			if ( ! $order->has_status( 'failed' ) ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}

		remove_filter( 'wc_stripe_idempotency_key', array( $this, 'change_idempotency_key' ), 10, 2 );
	}

	/**
	 * Because of retry callbacks, the default idempotency key would remain
	 * the same by default, even tough the request will change (source and/or customer params).
	 * This method will include both the source and the customer in the idempotency key to fix that.
	 *
	 * @param string $idempotency_key WC_Stripe_API's idempotency key.
	 * @param array  $request         All of the request's parameters.
	 * @return string                 An idempotency key, which includes both the customer, and the source.
	 */
	public function change_idempotency_key( $idempotency_key, $request ) {
		$customer = ! empty( $request['customer'] ) ? $request['customer'] : '';
		$source   = ! empty( $request['source'] ) ? $request['source'] : $customer;

		return $request['metadata']['order_id'] . '-' . $source . '-' . $customer;
	}
}
