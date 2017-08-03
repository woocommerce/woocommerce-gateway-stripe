<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Subscriptions.
 *
 * @extends WC_Gateway_Stripe
 */
class WC_Stripe_Subscriptions_Compat extends WC_Gateway_Stripe {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
		add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_stripe', array( $this, 'update_failing_payment_method' ), 10, 2 );

		// display the credit card used for a subscription in the "My Subscriptions" table
		add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

		// allow store managers to manually set Stripe as the payment method on a subscription
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
		add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		add_filter( 'wc_stripe_display_save_payment_method_checkbox', array( $this, 'maybe_hide_save_checkbox' ) );
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

		return true;
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
	 * Process the payment based on type.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		if ( $this->has_subscription( $order_id ) ) {
			// Regular payment with force customer enabled
			return parent::process_payment( $order_id, true, true );

		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}
	}

	/**
	 * Updates other subscription sources.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function save_source( $order, $source ) {
		parent::save_source( $order, $source );

		$order_id  = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order
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
	 * process_subscription_payment function.
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param string $stripe_token (default: '')
	 * @param  bool initial_payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {
		if ( $amount * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			return new WP_Error( 'stripe_error', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
		}

		$customer_id = WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
		$order_id    = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		// Get source from order
		$prepared_source = $this->prepare_order_source( $order );

		// Or fail :(
		if ( ! $prepared_source->customer ) {
			return new WP_Error( 'stripe_error', __( 'Customer not found', 'woocommerce-gateway-stripe' ) );
		}
		
		WC_Stripe_Logger::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

		// Make the request
		$request             = $this->generate_payment_request( $order, $prepared_source );
		$request['capture']  = 'true';
		$request['amount']   = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
		$request['metadata'] += array(
			'payment_type'   => 'recurring',
			'site_url'       => esc_url( get_site_url() ),
		);
		$response            = WC_Stripe_API::request( $request );

		// Process valid response
		if ( is_wp_error( $response ) ) {
			return $response; // Default catch all errors.
		}

		$this->process_response( $response, $order );

		return $response;
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
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
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
	 * @param  object $order
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
				throw new Exception( 'A "_stripe_customer_id" value is required.' );
			} elseif ( 0 !== strpos( $payment_meta['post_meta']['_stripe_customer_id']['value'], 'cus_' ) ) {
				throw new Exception( 'Invalid customer ID. A valid "_stripe_customer_id" must begin with "cus_".' );
			}

			if ( ! empty( $payment_meta['post_meta']['_stripe_source_id']['value'] ) && 0 !== strpos( $payment_meta['post_meta']['_stripe_source_id']['value'], 'src_' ) ) {
				throw new Exception( 'Invalid source ID. A valid "_stripe_source_id" must begin with "src_".' );
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
		if ( $this->id !== ( WC_Stripe_Helper::is_pre_30() ? $subscription->payment_method : $subscription->get_payment_method() ) || ! $customer_user ) {
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
		$cards = $stripe_customer->get_sources();

		if ( $cards ) {
			$found_card = false;
			foreach ( $cards as $card ) {
				if ( $card->id === $stripe_source_id ) {
					$found_card                = true;
					$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ), ( isset( $card->type ) ? $card->type : $card->brand ), $card->last4 );
					break;
				}
			}
			if ( ! $found_card ) {
				$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ), ( isset( $cards[0]->type ) ? $cards[0]->type : $cards[0]->brand ), $cards[0]->last4 );
			}
		}

		return $payment_method_to_display;
	}
}
