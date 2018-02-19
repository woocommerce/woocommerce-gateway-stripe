<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Subscriptions and Pre-Orders.
 *
 * @extends WC_Gateway_Stripe_Sepa
 */
class WC_Stripe_Sepa_Compat extends WC_Gateway_Stripe_Sepa {
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

			// Display the credit card used for a subscription in the "My Subscriptions" table.
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// Allow store managers to manually set Stripe as the payment method on a subscription.
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
			add_filter( 'wc_stripe_display_save_payment_method_checkbox', array( $this, 'maybe_hide_save_checkbox' ) );
			add_filter( 'wc_stripe_payment_metadata', array( $this, 'add_subscription_meta_data' ), 10, 2 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}
	}

	/**
	 * Checks to see if we need to hide the save checkbox field.
	 * Because when cart contains a subs product, it will save regardless.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function maybe_hide_save_checkbox( $display_tokenization ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}

		return $display_tokenization;
	}

	/**
	 * Is $order_id a subscription?
	 * @param  int  $order_id
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
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
	public function process_payment( $order_id, $retry = true, $force_save_source = false ) {
		if ( $this->has_subscription( $order_id ) ) {
			// Regular payment with force customer enabled.
			return parent::process_payment( $order_id, true, true );
		} elseif ( $this->is_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id, $retry, $force_save_source );
		} else {
			return parent::process_payment( $order_id, $retry, $force_save_source );
		}
	}

	/**
	 * Adds subscription related meta data on charge request.
	 *
	 * @since 4.0.0
	 * @param array $metadata
	 * @param object $order
	 */
	public function add_subscription_meta_data( $metadata, $order ) {
		if ( ! $this->has_subscription( WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id() ) ) {
			return $metadata;
		}

		return $metadata += array(
			'payment_type'   => 'recurring',
			'site_url'       => esc_url( get_site_url() ),
		);
	}

	/**
	 * Updates other subscription sources.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function save_source_to_order( $order, $source ) {
		parent::save_source_to_order( $order, $source );

		$order_id  = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = array();
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription_id = WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id();
			update_post_meta( $subscription_id, '_stripe_customer_id', $source->customer );
			update_post_meta( $subscription_id, '_stripe_source_id', $source->source );
		}
	}

	/**
	 * Process_subscription_payment function.
	 *
	 * @since 3.0
	 * @since 4.0.4 Add third parameter flag to retry.
	 * @param float $amount
	 * @param mixed $renewal_order
	 * @param bool $is_retry Is this a retry process.
	 */
	public function process_subscription_payment( $amount = 0.0, $renewal_order, $is_retry = false ) {
		if ( $amount * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			/* translators: minimum amount */
			return new WP_Error( 'stripe_error', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
		}

		$order_id = WC_Stripe_Helper::is_pre_30() ? $renewal_order->id : $renewal_order->get_id();

		// Get source from order.
		$prepared_source = $this->prepare_order_source( $renewal_order );

		if ( ! $prepared_source->customer ) {
			return new WP_Error( 'stripe_error', __( 'Customer not found', 'woocommerce-gateway-stripe' ) );
		}

		WC_Stripe_Logger::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

		if ( $is_retry ) {
			// Passing empty source with charge customer default.
			$prepared_source->source = '';
		}

		$request            = $this->generate_payment_request( $renewal_order, $prepared_source );
		$request['capture'] = 'true';
		$request['amount']  = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
		$response           = WC_Stripe_API::request( $request );

		if ( ! empty( $response->error ) || is_wp_error( $response ) ) {
			if ( $is_retry ) {
				/* translators: error message */
				$renewal_order->update_status( 'failed', sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $response->error->message ) );
			}

			return $response; // Default catch all errors.
		}

		$this->process_response( $response, $renewal_order );

		if ( ! $is_retry ) {
			return $response;
		}
	}

	/**
	 * Don't transfer Stripe customer/token meta to resubscribe orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $resubscribe_order->id : $resubscribe_order->get_id() ), '_stripe_customer_id' );
		delete_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $resubscribe_order->id : $resubscribe_order->get_id() ), '_stripe_source_id' );
		// For BW compat will remove in future
		delete_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $resubscribe_order->id : $resubscribe_order->get_id() ), '_stripe_card_id' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * Don't transfer Stripe fee/ID meta to renewal orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_renewal_meta( $renewal_order ) {
		delete_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $renewal_order->id : $renewal_order->get_id() ), 'Stripe Fee' );
		delete_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $renewal_order->id : $renewal_order->get_id() ), 'Net Revenue From Stripe' );
		return $renewal_order;
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$response = $this->process_subscription_payment( $amount_to_charge, $renewal_order );

		if ( is_wp_error( $response ) ) {
			/* translators: error message */
			$renewal_order->update_status( 'failed', sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
		}

		if ( ! empty( $response->error ) ) {
			// This is a very generic error to listen for but worth a retry before total fail.
			if ( isset( $response->error->type ) && 'invalid_request_error' === $response->error->type && apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
				$this->process_subscription_payment( $amount_to_charge, $renewal_order, true );
			} else {
				/* translators: error message */
				$renewal_order->update_status( 'failed', sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $response->error->message ) );
			}
		}
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_stripe_source_id' );
		// For BW compat will remove in the future.
		delete_post_meta( $order_id, '_stripe_card_id' );
	}

	/**
	 * Remove order meta
	 * @param object $order
	 */
	public function remove_order_customer_before_retry( $order ) {
		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_stripe_customer_id' );
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
		if ( WC_Stripe_Helper::is_pre_30() ) {
			update_post_meta( $subscription->id, '_stripe_customer_id', $renewal_order->stripe_customer_id );
			update_post_meta( $subscription->id, '_stripe_source_id', $renewal_order->stripe_source_id );

		} else {
			update_post_meta( $subscription->get_id(), '_stripe_customer_id', $renewal_order->get_meta( '_stripe_customer_id', true ) );
			update_post_meta( $subscription->get_id(), '_stripe_source_id', $renewal_order->get_meta( '_stripe_source_id', true ) );
		}
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
		$source_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id() ), '_stripe_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $source_id ) ) {
			$source_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id() ), '_stripe_card_id', true );

			// Take this opportunity to update the key name.
			WC_Stripe_Helper::is_pre_30() ? update_post_meta( $subscription->id, '_stripe_source_id', $source_id ) : update_post_meta( $subscription->get_id(), '_stripe_source_id', $source_id );
		}

		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_stripe_customer_id' => array(
					'value' => get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id() ), '_stripe_customer_id', true ),
					'label' => 'Stripe Customer ID',
				),
				'_stripe_source_id' => array(
					'value' => $source_id,
					'label' => 'Stripe Source ID',
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
				throw new Exception( __( 'A "Stripe Customer ID" value is required.', 'woocommerce-gateway-stripe' ) );
			} elseif ( 0 !== strpos( $payment_meta['post_meta']['_stripe_customer_id']['value'], 'cus_' ) ) {
				throw new Exception( __( 'Invalid customer ID. A valid "Stripe Customer ID" must begin with "cus_".', 'woocommerce-gateway-stripe' ) );
			}

			if (
				( ! empty( $payment_meta['post_meta']['_stripe_source_id']['value'] )
				&& 0 !== strpos( $payment_meta['post_meta']['_stripe_source_id']['value'], 'card_' ) )
				&& ( ! empty( $payment_meta['post_meta']['_stripe_source_id']['value'] )
				&& 0 !== strpos( $payment_meta['post_meta']['_stripe_source_id']['value'], 'src_' ) ) ) {

				throw new Exception( __( 'Invalid source ID. A valid source "Stripe Source ID" must begin with "src_" or "card_".', 'woocommerce-gateway-stripe' ) );
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
		$customer_user = WC_Stripe_Helper::is_pre_30() ? $subscription->customer_user : $subscription->get_customer_id();

		// bail for other payment methods
		if ( ( WC_Stripe_Helper::is_pre_30() ? $subscription->payment_method : $subscription->get_payment_method() ) !== $this->id || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$stripe_source_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id() ), '_stripe_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $stripe_source_id ) ) {
			$stripe_source_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id() ), '_stripe_card_id', true );

			// Take this opportunity to update the key name.
			WC_Stripe_Helper::is_pre_30() ? update_post_meta( $subscription->id, '_stripe_source_id', $stripe_source_id ) : update_post_meta( $subscription->get_id(), '_stripe_source_id', $stripe_source_id );
		}

		$stripe_customer    = new WC_Stripe_Customer();
		$stripe_customer_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->id : $subscription->get_id() ), '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the subscription, fallback to the user meta data.
		if ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) {
			$user_id            = $customer_user;
			$stripe_customer_id = get_user_meta( $user_id, '_stripe_customer_id', true );
			$stripe_source_id   = get_user_meta( $user_id, '_stripe_source_id', true );

			// For BW compat will remove in future.
			if ( empty( $stripe_source_id ) ) {
				$stripe_source_id = get_user_meta( $user_id, '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				update_user_meta( $user_id, '_stripe_source_id', $stripe_source_id );
			}
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) && false !== $subscription->order ) {
			$stripe_customer_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->order->id : $subscription->get_parent_id() ), '_stripe_customer_id', true );
			$stripe_source_id   = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->order->id : $subscription->get_parent_id() ), '_stripe_source_id', true );

			// For BW compat will remove in future.
			if ( empty( $stripe_source_id ) ) {
				$stripe_source_id = get_post_meta( ( WC_Stripe_Helper::is_pre_30() ? $subscription->order->id : $subscription->get_parent_id() ), '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				WC_Stripe_Helper::is_pre_30() ? update_post_meta( $subscription->order->id, '_stripe_source_id', $stripe_source_id ) : update_post_meta( $subscription->get_parent_id(), '_stripe_source_id', $stripe_source_id );
			}
		}

		$stripe_customer->set_id( $stripe_customer_id );
		$sources = $stripe_customer->get_sources();

		if ( $sources ) {
			$found_source = false;
			foreach ( $sources as $source ) {
				if ( isset( $source->type ) && 'card' === $source->type ) {
					$card = $source->card;
				}

				if ( $source->id === $stripe_source_id ) {
					$found_source = true;

					if ( $card ) {
						/* translators: 1) card brand 2) last 4 digits */
						$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ), ( isset( $card->brand ) ? $card->brand : __( 'N/A', 'woocommerce-gateway-stripe' ) ), $card->last4 );
					} else {
						$payment_method_to_display = __( 'N/A', 'woocommerce-gateway-stripe' );
					}
					break;
				}
			}

			if ( ! $found_source ) {
				if ( 'card' === $sources[0]->type ) {
					$card = $sources[0]->card;
				}

				if ( $card ) {
					/* translators: 1) card brand 2) last 4 digits */
					$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ), ( isset( $card->brand ) ? $card->brand : __( 'N/A', 'woocommerce-gateway-stripe' ) ), $card->last4 );
				} else {
					$payment_method_to_display = __( 'N/A', 'woocommerce-gateway-stripe' );
				}
			}
		}

		return $payment_method_to_display;
	}

	/**
	 * Process the pre-order
	 * @param int $order_id
	 * @return array
	 */
	public function process_pre_order( $order_id, $retry, $force_save_source ) {
		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			try {
				$order = wc_get_order( $order_id );

				if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
					/* translators: minimum amount */
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
				}

				$prepared_source = $this->prepare_source( get_current_user_id(), true );

				// We need a source on file to continue.
				if ( empty( $prepared_source->customer ) || empty( $prepared_source->source ) ) {
					throw new Exception( __( 'Unable to store payment details. Please try again.', 'woocommerce-gateway-stripe' ) );
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
			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
				return;
			}
		} else {
			return parent::process_payment( $order_id, $retry, $force_save_source );
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
	}
}
