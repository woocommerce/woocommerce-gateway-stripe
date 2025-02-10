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

		self::$has_attached_forced_tokenization_integration_hooks = true;
	}

	public function get_order_payment_token( $token, $top_most_order ) {
		if ( $top_most_order->payment_method !== $this->id ) {
			return $token;
		}

		$intent = $this->get_intent_from_order( $top_most_order );

		$token = array(
			'gateway' => $this->id,
			'token'   => $intent->id,
			'data'    => array(
				'intent' => $intent,
			),
		);

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
		return class_exists( 'WC_Checkout_Tokenization' );
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

	public function process_order_saving_order_token( $order_id ) {
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

	public function charge_order_token( $order ) {
		$top_most_order = WC_Checkout_Tokenization::get_top_most_order( $order );

		$intent = $this->get_intent_from_order( $top_most_order );
		if ( isset( $intent->object ) && 'setup_intent' === $intent->object ) {
			$intent = false; // This function can only deal with *payment* intents
		}

		$stripe_customer_id = null;
		if ( $intent && ! empty( $intent->customer ) ) {
			$stripe_customer_id = $intent->customer;
		}

		$prepared_source = $this->prepare_order_source( $top_most_order );

		$this->maybe_disallow_prepaid_card( $prepared_source->source_object );
		$this->check_source( $prepared_source );

		$this->save_source_to_order( $order, $prepared_source );

		// Update the saved payment method to have the latest billing details.
		if ( $prepared_source->source && $this->is_using_saved_payment_method() ) {
			$this->update_saved_payment_method( $prepared_source->source, $order );
		}

		if ( 0 >= $order->get_total() ) {
			return $this->complete_free_order( $order, $prepared_source, $force_save_source );
		}

		// This will throw exception if not valid.
		$this->validate_minimum_order_amount( $order );

		WC_Stripe_Logger::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

		if ( $intent ) {
			$intent = $this->update_existing_intent( $intent, $top_most_order, $prepared_source );
		} else {
			$intent = $this->create_intent( $top_most_order, $prepared_source );
		}

		// Confirm the intent after locking the order to make sure webhooks will not interfere.
		if ( empty( $intent->error ) ) {
			$this->lock_order_payment( $order, $intent );
			$intent = $this->confirm_intent( $intent, $order, $prepared_source );
		}

		$force_save_source_value = apply_filters( 'wc_stripe_force_save_source', $force_save_source, $prepared_source->source );

		if ( ! empty( $intent->error ) ) {
			$this->maybe_remove_non_existent_customer( $intent->error, $order );

			// We want to retry.
			if ( $this->is_retryable_error( $intent->error ) ) {
				return $this->retry_after_error( $intent, $order, $retry, $force_save_source, $previous_error, $use_order_source );
			}

			$this->unlock_order_payment( $order );
			$this->throw_localized_message( $intent, $order );
		}

		if ( WC_Stripe_Intent_Status::SUCCEEDED === $intent->status && ! $this->is_using_saved_payment_method() && ( $this->save_payment_method_requested() || $force_save_source_value ) ) {
			$this->save_payment_method( $prepared_source->source_object );
		}

		if ( ! empty( $intent ) ) {
			// Use the last charge within the intent to proceed.
			$response = $this->get_latest_charge_from_intent( $intent );

			// If the intent requires a 3DS flow, redirect to it.
			if ( WC_Stripe_Intent_Status::REQUIRES_ACTION === $intent->status ) {
				$this->unlock_order_payment( $order );

				// If the order requires some action from the customer, add meta to the order to prevent it from being cancelled by WooCommerce's hold stock settings.
				WC_Stripe_Helper::set_payment_awaiting_action( $order );

				if ( is_wc_endpoint_url( 'order-pay' ) ) {
					$redirect_url = add_query_arg( 'wc-stripe-confirmation', 1, $order->get_checkout_payment_url( false ) );

					return [
						'result'   => 'success',
						'redirect' => wp_sanitize_redirect( esc_url_raw( $redirect_url ) ),
					];
				} else {
					/**
					 * This URL contains only a hash, which will be sent to `checkout.js` where it will be set like this:
					 * `window.location = result.redirect`
					 * Once this redirect is sent to JS, the `onHashChange` function will execute `handleCardPayment`.
					 */

					return [
						'result'                => 'success',
						'redirect'              => $this->get_return_url( $order ),
						'payment_intent_secret' => $intent->client_secret,
						'save_payment_method'   => $this->save_payment_method_requested(),
					];
				}
			}
		}

		// Process valid response.
		$this->process_response( $response, $order );



	}
}
