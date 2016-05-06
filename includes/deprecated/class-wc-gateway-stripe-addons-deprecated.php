<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe_Addons class.
 *
 * @extends WC_Gateway_Stripe
 */
class WC_Gateway_Stripe_Addons_Deprecated extends WC_Gateway_Stripe_Addons {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'process_scheduled_subscription_payment' ), 10, 3 );

			add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'remove_renewal_order_meta' ), 10, 4 );
			add_action( 'woocommerce_subscriptions_changed_failing_payment_method_stripe', array( $this, 'change_failing_payment_method' ), 10, 3 );

			// display the current payment method used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_recurring_payment_method', array( $this, 'my_subscriptions_recurring_payment_method' ), 10, 3 );
		}
	}

	/**
	 * Store the customer and card IDs on the order
	 *
	 * @param int $order_id
	 * @return array
	 */
	protected function save_meta( $order_id, $customer_id, $card_id ) {
		update_post_meta( $order_id, '_stripe_customer_id', $customer_id );
		update_post_meta( $order_id, '_stripe_card_id', $card_id );
	}

	/**
	 * Process the payment
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {
		// Processing subscription
		if ( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {
			return $this->process_subscription( $order_id, $retry );

		// Processing pre-order or standard ordre
		} else {
			return parent::process_payment( $order_id, $retry );
		}
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @param $product_id int The ID of the subscription product for which this payment relates.
	 * @access public
	 * @return void
	 */
	public function process_scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		if ( is_wp_error( $result ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}

	/**
	 * process_subscription_payment function.
	 *
	 * @access public
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param string $stripe_token (default: '')
	 * @param  bool initial_payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0, $initial_payment = false ) {
		$order_items       = $order->get_items();
		$order_item        = array_shift( $order_items );
		$subscription_name = sprintf( __( 'Subscription for "%s"', 'woocommerce-gateway-stripe' ), $order_item['name'] ) . ' ' . sprintf( __( '(Order %s)', 'woocommerce-gateway-stripe' ), $order->get_order_number() );

		if ( $amount * 100 < 50 ) {
			return new WP_Error( 'stripe_error', __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
		}

		// We need a customer in Stripe. First, look for the customer ID linked to the USER.
		$user_id         = $order->customer_user;
		$stripe_customer = get_user_meta( $user_id, '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ! $stripe_customer || ! is_string( $stripe_customer ) ) {
			$stripe_customer = get_post_meta( $order->id, '_stripe_customer_id', true );
		}

		// Or fail :(
		if ( ! $stripe_customer ) {
			return new WP_Error( 'stripe_error', __( 'Customer not found', 'woocommerce-gateway-stripe' ) );
		}

		$currency            = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
		$stripe_payment_args = array(
			'amount'      => $this->get_stripe_amount( $amount, $currency ),
			'currency'    => $currency,
			'description' => $subscription_name,
			'customer'    => $stripe_customer,
			'expand[]'    => 'balance_transaction',
			'capture'     => ( $this->capture || ! $initial_payment ) ? 'true' : 'false'
		);

		// See if we're using a particular card
		if ( $card_id = get_post_meta( $order->id, '_stripe_card_id', true ) ) {
			$stripe_payment_args['source'] = $card_id;
		}

		// Charge the customer
		$response = $this->stripe_request( $stripe_payment_args, 'charges' );

		if ( ! is_wp_error( $response ) ) {
			update_post_meta( $order->id, '_stripe_charge_id', $response->id );
			add_post_meta( $order->id, '_transaction_id', $response->id, true );

			if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
				$fee = number_format( $response->balance_transaction->fee / 100, 2, '.', '' );
				update_post_meta( $order->id, 'Stripe Fee', $fee );
				update_post_meta( $order->id, 'Net Revenue From Stripe', $order->order_total - $fee );
			}

			if ( $response->captured ) {
				update_post_meta( $order->id, '_stripe_charge_captured', 'yes' );
				$order->add_order_note( sprintf( __( 'Stripe subscription charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id ) );
				$order->payment_complete( $response->id );
			} else {
				update_post_meta( $order->id, '_stripe_charge_captured', 'no' );
				$order->add_order_note( sprintf( __( 'Stripe subscription charge authorized (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id ) );
				$order->reduce_order_stock();
			}
		}

		return $response;
	}

	/**
	 * Don't transfer Stripe customer/token meta when creating a parent renewal order.
	 *
	 * @access public
	 * @param array $order_meta_query MySQL query for pulling the metadata
	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
	 * @return void
	 */
	public function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
		if ( 'parent' == $new_order_role ) {
			$order_meta_query .= " AND `meta_key` NOT IN ( '_stripe_customer_id', '_stripe_card_id', 'Stripe Fee', 'Net Revenue From Stripe', 'Stripe Payment ID' ) ";
		}
		return $order_meta_query;
	}

	/**
	 * Update the customer_id for a subscription after using Stripe to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 * @param WC_Order $original_order The original order in which the subscription was purchased.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @param string $subscription_key A subscription key of the form created by @see WC_Subscriptions_Manager::get_subscription_key()
	 * @return void
	 */
	public function change_failing_payment_method( $original_order, $renewal_order, $subscription_key ) {
		$new_customer_id = get_post_meta( $renewal_order->id, '_stripe_customer_id', true );
		$new_card_id     = get_post_meta( $renewal_order->id, '_stripe_card_id', true );
		update_post_meta( $original_order->id, '_stripe_customer_id', $new_customer_id );
		update_post_meta( $original_order->id, '_stripe_card_id', $new_card_id );
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param array $subscription_details the subscription details
	 * @param WC_Order $order the order containing the subscription
	 * @return string the subscription payment method
	 */
	public function my_subscriptions_recurring_payment_method( $payment_method_to_display, $subscription_details, $order ) {
		// bail for other payment methods
		if ( $this->id !== $order->recurring_payment_method || ! $order->customer_user ) {
			return $payment_method_to_display;
		}

		$user_id         = $order->customer_user;
		$stripe_customer = get_user_meta( $user_id, '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ! $stripe_customer || ! is_string( $stripe_customer ) ) {
			$stripe_customer = get_post_meta( $order->id, '_stripe_customer_id', true );
		}

		// Card specified?
		$stripe_card = get_post_meta( $order->id, '_stripe_card_id', true );

		// Get cards from API
		$cards       = $this->get_saved_cards( $stripe_customer );

		if ( $cards ) {
			$found_card = false;
			foreach ( $cards as $card ) {
				if ( $card->id === $stripe_card ) {
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
