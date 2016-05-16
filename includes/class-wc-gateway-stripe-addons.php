<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe_Addons class.
 *
 * @extends WC_Gateway_Stripe
 */
class WC_Gateway_Stripe_Addons extends WC_Gateway_Stripe {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_stripe', array( $this, 'update_failing_payment_method' ), 10, 2 );

			// display the credit card used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// allow store managers to manually set Stripe as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}
	}

	/**
	 * Is $order_id a subscription?
	 * @param  int  $order_id
	 * @return boolean
	 */
	protected function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
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
		if ( $this->is_subscription( $order_id ) ) {
			// Regular payment with force customer enabled
			return parent::process_payment( $order_id, true, true );

		} elseif ( $this->is_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id, $retry, $force_customer );

		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}
	}

	/**
	 * Updates other subscription sources.
	 */
	protected function save_source( $order, $source ) {
		parent::save_source( $order, $source );

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( wcs_order_contains_subscription( $order->id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->id );
		} elseif ( wcs_order_contains_renewal( $order->id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order->id );
		} else {
			$subscriptions = array();
		}

		foreach( $subscriptions as $subscription ) {
			update_post_meta( $subscription->id, '_stripe_customer_id', $source->customer );
			update_post_meta( $subscription->id, '_stripe_card_id', $source->source );
		}
	}

	/**
	 * process_subscription_payment function.
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param string $stripe_token (default: '')
	 * @param  bool initial_payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {
		if ( $amount * 100 < 50 ) {
			return new WP_Error( 'stripe_error', __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
		}

		// Get source from order
		$source = $this->get_order_source( $order );

		// If no order source was defined, use user source instead.
		if ( ! $source->customer ) {
			$source = $this->get_source( $order->customer_user );
		}

		// Or fail :(
		if ( ! $source->customer ) {
			return new WP_Error( 'stripe_error', __( 'Customer not found', 'woocommerce-gateway-stripe' ) );
		}

		WC_Stripe::log( "Info: Begin processing subscriotion payment for order {$order->id} for the amount of {$amount}" );

		// Make the request
		$request             = $this->generate_payment_request( $order, $source );
		$request['capture']  = 'true';
		$request['amount']   = $this->get_stripe_amount( $amount, $request['currency'] );
		$request['metadata'] = array(
			'payment_type'   => 'recurring'
		);
		$response            = WC_Stripe_API::request( $request );

		// Process valid response
		if ( ! is_wp_error( $response ) ) {
			$this->process_response( $response, $order );
		}

		return $response;
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

				if ( $order->get_total() * 100 < 50 ) {
					throw new Exception( __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
				}

				$source = $this->get_source( get_current_user_id(), true );

				// We need a source on file to continue.
				if ( empty( $source->customer ) || empty( $source->source ) ) {
					throw new Exception( __( 'Unable to store payment details. Please try again.', 'woocommerce-gateway-stripe' ) );
				}

				// Store source to order meta
				$this->save_source( $order, $source );

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				WC()->cart->empty_cart();

				// Is pre ordered!
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				// Return thank you page redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
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

	/**
	 * Don't transfer Stripe customer/token meta to resubscribe orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->id, '_stripe_customer_id' );
		delete_post_meta( $resubscribe_order->id, '_stripe_card_id' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * Don't transfer Stripe fee/ID meta to renewal orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_renewal_meta( $renewal_order ) {
		delete_post_meta( $renewal_order->id, 'Stripe Fee' );
		delete_post_meta( $renewal_order->id, 'Net Revenue From Stripe' );
		delete_post_meta( $renewal_order->id, 'Stripe Payment ID' );
		return $renewal_order;
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		// Define some callbacks if the first attempt fails.
		$retry_callbacks = array(
			'remove_order_source_before_retry',
			'remove_order_customer_before_retry',
		);

		while ( 1 ) {
			$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

			if ( is_wp_error( $response ) ) {
				if ( 0 === sizeof( $retry_callbacks ) ) {
					$renewal_order->update_status( 'failed', sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
					break;
				} else {
					$retry_callback = array_shift( $retry_callbacks );
					call_user_func( array( $this, $retry_callback ), $renewal_order );
				}
			} else {
				// Successful
				break;
			}
		}
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		delete_post_meta( $order->id, '_stripe_card_id' );
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_customer_before_retry( $order ) {
		delete_post_meta( $order->id, '_stripe_customer_id' );
	}

	/**
	 * Update the customer_id for a subscription after using Stripe to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->id, '_stripe_customer_id', $renewal_order->stripe_customer_id );
		update_post_meta( $subscription->id, '_stripe_card_id', $renewal_order->stripe_card_id );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_stripe_customer_id' => array(
					'value' => get_post_meta( $subscription->id, '_stripe_customer_id', true ),
					'label' => 'Stripe Customer ID',
				),
				'_stripe_card_id' => array(
					'value' => get_post_meta( $subscription->id, '_stripe_card_id', true ),
					'label' => 'Stripe Card ID',
				),
			),
		);
		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {

			if ( ! isset( $payment_meta['post_meta']['_stripe_customer_id']['value'] ) || empty( $payment_meta['post_meta']['_stripe_customer_id']['value'] ) ) {
				throw new Exception( 'A "_stripe_customer_id" value is required.' );
			} elseif ( 0 !== strpos( $payment_meta['post_meta']['_stripe_customer_id']['value'], 'cus_' ) ) {
				throw new Exception( 'Invalid customer ID. A valid "_stripe_customer_id" must begin with "cus_".' );
			}

			if ( ! empty( $payment_meta['post_meta']['_stripe_card_id']['value'] ) && 0 !== strpos( $payment_meta['post_meta']['_stripe_card_id']['value'], 'card_' ) ) {
				throw new Exception( 'Invalid card ID. A valid "_stripe_card_id" must begin with "card_".' );
			}
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods
		if ( $this->id !== $subscription->payment_method || ! $subscription->customer_user ) {
			return $payment_method_to_display;
		}

		$stripe_customer    = new WC_Stripe_Customer();
		$stripe_customer_id = get_post_meta( $subscription->id, '_stripe_customer_id', true );
		$stripe_card_id     = get_post_meta( $subscription->id, '_stripe_card_id', true );

		// If we couldn't find a Stripe customer linked to the subscription, fallback to the user meta data.
		if ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) {
			$user_id            = $subscription->customer_user;
			$stripe_customer_id = get_user_meta( $user_id, '_stripe_customer_id', true );
			$stripe_card_id     = get_user_meta( $user_id, '_stripe_card_id', true );
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) && false !== $subscription->order ) {
			$stripe_customer_id = get_post_meta( $subscription->order->id, '_stripe_customer_id', true );
			$stripe_card_id     = get_post_meta( $subscription->order->id, '_stripe_card_id', true );
		}

		$stripe_customer->set_id( $stripe_customer_id );
		$cards = $stripe_customer->get_cards();

		if ( $cards ) {
			$found_card = false;
			foreach ( $cards as $card ) {
				if ( $card->id === $stripe_card_id ) {
					$found_card                = true;
					$payment_method_to_display = sprintf( __( 'Via %s card ending in %s', 'woocommerce-gateway-stripe' ), ( isset( $card->type ) ? $card->type : $card->brand ), $card->last4 );
					break;
				}
			}
			if ( ! $found_card ) {
				$payment_method_to_display = sprintf( __( 'Via %s card ending in %s', 'woocommerce-gateway-stripe' ), ( isset( $cards[0]->type ) ? $cards[0]->type : $cards[0]->brand ), $cards[0]->last4 );
			}
		}

		return $payment_method_to_display;
	}
}
