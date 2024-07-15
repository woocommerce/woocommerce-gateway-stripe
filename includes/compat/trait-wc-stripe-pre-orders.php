<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Pre-Orders compatibility.
 */
trait WC_Stripe_Pre_Orders_Trait {

	/**
	 * Stores a flag to indicate if the Pre-order integration hooks have been attached.
	 *
	 * The callbacks attached as part of maybe_init_pre_orders() only need to be attached once to avoid duplication.
	 *
	 * @var bool False by default, true once the callbacks have been attached.
	 */
	private static $has_attached_pre_order_integration_hooks = false;

	/**
	 * Initialize pre-orders hook.
	 *
	 * @since 5.8.0
	 */
	public function maybe_init_pre_orders() {
		if ( ! $this->is_pre_orders_enabled() ) {
			return;
		}

		$this->supports[] = 'pre-orders'; // @phpstan-ignore-line (supports is defined in the classes that use this trait)

		add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, [ $this, 'process_pre_order_release_payment' ] ); // @phpstan-ignore-line (id is defined in the classes that use this trait)

		/**
		 * The callbacks attached below only need to be attached once. We don't need each gateway instance to have its own callback.
		 * Therefore we only attach them once on the main `stripe` gateway and store a flag to indicate that they have been attached.
		 */
		if ( self::$has_attached_pre_order_integration_hooks || WC_Gateway_Stripe::ID !== $this->id ) { // @phpstan-ignore-line (id is defined in the classes that use this trait)
			return;
		}

		add_filter( 'wc_stripe_display_save_payment_method_checkbox', [ $this, 'hide_save_payment_for_pre_orders_charged_upon_release' ] );

		self::$has_attached_pre_order_integration_hooks = true;
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
			class_exists( 'WC_Pre_Orders_Order' ) &&
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
			$this->validate_minimum_order_amount( $order ); // @phpstan-ignore-line (minimum amount is defined in the classes that use this trait)

			$prepared_source = $this->prepare_source( get_current_user_id(), true ); // @phpstan-ignore-line (prepare_source is defined in the classes that use this trait)

			// We need a source on file to continue.
			if ( empty( $prepared_source->customer ) || empty( $prepared_source->source ) ) {
				throw new WC_Stripe_Exception( __( 'Unable to store payment details. Please try again.', 'woocommerce-gateway-stripe' ) );
			}

			// Setup the response early to allow later modifications.
			$response = [
				'result'   => 'success',
				// @phpstan-ignore-next-line (get_return_url is defined in the classes that use this trait)
				'redirect' => $this->get_return_url( $order ),
			];

			$this->save_source_to_order( $order, $prepared_source ); // @phpstan-ignore-line (save_source_to_order is defined in the classes that use this trait)

			// Try setting up a payment intent.
			$intent_secret = $this->setup_intent( $order, $prepared_source ); // @phpstan-ignore-line (setup_intent is defined in the classes that use this trait)
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
			$source   = $this->prepare_order_source( $order ); // @phpstan-ignore-line (prepare_order_source is defined in the classes that use this trait)
			$response = $this->create_and_confirm_intent_for_off_session( $order, $source ); // @phpstan-ignore-line (create_and_confirm_intent_for_off_session is defined in the classes that use this trait)

			$is_authentication_required = $this->is_authentication_required_for_payment( $response ); // @phpstan-ignore-line (is_authentication_required_for_payment is defined in the classes that use this trait)

			if ( ! empty( $response->error ) && ! $is_authentication_required ) {
				if ( ! $retry ) {
					throw new Exception( $response->error->message );
				}
				$this->remove_order_source_before_retry( $order );
				$this->process_pre_order_release_payment( $order, false );
			} elseif ( $is_authentication_required ) {
				$charge = $this->get_latest_charge_from_intent( $response->error->payment_intent );
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
				$this->process_response( $this->get_latest_charge_from_intent( $response ), $order ); // @phpstan-ignore-line (process_response is defined in the classes that use this trait)
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

	/**
	 * Determines if there is a pre-order in the cart and if it is charged upon release.
	 *
	 * @return bool
	 */
	public function is_pre_order_charged_upon_release_in_cart() {
		$pre_order_product = $this->get_pre_order_product_from_cart();
		return $pre_order_product && $this->is_pre_order_product_charged_upon_release( $pre_order_product );
	}

	/**
	 * Determines if an order contains a pre-order and if it is charged upon release.
	 *
	 * @return bool
	 */
	public function has_pre_order_charged_upon_release( $order ) {
		$pre_order_product = $this->get_pre_order_product_from_order( $order );
		return $pre_order_product && $this->is_pre_order_product_charged_upon_release( $pre_order_product );
	}

	/**
	 * Hides the save payment method checkbox when the cart contains a pre-order that is charged upon release.
	 *
	 * @param bool $display_save_option The default value of whether the save payment method checkbox should be displayed.
	 * @return bool Whether the save payment method checkbox should be displayed.
	 */
	public function hide_save_payment_for_pre_orders_charged_upon_release( $display_save_option ) {

		// This function only sets the display param to false, so if it's already hidden or the cart doesn't contain a pre-order, we don't need to do anything.
		if ( ! $display_save_option || ! $this->is_pre_order_item_in_cart() ) {
			return $display_save_option;
		}

		// If the cart contains a pre-order that is charged upon release, we hide the save payment method checkbox because the payment method is force saved.
		if ( $this->is_pre_order_charged_upon_release_in_cart() ) {
			return false;
		}

		return $display_save_option;
	}
}
