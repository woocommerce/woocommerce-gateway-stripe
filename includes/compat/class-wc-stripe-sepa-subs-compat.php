<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Subscriptions.
 *
 * @extends WC_Gateway_Stripe_Sepa
 */
class WC_Stripe_Sepa_Subs_Compat extends WC_Gateway_Stripe_Sepa {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
			add_action( 'wcs_resubscribe_order_created', [ $this, 'delete_resubscribe_meta' ], 10 );
			add_action( 'wcs_renewal_order_created', [ $this, 'delete_renewal_meta' ], 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_stripe', [ $this, 'update_failing_payment_method' ], 10, 2 );
			add_action( 'wc_stripe_sepa_payment_fields', [ $this, 'display_update_subs_payment_checkout' ] );
			add_action( 'wc_stripe_add_payment_method_' . $this->id . '_success', [ $this, 'handle_add_payment_method_success' ], 10, 2 );

			// Display the credit card used for a subscription in the "My Subscriptions" table.
			add_filter( 'woocommerce_my_subscriptions_payment_method', [ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

			// Allow store managers to manually set Stripe as the payment method on a subscription.
			add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 2 );
			add_filter( 'wc_stripe_display_save_payment_method_checkbox', [ $this, 'maybe_hide_save_checkbox' ] );
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
	 *
	 * @param  int $order_id
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Checks if page is pay for order and change subs payment page.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public function is_subs_change_payment() {
		return ( isset( $_GET['pay_for_order'] ) && isset( $_GET['change_payment_method'] ) );
	}

	/**
	 * Displays a checkbox to allow users to update all subs payments with new
	 * payment.
	 *
	 * @since 4.1.11
	 */
	public function display_update_subs_payment_checkout() {
		$subs_statuses = apply_filters( 'wc_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );
		if (
			apply_filters( 'wc_stripe_display_update_subs_payment_method_card_checkbox', true ) &&
			wcs_user_has_subscription( get_current_user_id(), '', $subs_statuses ) &&
			is_add_payment_method_page()
		) {
			$label = esc_html( apply_filters( 'wc_stripe_save_to_subs_text', __( 'Update the Payment Method used for all of my active subscriptions.', 'woocommerce-gateway-stripe' ) ) );
			$id    = sprintf( 'wc-%1$s-update-subs-payment-method-card', $this->id );
			woocommerce_form_field(
				$id,
				[
					'type'    => 'checkbox',
					'label'   => $label,
					'default' => apply_filters( 'wc_stripe_save_to_subs_checked', false ),
				]
			);
		}
	}

	/**
	 * Updates all active subscriptions payment method.
	 *
	 * @since 4.1.11
	 * @param string $source_id
	 * @param object $source_object
	 */
	public function handle_add_payment_method_success( $source_id, $source_object ) {
		if ( isset( $_POST[ 'wc-' . $this->id . '-update-subs-payment-method-card' ] ) ) {
			$all_subs        = wcs_get_users_subscriptions();
			$subs_statuses   = apply_filters( 'wc_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );
			$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );

			if ( ! empty( $all_subs ) ) {
				foreach ( $all_subs as $sub ) {
					if ( $sub->has_status( $subs_statuses ) ) {
						WC_Subscriptions_Change_Payment_Gateway::update_payment_method(
							$sub,
							$this->id,
							[
								'post_meta' => [
									'_stripe_source_id'   => [ 'value' => $source_id ],
									'_stripe_customer_id' => [ 'value' => $stripe_customer->get_id() ],
								],
							]
						);
					}
				}
			}
		}
	}

	/**
	 * Updates other subscription sources.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function save_source_to_order( $order, $source ) {
		parent::save_source_to_order( $order, $source );

		$order_id = $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = [];
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription_id = $subscription->get_id();
			update_post_meta( $subscription_id, '_stripe_customer_id', $source->customer );
			update_post_meta( $subscription_id, '_stripe_source_id', $source->source );
		}
	}

	/**
	 * Process the payment based on type.
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false ) {
		if ( $this->has_subscription( $order_id ) ) {
			if ( $this->is_subs_change_payment() ) {
				return $this->change_subs_payment_method( $order_id );
			}

			// Regular payment with force customer enabled
			return parent::process_payment( $order_id, $retry, true, $previous_error );
		} else {
			return parent::process_payment( $order_id, $retry, $force_save_source, $previous_error );
		}
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @since 4.0.4
	 * @param int $order_id
	 */
	public function change_subs_payment_method( $order_id ) {
		try {
			$subscription    = wc_get_order( $order_id );
			$prepared_source = $this->prepare_source( get_current_user_id(), true );

			if ( empty( $prepared_source->source ) ) {
				$localized_message = __( 'Payment processing failed. Please retry.', 'woocommerce-gateway-stripe' );
				throw new WC_Stripe_Exception( print_r( $prepared_source, true ), $localized_message );
			}

			$this->save_source_to_order( $subscription, $prepared_source );

			do_action( 'wc_stripe_change_subs_payment_method_success', $prepared_source->source, $prepared_source );

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $subscription ),
			];
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->process_subscription_payment( $amount_to_charge, $renewal_order, true, false );
	}

	/**
	 * Process_subscription_payment function.
	 *
	 * @since 3.0
	 * @since 4.0.4 Add third parameter flag to retry.
	 * @since 4.1.0 Add fourth parameter to log previous errors.
	 * @param float  $amount
	 * @param mixed  $renewal_order
	 * @param bool   $retry Should we retry the process?
	 * @param object $previous_error
	 */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {
		try {
			if ( $amount * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
				/* translators: minimum amount */
				$message = sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) );
				throw new WC_Stripe_Exception(
					'Error while processing renewal order ' . $renewal_order->get_id() . ' : ' . $message,
					$message
				);
			}

			$order_id = $renewal_order->get_id();

			$this->ensure_subscription_has_customer_id( $order_id );

			// Get source from order
			$prepared_source = $this->prepare_order_source( $renewal_order );
			$source_object   = $prepared_source->source_object;

			if ( ! $prepared_source->customer ) {
				throw new WC_Stripe_Exception(
					'Failed to process renewal for order ' . $renewal_order->get_id() . '. Stripe customer id is missing in the order',
					__( 'Customer not found', 'woocommerce-gateway-stripe' )
				);
			}

			WC_Stripe_Logger::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

			/*
			 * If we're doing a retry and source is chargeable, we need to pass
			 * a different idempotency key and retry for success.
			 */
			if ( is_object( $source_object ) && empty( $source_object->error ) && $this->need_update_idempotency_key( $source_object, $previous_error ) ) {
				add_filter( 'wc_stripe_idempotency_key', [ $this, 'change_idempotency_key' ], 10, 2 );
			}

			if ( ( $this->is_no_such_source_error( $previous_error ) || $this->is_no_linked_source_error( $previous_error ) ) && apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
				// Passing empty source will charge customer default.
				$prepared_source->source = '';
			}

			$request            = $this->generate_payment_request( $renewal_order, $prepared_source );
			$request['capture'] = 'true';
			$request['amount']  = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
			$response           = WC_Stripe_API::request( $request );

			if ( ! empty( $response->error ) ) {
				// We want to retry.
				if ( $this->is_retryable_error( $response->error ) ) {
					if ( $retry ) {
						// Don't do anymore retries after this.
						if ( 5 <= $this->retry_interval ) {
							return $this->process_subscription_payment( $amount, $renewal_order, false, $response->error );
						}

						sleep( $this->retry_interval );

						$this->retry_interval++;

						return $this->process_subscription_payment( $amount, $renewal_order, true, $response->error );
					} else {
						$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' );
						$renewal_order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}
				}

				$localized_messages = WC_Stripe_Helper::get_localized_messages();

				if ( 'card_error' === $response->error->type ) {
					$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
				} else {
					$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
				}

				$renewal_order->add_order_note( $localized_message );

				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}

			do_action( 'wc_gateway_stripe_process_payment', $response, $renewal_order );

			$this->process_response( $response, $renewal_order );
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );

			/* translators: error message */
			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * Don't transfer Stripe customer/token meta to resubscribe orders.
	 *
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->get_id(), '_stripe_customer_id' );
		delete_post_meta( $resubscribe_order->get_id(), '_stripe_source_id' );
		// For BW compat will remove in future
		delete_post_meta( $resubscribe_order->get_id(), '_stripe_card_id' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * Don't transfer Stripe fee/ID meta to renewal orders.
	 *
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_renewal_meta( $renewal_order ) {
		WC_Stripe_Helper::delete_stripe_fee( $renewal_order );
		WC_Stripe_Helper::delete_stripe_net( $renewal_order );

		return $renewal_order;
	}

	/**
	 * Update the customer_id for a subscription after using Stripe to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->get_id(), '_stripe_customer_id', $renewal_order->get_meta( '_stripe_customer_id', true ) );
		update_post_meta( $subscription->get_id(), '_stripe_source_id', $renewal_order->get_meta( '_stripe_source_id', true ) );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param array           $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$source_id = get_post_meta( $subscription->get_id(), '_stripe_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $source_id ) ) {
			$source_id = get_post_meta( $subscription->get_id(), '_stripe_card_id', true );

			// Take this opportunity to update the key name.
			update_post_meta( $subscription->get_id(), '_stripe_source_id', $source_id );
		}

		$payment_meta[ $this->id ] = [
			'post_meta' => [
				'_stripe_customer_id' => [
					'value' => get_post_meta( $subscription->get_id(), '_stripe_customer_id', true ),
					'label' => 'Stripe Customer ID',
				],
				'_stripe_source_id'   => [
					'value' => $source_id,
					'label' => 'Stripe Source ID',
				],
			],
		];
		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array  $payment_meta associative array of meta data required for automatic payments
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {

			if ( ! isset( $payment_meta['post_meta']['_stripe_customer_id']['value'] ) || empty( $payment_meta['post_meta']['_stripe_customer_id']['value'] ) ) {

				// Allow empty stripe customer id during subscription renewal. It will be added when processing payment if required.
				if ( ! isset( $_POST['wc_order_action'] ) || 'wcs_process_renewal' !== $_POST['wc_order_action'] ) {
					throw new Exception( __( 'A "Stripe Customer ID" value is required.', 'woocommerce-gateway-stripe' ) );
				}
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
	 * @param string          $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		$customer_user = $subscription->get_customer_id();

		// bail for other payment methods
		if ( $subscription->get_payment_method() !== $this->id || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$stripe_source_id = get_post_meta( $subscription->get_id(), '_stripe_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $stripe_source_id ) ) {
			$stripe_source_id = get_post_meta( $subscription->get_id(), '_stripe_card_id', true );

			// Take this opportunity to update the key name.
			update_post_meta( $subscription->get_id(), '_stripe_source_id', $stripe_source_id );
		}

		$stripe_customer    = new WC_Stripe_Customer();
		$stripe_customer_id = get_post_meta( $subscription->get_id(), '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the subscription, fallback to the user meta data.
		if ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) {
			$user_id            = $customer_user;
			$stripe_customer_id = get_user_option( '_stripe_customer_id', $user_id );
			$stripe_source_id   = get_user_option( '_stripe_source_id', $user_id );

			// For BW compat will remove in future.
			if ( empty( $stripe_source_id ) ) {
				$stripe_source_id = get_user_option( '_stripe_card_id', $user_id );

				// Take this opportunity to update the key name.
				update_user_option( $user_id, '_stripe_source_id', $stripe_source_id, false );
			}
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) && false !== $subscription->get_parent() ) {
			$stripe_customer_id = get_post_meta( $subscription->get_parent_id(), '_stripe_customer_id', true );
			$stripe_source_id   = get_post_meta( $subscription->get_parent_id(), '_stripe_source_id', true );

			// For BW compat will remove in future.
			if ( empty( $stripe_source_id ) ) {
				$stripe_source_id = get_post_meta( $subscription->get_parent_id(), '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				update_post_meta( $subscription->get_parent_id(), '_stripe_source_id', $stripe_source_id );
			}
		}

		$stripe_customer->set_id( $stripe_customer_id );

		$sources                   = $stripe_customer->get_sources();
		$payment_method_to_display = __( 'N/A', 'woocommerce-gateway-stripe' );

		if ( $sources ) {
			foreach ( $sources as $source ) {
				if ( $source->id === $stripe_source_id ) {
					if ( $source->sepa_debit ) {
						/* translators: 1) last 4 digits of SEPA Direct Debit */
						$payment_method_to_display = sprintf( __( 'Via SEPA Direct Debit ending in %1$s', 'woocommerce-gateway-stripe' ), $source->sepa_debit->last4 );
					}

					break;
				}
			}
		}

		return $payment_method_to_display;
	}
}
