<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Pre Orders.
 *
 * @extends WC_Gateway_Stripe
 */
class WC_Stripe_Pre_Orders_Compat extends WC_Gateway_Stripe {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}
	}

	/**
	 * Is $order_id a pre-order?
	 * @param  int  $order_id
	 * @return boolean
	 */
	protected function is_pre_order( $order_id ) {
		return ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) );
	}

	/**
	 * Process the payment based on type.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		if ( $this->is_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id, $retry, $force_customer );

		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}
	}

	/**
	 * Process the pre-order
	 * @param int $order_id
	 * @return array
	 */
	public function process_pre_order( $order_id, $retry, $force_customer ) {
		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			try {
				$order = wc_get_order( $order_id );

				if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
				}

				$source = $this->get_source( get_current_user_id(), true );

				// We need a source on file to continue.
				if ( empty( $source->customer ) || empty( $source->source ) ) {
					throw new Exception( __( 'Unable to store payment details. Please try again.', 'woocommerce-gateway-stripe' ) );
				}

				// Store source to order meta
				$this->save_source( $order, $source );

				// Remove cart
				WC()->cart->empty_cart();

				// Is pre ordered!
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				// Return thank you page redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
				return;
			}
		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released
	 * @param WC_Order $order
	 * @return void
	 */
	public function process_pre_order_release_payment( $order ) {
		try {
			// Define some callbacks if the first attempt fails.
			$retry_callbacks = array(
				'remove_order_source_before_retry',
				'remove_order_customer_before_retry',
			);

			while ( 1 ) {
				$source   = $this->get_order_source( $order );
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source ) );

				if ( is_wp_error( $response ) ) {
					if ( 0 === sizeof( $retry_callbacks ) ) {
						throw new Exception( $response->get_error_message() );
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
			$order_note = sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $e->getMessage() );

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
