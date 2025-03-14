<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Forced Tokenization compatibility.
 *
 * @since x.x.x
 */
trait WC_Stripe_Forced_Tokenization_Trait {

	/**
	 * Stores a flag to indicate if the forced tokenization integration hooks have been attached.
	 *
	 * The callbacks attached as part of maybe_init_forced_tokenization() only need to be attached once to avoid duplication.
	 *
	 * @var bool False by default, true once the callbacks have been attached.
	 */
	private static $has_attached_forced_tokenization_integration_hooks = false;

	/**
	 * Initialize pre-orders hook.
	 *
	 * @since x.x.x
	 */
	public function maybe_init_forced_tokenization() {
		if ( ! $this->is_forced_tokenization_enabled() ) {
			return;
		}

		$this->supports[] = 'forced-tokenization'; // @phpstan-ignore-line (supports is defined in the classes that use this trait)

		add_action( 'wc_checkout_tokenization_' . $this->id . '_charge_order_token', [ $this, 'charge_order_token' ], 10, 2 ); // @phpstan-ignore-line (id is defined in the classes that use this trait)

		/**
		 * The callbacks attached below only need to be attached once. We don't need each gateway instance to have its own callback.
		 * Therefore we only attach them once on the main `stripe` gateway and store a flag to indicate that they have been attached.
		 */
		if ( self::$has_attached_forced_tokenization_integration_hooks || WC_Gateway_Stripe::ID !== $this->id ) { // @phpstan-ignore-line (id is defined in the classes that use this trait)
			return;
		}

		add_filter( 'wc_stripe_display_save_payment_method_checkbox', [ $this, 'hide_save_payment_for_forced_tokenization' ] );

		add_filter( 'pre_wc_checkout_tokenization_get_order_payment_token', [ $this, 'get_order_payment_token' ], 10, 2 );

		add_action( 'wc_checkout_tokenization_delete_order_payment_token', [ $this, 'delete_order_payment_token' ], 10, 2 );

		self::$has_attached_forced_tokenization_integration_hooks = true;
	}

	/**
	 * Filter the order payment token to include the source and intent.
	 *
	 * Runs on the `pre_wc_checkout_tokenization_get_order_payment_token` filter.
	 *
	 * @since x.x.x
	 *
	 * @param array $token The order payment token.
	 * @param WC_Order $top_most_order The top most order in the chain.
	 * @return array The modified order payment token.
	 */
	public function get_order_payment_token( $token, $top_most_order ) {
		if ( $top_most_order->get_payment_method() !== $this->id ) {
			return $token;
		}

		$intent = $this->get_intent_from_order( $top_most_order );

		$token = [
			'gateway' => $this->id,
			'token'   => $intent->id,
			'data'    => [
				'intent' => $intent,
			],
		];

		return $token;
	}

	/**
	 * Checks if forced tokenization is supported on this site.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public function is_forced_tokenization_enabled() {
		return WC_Stripe_Feature_Flags::is_upe_checkout_enabled() && class_exists( 'WC_Checkout_Tokenization' );
	}

	/**
	 * Whether the current cart require a payment token stored against the order.
	 *
	 * @since x.x.x
	 *
	 * @param  int $order_id
	 * @return bool
	 */
	public function cart_requires_order_payment_token() {
		return $this->is_forced_tokenization_enabled() && WC_Checkout_Tokenization::cart_requires_order_payment_token();
	}

	/**
	 * Whether the current order requires a payment token be stored against the order.
	 *
	 * @since x.x.x
	 *
	 * @param int|\WC_Order $order_id The order ID or order object.
	 * @return bool True if the order requires a payment token stored against the order, false otherwise.
	 */
	public function order_requires_order_payment_token( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return $this->is_forced_tokenization_enabled() && WC_Checkout_Tokenization::order_requires_order_payment_token( $order );
	}

	/**
	 * Whether the current cart requires the user to save a payment method.
	 *
	 * @since x.x.x
	 *
	 * @return bool True if the cart requires the user to save a payment method, false otherwise.
	 */
	public function cart_requires_user_payment_method() {
		return $this->is_forced_tokenization_enabled() && WC_Checkout_Tokenization::cart_requires_user_payment_method();
	}

	/**
	 * Whether the current order requires the user to save a payment method.
	 *
	 * @since x.x.x
	 *
	 * @param int|\WC_Order $order_id The order ID or order object.
	 * @return bool True if the order requires the user to save a payment method, false otherwise.
	 */
	public function order_requires_user_payment_method( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return $this->is_forced_tokenization_enabled() && WC_Checkout_Tokenization::order_requires_user_payment_method( $order );
	}

	/**
	 * Hide the save payment method checkbox when forced tokenization is enabled.
	 *
	 * Hides the save payment checkbox when a payment token is required for the
	 * user or the order.
	 *
	 * Runs on the filter `wc_stripe_display_save_payment_method_checkbox`.
	 *
	 * @since x.x.x
	 *
	 * @param bool $display_save_option Whether to display the save payment method checkbox.
	 * @return bool Modified value of $display_save_option.
	 */
	public function hide_save_payment_for_forced_tokenization( $display_save_option ) {
		if ( ! $display_save_option ) {
			// No further checking required.
			return $display_save_option;
		}

		if ( $this->cart_requires_order_payment_token() || $this->cart_requires_user_payment_method() ) {
			$display_save_option = false;
		}

		return $display_save_option;
	}

	/**
	 * Process a payment and store a reusable token against the order.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $retry Whether this is a retry.
	 * @param mixed    $error Previous error.
	 */
	public function process_order_saving_order_token( $order_id, $retry = true, $error = false ) {
		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return false;
			}

			$future_payments = function( $request ) {
				$request['setup_future_usage'] = 'off_session';
				return $request;
			};

			add_filter( 'wc_stripe_generate_create_intent_request', $future_payments );

			$source   = $this->prepare_order_source( $order ); // @phpstan-ignore-line (prepare_order_source is defined in the classes that use this trait)
			$response = $this->create_and_confirm_intent_for_off_session( $order, $source ); // @phpstan-ignore-line (create_and_confirm_intent_for_off_session is defined in the classes that use this trait)

			add_filter( 'wc_stripe_generate_create_intent_request', $future_payments );

			$is_authentication_required = $this->is_authentication_required_for_payment( $response ); // @phpstan-ignore-line (is_authentication_required_for_payment is defined in the classes that use this trait)

			if ( ! empty( $response->error ) && ! $is_authentication_required ) {
				if ( ! $retry ) {
					throw new Exception( $response->error->message );
				}
				$this->remove_order_source_before_retry( $order );
				// $this->process_pre_order_release_payment( $order, false );
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
	 * Process a scheduled payment for an order with forced tokenization.
	 *
	 * Runs on the "wc_checkout_tokenization_{$this->id}_charge_order_token" action.
	 *
	 * @param WC_Order $order Scheduled order.
	 */
	public function charge_order_token( $order ) {
		return $this->process_order_token_payment( $order );
	}

	/**
	 * Process a scheduled payment.
	 *
	 * @param WC_Order $order          Scheduled deposit order.
	 * @param bool     $retry          Whether to retry the payment.
	 * @param mixed    $previous_error Previous error.
	 */
	public function process_order_token_payment( $order, $retry = true, $previous_error = false ) {
		$amount         = $order->get_total();
		$top_most_order = WC_Checkout_Tokenization::get_top_most_order( $order );

		try {
			$order_id = $order->get_id();

			// Get source from order
			$prepared_source = $this->prepare_order_source( $top_most_order );
			$source_object   = $prepared_source->source_object;

			$this->check_source( $prepared_source );
			$this->save_source_to_order( $order, $prepared_source );

			if ( ! $prepared_source->customer ) {
				throw new WC_Stripe_Exception(
					'Failed to process payment for order ' . $order->get_id() . '. Stripe customer id is missing in the order',
					__( 'Customer not found', 'woocommerce-gateway-stripe' )
				);
			}

			WC_Stripe_Logger::log( "Info: Begin processing scheduled payment for order {$order_id} for the amount of {$amount}" );

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

			// If the payment gateway is SEPA, use the charges API.
			// TODO: Remove when SEPA is migrated to payment intents.
			if ( 'stripe_sepa' === $this->id ) {
				$request            = $this->generate_payment_request( $order, $prepared_source );
				$request['capture'] = 'true';
				$request['amount']  = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
				$response           = WC_Stripe_API::request( $request );

				$is_authentication_required = false;
			} else {
				$this->lock_order_payment( $order );
				$response                   = $this->create_and_confirm_intent_for_off_session( $order, $prepared_source, $amount );
				$is_authentication_required = $this->is_authentication_required_for_payment( $response );
			}

			// It's only a failed payment if it's an error and it's not of the type 'authentication_required'.
			// If it's 'authentication_required', then we should email the user and ask them to authenticate.
			if ( ! empty( $response->error ) && ! $is_authentication_required ) {
				// We want to retry.
				if ( $this->is_retryable_error( $response->error ) ) {
					if ( $retry ) {
						// Don't do anymore retries after this.
						if ( 5 <= $this->retry_interval ) { // @phpstan-ignore-line (retry_interval is defined in classes using this class)
							return $this->process_order_token_payment( $order, false, $response->error );
						}

						sleep( $this->retry_interval );

						$this->retry_interval++;

						return $this->process_order_token_payment( $order, true, $response->error );
					} else {
						$localized_message = sprintf(
							/* translators: 1) error message from Stripe; 2) request log URL */
							__( 'Sorry, we are unable to process the payment at this time. Reason: %1$s %2$s', 'woocommerce-gateway-stripe' ),
							$response->error->message,
							isset( $response->error->request_log_url ) ? make_clickable( $response->error->request_log_url ) : ''
						);
						$order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}
				}

				$localized_messages = WC_Stripe_Helper::get_localized_messages();

				if ( 'card_error' === $response->error->type ) {
					$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
				} elseif ( 'payment_intent_mandate_invalid' === $response->error->type ) {
					$localized_message = __(
						'The mandate used for this scheduled payment is invalid. You may need to bring the customer back to your store and ask them to resubmit their payment information.',
						'woocommerce-gateway-stripe'
					);
				} else {
					$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
				}

				if ( isset( $response->error->request_log_url ) ) {
					$localized_message .= ' ' . make_clickable( $response->error->request_log_url );
				}

				$order->add_order_note( $localized_message );

				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			/* translators: error message */
			$order->update_status( 'failed' );
			$this->unlock_order_payment( $order );

			return;
		}

		try {

			// Either the charge was successfully captured, or it requires further authentication.
			if ( $is_authentication_required ) {
				do_action( 'wc_gateway_stripe_process_payment_authentication_required', $order, $response );

				$error_message = __( 'This transaction requires authentication.', 'woocommerce-gateway-stripe' );
				$order->add_order_note( $error_message );

				$charge = $this->get_latest_charge_from_intent( $response->error->payment_intent );
				$id     = $charge->id;

				$order->set_transaction_id( $id );
				/* translators: %s is the charge Id */
				$order->update_status( 'failed', sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'woocommerce-gateway-stripe' ), $id ) );
				if ( is_callable( [ $order, 'save' ] ) ) {
					$order->save();
				}
			} elseif ( $this->must_authorize_off_session( $response ) ) {
				$charge_attempt_at = $response->processing->card->customer_notification->completes_at;
				$attempt_date      = wp_date( get_option( 'date_format', 'F j, Y' ), $charge_attempt_at, wp_timezone() );
				$attempt_time      = wp_date( get_option( 'time_format', 'g:i a' ), $charge_attempt_at, wp_timezone() );

				$message = sprintf(
					/* translators: 1) a date in the format yyyy-mm-dd, e.g. 2021-09-21; 2) time in the 24-hour format HH:mm, e.g. 23:04 */
					__( 'The customer must authorize this payment via the pre-debit notification sent to them by their card issuing bank, before %1$s at %2$s, when the charge will be attempted.', 'woocommerce-gateway-stripe' ),
					$attempt_date,
					$attempt_time
				);
				$order->add_order_note( $message );
				$order->update_status( 'pending' );
				if ( is_callable( [ $order, 'save' ] ) ) {
					$order->save();
				}
			} else {
				// The charge was successfully captured
				do_action( 'wc_gateway_stripe_process_payment', $response, $order );

				// Use the last charge within the intent or the full response body in case of SEPA.
				$latest_charge = $this->get_latest_charge_from_intent( $response );
				$this->process_response( ( ! empty( $latest_charge ) ) ? $latest_charge : $response, $order );
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );
		}

		$this->unlock_order_payment( $order );
	}

	/**
	 * Delete the Stripe token data from the order.
	 *
	 * Detach the payment method from the customer as the order is now
	 * completed and the payment method is no longer required.
	 *
	 * The payment method is invalidated via the API rather than deleting
	 * the meta data to allow for future references in refunds and other
	 * operations.
	 *
	 * @see https://docs.stripe.com/api/payment_methods/detach
	 *
	 * @since x.x.x
	 *
	 * @param WC_Order $order The order object the token is stored against.
	 */
	public function delete_order_payment_token( $order ) {
		// API request to detach the payment method from the customer.
		$payment_method_id = $order->get_meta( '_stripe_source_id' ); // Payment method is stored as source ID.
		$customer_id       = $order->get_meta( '_stripe_customer_id' );

		if ( ! $payment_method_id || ! $customer_id ) {
			return;
		}

		WC_Stripe_API::detach_payment_method_from_customer( $customer_id, $payment_method_id );
	}
}
