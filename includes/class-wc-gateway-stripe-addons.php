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
     * Process the subscription
     *
	 * @param int $order_id
	 * @return array
     */
	public function process_subscription( $order_id, $retry = true ) {
		$order        = wc_get_order( $order_id );
		$stripe_token = isset( $_POST['stripe_token'] ) ? wc_clean( $_POST['stripe_token'] ) : '';
		$card_id      = isset( $_POST['stripe_card_id'] ) ? wc_clean( $_POST['stripe_card_id'] ) : '';
		$customer_id  = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) : 0;

		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}

		// Use Stripe CURL API for payment
		try {
			$post_data = array();

			// Pay using a saved card!
			if ( $card_id !== 'new' && $card_id && $customer_id ) {
				$post_data['customer'] = $customer_id;
				$post_data['source']   = $card_id;
			}

			// If not using a saved card, we need a token
			elseif ( empty( $stripe_token ) ) {
				$error_msg = __( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'woocommerce-gateway-stripe' );

				if ( $this->testmode ) {
					$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-gateway-stripe' );
				}

				throw new Exception( $error_msg );
			}

			// Save token
			if ( ! $customer_id ) {
				$customer_id = $this->add_customer( $order, $stripe_token );

				if ( is_wp_error( $customer_id ) ) {
					throw new Exception( $customer_id->get_error_message() );
				}

				unset( $post_data['source'] );
				$post_data['customer'] = $customer_id;

			} elseif ( ! $card_id || $card_id === 'new' ) {
				$card_id = $this->add_card( $customer_id, $stripe_token );

				if ( is_wp_error( $card_id ) ) {
					// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id and retry.
					if ( 'customer' === $card_id->get_error_code() && $retry ) {
						delete_user_meta( get_current_user_id(), '_stripe_customer_id' );
						return $this->process_subscription( $order_id, false ); // false to prevent retry again (endless loop)
					}
					throw new Exception( $card_id->get_error_message() );
				}

				$post_data['source']   = $card_id;
				$post_data['customer'] = $customer_id;
			}

			// Store the ID in the order
			$this->save_meta( $order_id, $customer_id, $card_id );

			if ( $order->get_total() > 0 ) {
				$payment_response = $this->process_subscription_payment( $order, $order->get_total(), true );

				if ( is_wp_error( $payment_response ) ) {
					throw new Exception( $payment_response->get_error_message() );
				}

			} else {
				$order->payment_complete();
			}

			WC()->cart->empty_cart();

			// Return thank you page redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {
			wc_add_notice( __('Error:', 'woocommerce-gateway-stripe') . ' "' . $e->getMessage() . '"', 'error' );
			return;
		}
	}

	/**
	 * Store the customer and card IDs on the order and subscriptions in the order
	 *
	 * @param int $order_id
	 * @return array
	 */
	protected function save_meta( $order_id, $customer_id, $card_id ) {
		update_post_meta( $order_id, '_stripe_customer_id', $customer_id );
		update_post_meta( $order_id, '_stripe_card_id', $card_id );

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = array();
		}

		foreach( $subscriptions as $subscription ) {
			update_post_meta( $subscription->id, '_stripe_customer_id', $customer_id );
			update_post_meta( $subscription->id, '_stripe_card_id', $card_id );
		}
	}

	/**
	 * Don't transfer Stripe customer/token meta to resubscribe orders.
	 *
	 * @access public
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->id, '_stripe_customer_id' );
		delete_post_meta( $resubscribe_order->id, '_stripe_card_id' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * Don't transfer Stripe fee/ID meta to renewal orders.
	 *
	 * @access public
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 * @return void
	 */
	public function delete_renewal_meta( $renewal_order ) {
		delete_post_meta( $renewal_order->id, 'Stripe Fee' );
		delete_post_meta( $renewal_order->id, 'Net Revenue From Stripe' );
		delete_post_meta( $renewal_order->id, 'Stripe Payment ID' );
		return $renewal_order;
	}

	/**
	 * Process the pre-order
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_pre_order( $order_id, $retry = true ) {
		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			$order        = wc_get_order( $order_id );
			$stripe_token = isset( $_POST['stripe_token'] ) ? wc_clean( $_POST['stripe_token'] ) : '';
			$card_id      = isset( $_POST['stripe_card_id'] ) ? wc_clean( $_POST['stripe_card_id'] ) : '';
			$customer_id  = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) : 0;

			if ( ! $customer_id || ! is_string( $customer_id ) ) {
				$customer_id = 0;
			}

			try {
				$post_data = array();

				// Check amount
				if ( $order->order_total * 100 < 50 ) {
					throw new Exception( __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
				}

				// Pay using a saved card!
				if ( $card_id !== 'new' && $card_id && $customer_id ) {
					$post_data['customer'] = $customer_id;
					$post_data['source']   = $card_id;
				}

				// If not using a saved card, we need a token
				elseif ( empty( $stripe_token ) ) {
					$error_msg = __( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'woocommerce-gateway-stripe' );

					if ( $this->testmode ) {
						$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-gateway-stripe' );
					}

					throw new Exception( $error_msg );
				}

				// Save token
				if ( ! $customer_id ) {
					$customer_id = $this->add_customer( $order, $stripe_token );

					if ( is_wp_error( $customer_id ) ) {
						throw new Exception( $customer_id->get_error_message() );
					}

					unset( $post_data['source'] );
					$post_data['customer'] = $customer_id;

				} elseif ( ! $card_id || $card_id === 'new' ) {
					$card_id = $this->add_card( $customer_id, $stripe_token );

					if ( is_wp_error( $card_id ) ) {
						// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id and retry.
						if ( 'customer' === $card_id->get_error_code() && $retry ) {
							delete_user_meta( get_current_user_id(), '_stripe_customer_id' );
							return $this->process_pre_order( $order_id, false ); // false to prevent retry again (endless loop)
						}
						throw new Exception( $card_id->get_error_message() );
					}

					$post_data['source']   = $card_id;
					$post_data['customer'] = $customer_id;
				}

				// Store the ID in the order
				update_post_meta( $order->id, '_stripe_customer_id', $customer_id );

				// Store the ID in the order
				update_post_meta( $order->id, '_stripe_card_id', $card_id );

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
				if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '>=' ) ) {
					wc_add_notice( $e->getMessage(), 'error' );

				} else {
					WC()->add_error( $e->getMessage() );
				}

				return;
			}
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * Process the payment
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {
		// Processing subscription
		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) ) {
			return $this->process_subscription( $order_id, $retry );

		// Processing pre-order
		} elseif ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id, $retry );

		// Processing regular product
		} else {
			return parent::process_payment( $order_id, $retry );
		}
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 * @access public
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
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

		if ( $amount * 100 < 50 ) {
			return new WP_Error( 'stripe_error', __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
		}

		$stripe_customer = false;

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
			'description' => sprintf( __( '%s - Order %s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
			'customer'    => $stripe_customer,
			'expand[]'    => 'balance_transaction',
			'capture'     => ( $this->capture || ! $initial_payment ) ? 'true' : 'false',
			'metadata'    => array(
				'payment_type' => ( $initial_payment ) ? 'initial' : 'recurring',
			),
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

				$order->payment_complete( $response->id );

				$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id ) );

			} else {

				update_post_meta( $order->id, '_stripe_charge_captured', 'no' );
				add_post_meta( $order->id, '_transaction_id', $response->id, true );

				$order->update_status( 'on-hold', sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' ), $response->id ) );

				$order->reduce_order_stock();
			}
		}

		return $response;
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

		$stripe_customer = get_user_meta( $subscription->id, '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the subscription, fallback to the user meta data.
		if ( ! $stripe_customer || ! is_string( $stripe_customer ) ) {
			$user_id         = $subscription->customer_user;
			$stripe_customer = get_user_meta( $user_id, '_stripe_customer_id', true );
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer || ! is_string( $stripe_customer ) ) && false !== $subscription->order ) {
			$stripe_customer = get_post_meta( $subscription->order->id, '_stripe_customer_id', true );
		}

		// Card specified?
		$stripe_card = get_post_meta( $subscription->id, '_stripe_card_id', true );

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ! $stripe_card && false !== $subscription->order ) {
			$stripe_card = get_post_meta( $subscription->order->id, '_stripe_card_id', true );
		}

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
	 * Process a pre-order payment when the pre-order is released
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function process_pre_order_release_payment( $order ) {
		try {
			$post_data                = array();
			$post_data['customer']    = get_post_meta( $order->id, '_stripe_customer_id', true );
			$post_data['source']      = get_post_meta( $order->id, '_stripe_card_id', true );
			$post_data['currency']    = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
			$post_data['amount']      = $this->get_stripe_amount( $order->order_total, $post_data['currency'] );
			$post_data['description'] = sprintf( __( '%s - Order %s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
			$post_data['capture']     = $this->capture ? 'true' : 'false';
			$post_data['expand[]']    = 'balance_transaction';

			if ( empty( $post_data['source'] ) ) {
				unset( $post_data['source'] );
			}

			// Make the request
			$response = $this->stripe_request( $post_data, 'charges' );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			// Store charge ID
			update_post_meta( $order->id, '_stripe_charge_id', $response->id );

			// Store other data such as fees
			update_post_meta( $order->id, 'Stripe Payment ID', $response->id );

			if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
				$fee = number_format( $response->balance_transaction->fee / 100, 2, '.', '' );
				update_post_meta( $order->id, 'Stripe Fee', $fee );
				update_post_meta( $order->id, 'Net Revenue From Stripe', $order->order_total - $fee );
			}

			if ( $response->captured ) {

				// Store captured value
				update_post_meta( $order->id, '_stripe_charge_captured', 'yes' );

				// Payment complete
				$order->payment_complete( $response->id );

				// Add order note
				$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id ) );

			} else {

				// Store captured value
				update_post_meta( $order->id, '_stripe_charge_captured', 'no' );
				add_post_meta( $order->id, '_transaction_id', $response->id, true );

				// Mark as on-hold
				$order->update_status( 'on-hold', sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' ), $response->id ) );
			}

		} catch ( Exception $e ) {
			$order_note = sprintf( __( 'Stripe Transaction Failed (%s)', 'woocommerce-gateway-stripe' ), $e->getMessage() );

			// Mark order as failed if not already set,
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			if ( 'failed' != $order->status ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}
	}
}
