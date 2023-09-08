<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Pre-Orders compatibility.
 */
trait WC_Stripe_Pre_Orders_Trait {

	/**
	 * Initialize pre-orders hook.
	 *
	 * @since 5.8.0
	 */
	public function maybe_init_pre_orders() {
		if ( ! $this->is_pre_orders_enabled() ) {
			return;
		}

		$this->supports[] = 'pre-orders';

		add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, [ $this, 'process_pre_order_release_payment' ] );
	}

	/**
	 * Checks if pre-orders are enabled on the site.
	 *
	 * @since 5.8.0
	 *
	 * @return bool
	 */
	public function is_pre_orders_enabled() {
		return class_exists( 'WC_Pre_Orders' );
	}

	/**
	 * Is $order_id a pre-order?
	 *
	 * @since 5.8.0
	 *
	 * @param  int $order_id
	 * @return bool
	 */
	public function has_pre_order( $order_id ) {
		return $this->is_pre_orders_enabled() && class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id );
	}

	/**
	 * Returns boolean on whether current cart contains a pre-order item.
	 *
	 * @since 5.8.0
	 *
	 * @return bool
	 */
	public function is_pre_order_item_in_cart() {
		return $this->is_pre_orders_enabled() && class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order();
	}

	/**
	 * Returns pre-order product from cart.
	 *
	 * @since 5.8.0
	 *
	 * @return object|null
	 */
	public function get_pre_order_product_from_cart() {
		if ( ! $this->is_pre_orders_enabled() || ! class_exists( 'WC_Pre_Orders_Cart' ) ) {
			return false;
		}
		return WC_Pre_Orders_Cart::get_pre_order_product();
	}

	/**
	 * Returns pre-order product from order.
	 *
	 * @since 5.8.0
	 *
	 * @param int $order_id
	 *
	 * @return object|null
	 */
	public function get_pre_order_product_from_order( $order_id ) {
		if ( ! $this->is_pre_orders_enabled() || ! class_exists( 'WC_Pre_Orders_Order' ) ) {
			return false;
		}
		return WC_Pre_Orders_Order::get_pre_order_product( $order_id );
	}

	/**
	 * Returns boolean on whether product is charged upon release.
	 *
	 * @since 5.8.0
	 *
	 * @param object $product
	 *
	 * @return bool
	 */
	public function is_pre_order_product_charged_upon_release( $product ) {
		return $this->is_pre_orders_enabled() && class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product );
	}

	/**
	 * Returns boolean on whether product is charged upfront.
	 *
	 * @since 5.8.0
	 *
	 * @param object $product
	 *
	 * @return bool
	 */
	public function is_pre_order_product_charged_upfront( $product ) {
		return $this->is_pre_orders_enabled() && class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upfront( $product );
	}

	/**
	 * Checks if we need to process pre-orders when
	 * a pre-order product is in the cart.
	 *
	 * @since 5.8.0
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function maybe_process_pre_orders( $order_id ) {
		return (
			$this->has_pre_order( $order_id ) &&
			WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id )
		);
	}

	/**
	 * Remove order meta.
	 *
	 * @param object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		$order->delete_meta_data( '_stripe_source_id' );
		$order->delete_meta_data( '_stripe_card_id' );
		$order->save();
	}

	/**
	 * Marks the order as pre-ordered.
	 * The native function is wrapped so we can call it separately and more easily mock it in our tests.
	 *
	 * @param object $order
	 */
	public function mark_order_as_pre_ordered( $order ) {
		if ( ! class_exists( 'WC_Pre_Orders_Order' ) ) {
			return;
		}
		WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
	}

	/**
	 * Process the pre-order when pay upon release is used.
	 *
	 * @param int $order_id
	 *
	 * @return array
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
			$response = [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

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
			$this->mark_order_as_pre_ordered( $order );

			// Return thank you page redirect
			return $response;
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Pre Orders Error: ' . $e->getMessage() );

			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			];
		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released.
	 *
	 * @param WC_Order $order
	 * @param bool     $retry
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
			} elseif ( $is_authentication_required ) {
				$charge = end( $response->error->payment_intent->charges->data );
				$id     = $charge->id;

				$order->set_transaction_id( $id );
				/* translators: %s is the charge Id */
				$order->update_status( 'failed', sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'woocommerce-gateway-stripe' ), $id ) );
				if ( is_callable( [ $order, 'save' ] ) ) {
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
			$error_message = is_callable( [ $e, 'getLocalizedMessage' ] ) ? $e->getLocalizedMessage() : $e->getMessage();
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
