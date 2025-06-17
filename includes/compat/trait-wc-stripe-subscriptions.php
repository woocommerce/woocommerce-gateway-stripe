<?php

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Subscriptions compatibility.
 */
trait WC_Stripe_Subscriptions_Trait {

	use WC_Stripe_Subscriptions_Utilities_Trait;

	/**
	 * Stores a flag to indicate if the subscription integration hooks have been attached.
	 *
	 * The callbacks attached as part of maybe_init_subscriptions() only need to be attached once to avoid duplication.
	 *
	 * @var bool False by default, true once the callbacks have been attached.
	 */
	private static $has_attached_integration_hooks = false;

	/**
	 * Initialize subscription support and hooks.
	 *
	 * @since 5.6.0
	 */
	public function maybe_init_subscriptions() {
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return;
		}

		$this->supports = array_merge(
			$this->supports,
			[
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
			]
		);

		/**
		 * We need to attach the callbacks below once per Gateway (CC, SEPA, etc.), but only once.
		 * Therefore, we use a static flag at class level to indicate that they have been attached.
		 */
		if ( self::$has_attached_integration_hooks ) {
			return;
		}

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, [ $this, 'update_failing_payment_method' ], 10, 2 );

		add_action( 'wc_stripe_payment_fields_' . $this->id, [ $this, 'display_update_subs_payment_checkout' ] );
		add_action( 'wc_stripe_add_payment_method_' . $this->id . '_success', [ $this, 'handle_add_payment_method_success' ], 10, 2 );
		add_action( 'woocommerce_stripe_add_payment_method', [ $this, 'handle_upe_add_payment_method_success' ], 10, 2 );

		// Display the payment method used for a subscription in the "My Subscriptions" table.
		add_filter( 'woocommerce_my_subscriptions_payment_method', [ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );

		// Validate the payment method meta data set on a subscription.
		add_filter( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 2 );

		self::$has_attached_integration_hooks = true;

		/**
		 * The callbacks attached below only need to be attached once. We don't need each gateway instance to have its own callback.
		 * Therefore we only attach them once on the main `stripe` gateway and store a flag to indicate that they have been attached.
		 */
		if ( WC_Gateway_Stripe::ID !== $this->id ) {
			return;
		}

		add_action( 'woocommerce_subscriptions_change_payment_before_submit', [ $this, 'differentiate_change_payment_method_form' ] );
		add_action( 'wcs_resubscribe_order_created', [ $this, 'delete_resubscribe_meta' ], 10 );
		add_action( 'wcs_renewal_order_created', [ $this, 'delete_renewal_meta' ], 10 );

		add_filter( 'wc_stripe_display_save_payment_method_checkbox', [ $this, 'display_save_payment_method_checkbox' ] );

		// Add the necessary information to create a mandate to the payment intent.
		add_filter( 'wc_stripe_generate_create_intent_request', [ $this, 'add_subscription_information_to_intent' ], 10, 3 );

		/*
		* WC subscriptions hooks into the "template_redirect" hook with priority 100.
		* If the screen is "Pay for order" and the order is a subscription renewal, it redirects to the plain checkout.
		* See: https://github.com/woocommerce/woocommerce-subscriptions/blob/99a75687e109b64cbc07af6e5518458a6305f366/includes/class-wcs-cart-renewal.php#L165
		* If we are in the "You just need to authorize SCA" flow, we don't want that redirection to happen.
		*/
		add_action( 'template_redirect', [ $this, 'remove_order_pay_var' ], 99 );
		add_action( 'template_redirect', [ $this, 'restore_order_pay_var' ], 101 );

		// Disable editing for Indian subscriptions with mandates. Those need to be recreated as mandates does not support upgrades (due fixed amounts).
		add_filter( 'wc_order_is_editable', [ $this, 'disable_subscription_edit_for_india' ], 10, 2 );

		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [ $this, 'update_payment_after_deferred_intent' ], 10, 3 );
	}

	/**
	 * Displays a checkbox to allow users to update all subs payments with new
	 * payment.
	 *
	 * @since 4.1.11
	 */
	public function display_update_subs_payment_checkout() {
		$statuses = apply_filters( 'wc_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );

		if ( ! apply_filters( 'wc_stripe_display_update_subs_payment_method_card_checkbox', true ) ) {
			return;
		}

		if ( ! is_add_payment_method_page() ) {
			return;
		}

		if ( function_exists( 'wcs_user_has_subscription' ) && wcs_user_has_subscription( get_current_user_id(), '', $statuses ) ) {
			$label = esc_html( apply_filters( 'wc_stripe_save_to_subs_text', __( 'Update the payment method for all of my current subscriptions', 'woocommerce-gateway-stripe' ) ) );
			$id    = sprintf( 'wc-%1$s-update-subs-payment-method-card', $this->id );
			woocommerce_form_field(
				$id,
				[
					'type'        => 'checkbox',
					'label'       => $label,
					'default'     => apply_filters( 'wc_stripe_save_to_subs_checked', false ),
					'input_class' => [ 'wc-stripe-update-all-subscriptions-payment-method' ],
				]
			);
		}
	}

	/**
	 * Updates all active subscriptions payment method.
	 *
	 * Note: This is the Legacy checkout experience method for updating subscriptions payment method.
	 *
	 * @since 4.1.11
	 *
	 * @see handle_upe_add_payment_method_success() for the new UPE checkout method.
	 *
	 * @param string $source_id
	 * @param object $source_object
	 */
	public function handle_add_payment_method_success( $source_id, $source_object ) {
		$this->handle_upe_add_payment_method_success( get_current_user_id(), $source_object );
	}

	/**
	 * Updates all the user's active subscriptions payment method with the new payment method.
	 *
	 * @since 8.8.0
	 *
	 * @param int      $user_id               The user ID.
	 * @param stdClass $payment_method_object The newly added payment method object.
	 */
	public function handle_upe_add_payment_method_success( $user_id, $payment_method_object ) {
		// To avoid errors, exit early if there is no WC_Subscriptions_Change_Payment_Gateway class or the payment method object is not complete.
		if ( ! class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) || ! isset( $payment_method_object->id ) ) {
			return;
		}

		// Check if the customer has requested to update all subscriptions via a direct request or after returning from the UPE redirect.
		$should_update_subscriptions = isset( $_POST[ 'wc-' . $this->id . '-update-subs-payment-method-card' ] );
		$should_update_subscriptions = $should_update_subscriptions || isset( $this->stripe_id, $_GET[ "wc-stripe-{$this->stripe_id}-update-all-subscription-payment-methods" ] );

		if ( ! $should_update_subscriptions ) {
			return;
		}

		$statuses        = apply_filters( 'wc_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );
		$subscriptions   = function_exists( 'wcs_get_users_subscriptions' ) ? wcs_get_users_subscriptions( $user_id ) : [];
		$stripe_customer = new WC_Stripe_Customer( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( ! $subscription->has_status( $statuses ) ) {
				continue;
			}

			if ( ! current_user_can( 'edit_shop_subscription_payment_method', $subscription->get_id() ) ) {
				continue;
			}

			WC_Subscriptions_Change_Payment_Gateway::update_payment_method(
				$subscription,
				$this->id,
				[
					'post_meta' => [
						'_stripe_source_id'   => [ 'value' => $payment_method_object->id ],
						'_stripe_customer_id' => [ 'value' => $stripe_customer->get_id() ],
					],
				]
			);
		}
	}

	/**
	 * Render a dummy element in the "Change payment method" form (that does not appear in the "Pay for order" form)
	 * which can be checked to determine proper SCA handling to apply for each form.
	 *
	 * @since 4.6.1
	 */
	public function differentiate_change_payment_method_form() {
		echo '<input type="hidden" id="wc-stripe-change-payment-method" />';
	}

	/**
	 * Maybe process payment method change for subscriptions.
	 *
	 * @since 5.6.0
	 *
	 * @param int $order_id
	 * @return bool
	 */
	public function maybe_change_subscription_payment_method( $order_id ) {
		return (
			WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() &&
			$this->has_subscription( $order_id ) &&
			$this->is_changing_payment_method_for_subscription()
		);
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @since 5.6.0
	 *
	 * @param int $order_id
	 * @return array|null
	 */
	public function process_change_subscription_payment_method( $order_id ) {
		try {
			$subscription    = wc_get_order( $order_id );
			$prepared_source = $this->prepare_source( get_current_user_id(), true );

			$this->maybe_disallow_prepaid_card( $prepared_source->source_object );
			$this->check_source( $prepared_source );
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
	 * Process the payment method change with deferred intent.
	 *
	 * @param int $subscription_id
	 *
	 * @return array
	 */
	public function process_change_subscription_payment_with_deferred_intent( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return [
				'result'   => 'failure',
				'redirect' => '',
			];
		}

		try {
			$payment_information = $this->prepare_payment_information_from_request( $subscription );

			$this->validate_selected_payment_method_type( $payment_information, $subscription->get_billing_country() );

			$payment_method_id     = $payment_information['payment_method'];
			$selected_payment_type = $payment_information['selected_payment_type'];
			$upe_payment_method    = $this->payment_methods[ $selected_payment_type ] ?? null;

			// Retrieve the payment method object from Stripe.
			$payment_method = $this->stripe_request( 'payment_methods/' . $payment_method_id );

			// Throw an exception when the payment method is a prepaid card and it's disallowed.
			$this->maybe_disallow_prepaid_card( $payment_method );

			// Create a setup intent, or update an existing one associated with the order.
			$payment_intent = $this->process_setup_intent_for_order( $subscription, $payment_information );

			// Handle saving the payment method in the store.
			if ( $payment_information['save_payment_method_to_store'] && $upe_payment_method && $upe_payment_method->get_id() === $upe_payment_method->get_retrievable_type() ) {
				$this->handle_saving_payment_method(
					$subscription,
					$payment_information['payment_method_details'],
					$selected_payment_type
				);
			}

			$redirect           = $this->get_return_url( $subscription );
			$new_payment_method = $this->get_upe_gateway_id_for_order( $upe_payment_method );

			// If the payment intent requires confirmation or action, redirect the customer to confirm the intent.
			if ( in_array( $payment_intent->status, WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION_OR_ACTION_STATUSES, true ) ) {
				// Because we're filtering woocommerce_subscriptions_update_payment_via_pay_shortcode, we need to manually set this delayed update all flag here.
				if ( isset( $_POST['update_all_subscriptions_payment_method'] ) && wc_clean( wp_unslash( $_POST['update_all_subscriptions_payment_method'] ) ) ) {
					$subscription->update_meta_data( '_delayed_update_payment_method_all', $new_payment_method );
					$subscription->save();
				}

				wp_safe_redirect( $this->get_redirect_url( $redirect, $payment_intent, $payment_information, $subscription, false ) );
				exit;
			} else {
				// Update the payment method for the subscription.
				WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, $new_payment_method );

				// Attach the new payment method ID and the customer ID to the subscription on success.
				$this->set_payment_method_id_for_subscription( $subscription, $payment_method_id );
				$this->set_customer_id_for_subscription( $subscription, $payment_information['customer'] );

				// Trigger wc_stripe_change_subs_payment_method_success action hook to preserve backwards compatibility, see process_change_subscription_payment_method().
				do_action(
					'wc_stripe_change_subs_payment_method_success',
					$payment_information['payment_method'],
					(object) [
						'token_id'       => false !== $payment_information['token'] ? $payment_information['token']->get_id() : false,
						'customer'       => $payment_information['customer'],
						'source'         => null,
						'source_object'  => $payment_method,
						'payment_method' => $payment_information['payment_method'],
					]
				);

				// Because this new payment does not require action/confirmation, remove this filter so that WC_Subscriptions_Change_Payment_Gateway proceeds to update all subscriptions if flagged.
				remove_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [ $this, 'update_payment_after_deferred_intent' ], 10 );
			}

			return [
				'result'   => 'success',
				'redirect' => $redirect,
			];
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			return [
				'result'   => 'failure',
				'redirect' => '',
			];
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
	 * @since 5.6.0 Process renewal payments for SEPA and UPE.
	 *
	 * @param float  $amount
	 * @param mixed  $renewal_order
	 * @param bool   $retry Should we retry the process?
	 * @param object $previous_error
	 */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {
		try {
			$order_id = $renewal_order->get_id();

			// Unlike regular off-session subscription payments, early renewals are treated as on-session payments, involving the customer.
			// This makes the SCA authorization popup show up for the "Renew early" modal (Subscriptions settings > Accept Early Renewal Payments via a Modal).
			// Note: Currently available for non-UPE credit card only.
			if ( isset( $_REQUEST['process_early_renewal'] ) && 'stripe' === $this->id && ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification
				$response = $this->process_payment( $order_id, true, false, $previous_error, true );

				if ( 'success' === $response['result'] && isset( $response['payment_intent_secret'] ) ) {
					$verification_url = add_query_arg(
						[
							'order'         => $order_id,
							'nonce'         => wp_create_nonce( 'wc_stripe_confirm_pi' ),
							'redirect_to'   => esc_url_raw( remove_query_arg( [ 'process_early_renewal', 'subscription_id', 'wcs_nonce' ] ) ),
							'early_renewal' => true,
						],
						WC_AJAX::get_endpoint( 'wc_stripe_verify_intent' )
					);

					echo wp_json_encode(
						[
							'stripe_sca_required' => true,
							'intent_secret'       => $response['payment_intent_secret'],
							'redirect_url'        => wp_sanitize_redirect( esc_url_raw( $verification_url ) ),
						]
					);

					exit;
				}

				// Hijack all other redirects in order to do the redirection in JavaScript.
				add_action( 'wp_redirect', [ $this, 'redirect_after_early_renewal' ], 100 );

				return;
			}

			// Check for an existing intent, which is associated with the order.
			if ( $this->has_authentication_already_failed( $renewal_order ) ) {
				return;
			}

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

			// If the payment gateway is SEPA, use the charges API.
			// TODO: Remove when SEPA is migrated to payment intents.
			if ( 'stripe_sepa' === $this->id ) {
				$request            = $this->generate_payment_request( $renewal_order, $prepared_source );
				$request['capture'] = 'true';
				$request['amount']  = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
				$response           = WC_Stripe_API::request( $request );

				$is_authentication_required = false;
			} else {
				$this->lock_order_payment( $renewal_order );
				$response                   = $this->create_and_confirm_intent_for_off_session( $renewal_order, $prepared_source, $amount );
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
							return $this->process_subscription_payment( $amount, $renewal_order, false, $response->error );
						}

						sleep( $this->retry_interval );

						++$this->retry_interval;

						return $this->process_subscription_payment( $amount, $renewal_order, true, $response->error );
					} else {
						$localized_message = sprintf(
							/* translators: 1) error message from Stripe; 2) request log URL */
							__( 'Sorry, we are unable to process the payment at this time. Reason: %1$s %2$s', 'woocommerce-gateway-stripe' ),
							$response->error->message,
							isset( $response->error->request_log_url ) ? make_clickable( $response->error->request_log_url ) : ''
						);
						$renewal_order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}
				}

				$localized_messages = WC_Stripe_Helper::get_localized_messages();

				if ( 'card_error' === $response->error->type ) {
					$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
				} elseif ( 'payment_intent_mandate_invalid' === $response->error->type ) {
					$localized_message = __(
						'The mandate used for this renewal payment is invalid. You may need to bring the customer back to your store and ask them to resubmit their payment information.',
						'woocommerce-gateway-stripe'
					);
				} else {
					$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
				}

				if ( isset( $response->error->request_log_url ) ) {
					$localized_message .= ' ' . make_clickable( $response->error->request_log_url );
				}

				$renewal_order->add_order_note( $localized_message );

				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );

			/* translators: error message */
			$renewal_order->update_status( OrderStatus::FAILED );
			$this->unlock_order_payment( $renewal_order );

			return;
		}

		try {

			// Either the charge was successfully captured, or it requires further authentication.
			if ( $is_authentication_required ) {
				do_action( 'wc_gateway_stripe_process_payment_authentication_required', $renewal_order, $response );

				$error_message = __( 'This transaction requires authentication.', 'woocommerce-gateway-stripe' );
				$renewal_order->add_order_note( $error_message );

				$charge = $this->get_latest_charge_from_intent( $response->error->payment_intent );
				$id     = $charge->id;

				$renewal_order->set_transaction_id( $id );
				/* translators: %s is the charge Id */
				$renewal_order->update_status( OrderStatus::FAILED, sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'woocommerce-gateway-stripe' ), $id ) );
				if ( is_callable( [ $renewal_order, 'save' ] ) ) {
					$renewal_order->save();
				}
			} elseif ( $this->is_charge_attempt_delayed( $response ) ) {
				$charge_attempt_at = $response->processing->card->customer_notification->completes_at;
				$attempt_date      = wp_date( get_option( 'date_format', 'F j, Y' ), $charge_attempt_at, wp_timezone() );
				$attempt_time      = wp_date( get_option( 'time_format', 'g:i a' ), $charge_attempt_at, wp_timezone() );

				$message = '';
				if ( ! empty( $response->processing->card->customer_notification->approval_requested ) ) {
					$message = sprintf(
						/* translators: 1) a date in the format yyyy-mm-dd, e.g. 2021-09-21; 2) time in the 24-hour format HH:mm, e.g. 23:04 */
						__( 'The customer must authorize this payment via the pre-debit notification sent to them by their card issuing bank, before %1$s at %2$s, when the charge will be attempted.', 'woocommerce-gateway-stripe' ),
						$attempt_date,
						$attempt_time
					);
				} else {
					$message = sprintf(
						/* translators: 1) a date in the format yyyy-mm-dd, e.g. 2021-09-21; 2) time in the 24-hour format HH:mm, e.g. 23:04 */
						__( 'The charge will be attempted on %1$s at %2$s.', 'woocommerce-gateway-stripe' ),
						$attempt_date,
						$attempt_time
					);
				}

				$renewal_order->add_order_note( $message );
				$renewal_order->update_status( OrderStatus::PENDING );
				if ( is_callable( [ $renewal_order, 'save' ] ) ) {
					$renewal_order->save();
				}

				do_action( 'wc_gateway_stripe_process_payment_subscription_charge_attempt_delayed', $response, $renewal_order );
			} else {
				// The charge was successfully captured
				do_action( 'wc_gateway_stripe_process_payment', $response, $renewal_order );

				// Use the last charge within the intent or the full response body in case of SEPA.
				$latest_charge = $this->get_latest_charge_from_intent( $response );
				$this->process_response( ( ! empty( $latest_charge ) ) ? $latest_charge : $response, $renewal_order );
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );
		}

		$this->unlock_order_payment( $renewal_order );
	}

	/**
	 * Updates other subscription sources.
	 *
	 * @since 5.6.0
	 *
	 * @param WC_Order $order              The order object.
	 * @param stdClass $source             The source object.
	 * @param string   $payment_gateway_id The payment method ID. eg 'stripe.
	 */
	public function maybe_update_source_on_subscription_order( $order, $source, $payment_gateway_id = '' ) {
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return;
		}

		$order_id = $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $order_id ) : [];
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ? wcs_get_subscriptions_for_renewal_order( $order_id ) : [];
		} else {
			$subscriptions = [];
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( '_stripe_customer_id', $source->customer );

			if ( ! empty( $source->payment_method ) ) {
				$subscription->update_meta_data( '_stripe_source_id', $source->payment_method );
			} else {
				$subscription->update_meta_data( '_stripe_source_id', $source->source );
			}

			// Update the payment method.
			if ( ! empty( $payment_gateway_id ) ) {
				$subscription->set_payment_method( $payment_gateway_id );
			}

			$subscription->save();
		}
	}

	/**
	 * Don't transfer Stripe customer/token meta to resubscribe orders.
	 *
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		$resubscribe_order->delete_meta_data( '_stripe_customer_id' );
		$resubscribe_order->delete_meta_data( '_stripe_source_id' );
		// For BW compat will remove in future.
		$resubscribe_order->delete_meta_data( '_stripe_card_id' );
		// Delete payment intent ID.
		$resubscribe_order->delete_meta_data( '_stripe_intent_id' );
		$this->delete_renewal_meta( $resubscribe_order );
		$resubscribe_order->save();
	}

	/**
	 * Don't transfer Stripe fee/ID meta to renewal orders.
	 *
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_renewal_meta( $renewal_order ) {
		WC_Stripe_Helper::delete_stripe_fee( $renewal_order );
		WC_Stripe_Helper::delete_stripe_net( $renewal_order );

		// Delete payment intent ID.
		$renewal_order->delete_meta_data( '_stripe_intent_id' );

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
		$subscription->update_meta_data( '_stripe_customer_id', $renewal_order->get_meta( '_stripe_customer_id', true ) );
		$subscription->update_meta_data( '_stripe_source_id', $renewal_order->get_meta( '_stripe_source_id', true ) );
		$subscription->save();
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$subscription_id = $subscription->get_id();
		$source_id       = $subscription->get_meta( '_stripe_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $source_id ) ) {
			$source_id = $subscription->get_meta( '_stripe_card_id', true );

			// Take this opportunity to update the key name.
			$subscription->update_meta_data( '_stripe_source_id', $source_id );
			$subscription->delete_meta_data( '_stripe_card_id' );
			$subscription->save();
		}

		$payment_meta[ $this->id ] = [
			'post_meta' => [
				'_stripe_customer_id' => [
					'value' => $subscription->get_meta( '_stripe_customer_id', true ),
					'label' => 'Stripe Customer ID',
				],
				'_stripe_source_id'   => [
					'value' => $source_id,
					'label' => 'Stripe Payment Method ID',
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
	 * @since 4.0.4 Stripe sourd id field no longer needs to be required.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array  $payment_meta associative array of meta data required for automatic payments
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
				! empty( $payment_meta['post_meta']['_stripe_source_id']['value'] ) && (
					0 !== strpos( $payment_meta['post_meta']['_stripe_source_id']['value'], 'card_' )
					&& 0 !== strpos( $payment_meta['post_meta']['_stripe_source_id']['value'], 'src_' )
					&& 0 !== strpos( $payment_meta['post_meta']['_stripe_source_id']['value'], 'pm_' )
				)
			) {
				throw new Exception( __( 'Invalid payment method ID. A valid "Stripe Payment Method ID" must begin with "src_", "pm_", or "card_".', 'woocommerce-gateway-stripe' ) );
			}
		}
	}

	/**
	 * Add the necessary information to payment intents for subscriptions to allow Stripe to create
	 * mandates for 3DS payments in India. It's ok to apply this across the board; Stripe will
	 * take care of handling any authorizations.
	 *
	 * @param array    $request          The HTTP request that will be sent to Stripe to create the payment intent.
	 * @param WC_Order $order            The renewal order.
	 * @param object   $prepared_source  The source object.
	 */
	public function add_subscription_information_to_intent( $request, $order, $prepared_source ) {
		// Just in case the order doesn't contain a subscription we return the base request.
		if ( ! $this->has_subscription( $order->get_id() ) ) {
			return $request;
		}

		$subscriptions_for_renewal_order = [];

		// Check if this is not a subscription switch. When switching we will force the creation of mandates to update the amount
		if ( ! WC_Subscriptions_Switcher::cart_contains_switches() ) {
			// TODO: maybe this isn't necessary since this function should really only be called
			//       when creating the intent? It's called in process_subscription_payment though
			//       so it's probably needed here too?
			// If we've already created a mandate for this order; use that.
			$mandate = $order->get_meta( '_stripe_mandate_id', true );
			if ( isset( $request['confirm'] ) && filter_var( $request['confirm'], FILTER_VALIDATE_BOOLEAN ) && ! empty( $mandate ) ) {
				$request['mandate'] = $mandate;
				unset( $request['setup_future_usage'] );
				return $request;
			}

			$subscriptions_for_renewal_order = function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ? wcs_get_subscriptions_for_renewal_order( $order ) : [];

			// Check if mandate already exists.
			if ( 1 === count( $subscriptions_for_renewal_order ) ) {
				$subscription_order = reset( $subscriptions_for_renewal_order );
				$mandate            = $this->get_mandate_for_subscription( $subscription_order, isset( $request['payment_method'] ) ? $request['payment_method'] : '' );

				if ( ! empty( $mandate ) ) {
					$request['confirm'] = 'true';
					$request['mandate'] = $mandate;
					unset( $request['setup_future_usage'] );
					return $request;
				}
			}
		}

		// Add mandate options to request to create new mandate if mandate id does not already exist in a previous renewal or parent order.
		// Note: This is for backwards compatibility if `_stripe_mandate_id` is not set.
		$mandate_options = $this->create_mandate_options_for_order( $order, $subscriptions_for_renewal_order );
		if ( ! empty( $mandate_options ) ) {
			if ( ! isset( $request['payment_method_options']['card']['mandate_options'] ) ) {
				$request['payment_method_options']['card']['mandate_options'] = [];
			}
			$request['payment_method_options']['card']['mandate_options'] = array_merge(
				$request['payment_method_options']['card']['mandate_options'],
				$mandate_options
			);
		}

		return $request;
	}

	/**
	 * Find the mandate id for a subscription renewal from a previous renewal order. Return the mandate id
	 * if it exists and the amount matches the renewal order amount, return empty otherwise to indicate that a
	 * new mandate should be created.
	 *
	 * @param WC_Order $order The subscription order.
	 * @return string the mandate id or empty string if no valid mandate id is found.
	 */
	private function get_mandate_for_subscription( $order, $payment_method ) {
		$renewal_order_ids = $order->get_related_orders( 'ids' );

		foreach ( $renewal_order_ids as $renewal_order_id ) {
			$renewal_order = wc_get_order( $renewal_order_id );
			if ( ! $renewal_order instanceof WC_Order ) {
				continue;
			}

			$mandate                      = $renewal_order->get_meta( '_stripe_mandate_id', true );
			$renewal_order_payment_method = $renewal_order->get_meta( '_stripe_source_id', true );

			// Return from the most recent renewal order with a valid mandate. Mandate is created against a payment method
			// in Stripe so the payment method should also match to reuse the mandate.
			if ( ! empty( $mandate ) && $renewal_order_payment_method === $payment_method ) {
				return $mandate;
			}
		}
		return '';
	}

	/**
	 * Create mandate options for a subscription order to be added to the payment intent request.
	 *
	 * @param WC_Order $order The renewal order.
	 * @param WC_Subscription[] $subscriptions Subscriptions for the renewal order.
	 * @return array the mandate_options for the subscription order.
	 */
	private function create_mandate_options_for_order( $order, $subscriptions ) {
		$mandate_options = [];
		$currency        = strtolower( $order->get_currency() );

		// We don't need to add mandate options if the currency is not supported for Indian recurring payment mandates.
		if ( ! WC_Stripe_Helper::is_currency_supported_for_indian_recurring_payment_mandate( $currency ) ) {
			return [];
		}

		$sub_amount = 0;

		$cart_contain_switches      = WC_Subscriptions_Switcher::cart_contains_switches();
		$is_changing_payment_method = $this->is_changing_payment_method_for_subscription();

		// If this is a switch order we set the mandate options based on the new subscription.
		if ( $cart_contain_switches ) {
			foreach ( WC()->cart->cart_contents as $cart_item ) {
				$subscription_price = WC_Subscriptions_Product::get_price( $cart_item['data'] );
				$sub_amount        += (int) WC_Stripe_Helper::get_stripe_amount( $subscription_price, $currency );
			}

			// Get the first cart item associated with this order.
			$cart_item = reset( WC()->cart->cart_contents );

			$sub_billing_period   = WC_Subscriptions_Product::get_period( $cart_item['data'] );
			$sub_billing_interval = absint( WC_Subscriptions_Product::get_interval( $cart_item['data'] ) );
		} elseif ( $is_changing_payment_method ) {
			// On the change payment method page, the $order object sent in this function is actually a subscription.
			$subscription = $order;

			$sub_amount           = WC_Stripe_Helper::get_stripe_amount( $subscription->get_subtotal(), $currency );
			$sub_billing_period   = strtolower( $subscription->get_billing_period() );
			$sub_billing_interval = $subscription->get_billing_interval();
		} else {
			// If this is the first order, not a renewal, then get the subscriptions for the parent order.
			if ( empty( $subscriptions ) ) {
				$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $order ) : [];
			}

			// If there are no subscriptions we just return since mandates aren't required.
			if ( 0 === count( $subscriptions ) ) {
				return [];
			}

			foreach ( $subscriptions as $sub ) {
				$sub_amount += WC_Stripe_Helper::get_stripe_amount( $sub->get_total(), $currency );
			}

			// Get the first subscription associated with this order.
			$sub = reset( $subscriptions );

			$sub_billing_period   = strtolower( $sub->get_billing_period() );
			$sub_billing_interval = $sub->get_billing_interval();
		}

		// If the amount is 0 we don't need to create a mandate since we won't be charging anything.
		// And there won't be any renewal for this free subscription.
		if ( 0 === $sub_amount ) {
			return [];
		}

		$has_interval = $sub_billing_period && $sub_billing_interval > 0;
		if ( $has_interval && ( 1 === count( $subscriptions ) || $cart_contain_switches || $is_changing_payment_method ) ) {
			$mandate_options['amount_type']    = 'fixed';
			$mandate_options['interval']       = $sub_billing_period;
			$mandate_options['interval_count'] = $sub_billing_interval;
		} else {
			// If there are multiple subscriptions the amount_type becomes 'maximum' so we can charge anything
			// less than the order total, and the interval is sporadic so we don't have to follow a set interval.
			$mandate_options['amount_type'] = 'maximum';
			$mandate_options['interval']    = 'sporadic';
		}

		$mandate_options['amount']          = $sub_amount;
		$mandate_options['reference']       = $order->get_id();
		$mandate_options['start_date']      = time();
		$mandate_options['supported_types'] = [ 'india' ];

		return $mandate_options;
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 * @version 5.6.0
	 *
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

		$stripe_source_id = $subscription->get_meta( '_stripe_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $stripe_source_id ) ) {
			$stripe_source_id = $subscription->get_meta( '_stripe_card_id', true );

			// Take this opportunity to update the key name.
			$subscription->update_meta_data( '_stripe_source_id', $stripe_source_id );
			$subscription->save();
		}

		$stripe_customer    = new WC_Stripe_Customer();
		$stripe_customer_id = $subscription->get_meta( '_stripe_customer_id', true );

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
			$parent_order       = wc_get_order( $subscription->get_parent_id() );
			$stripe_customer_id = $parent_order->get_meta( '_stripe_customer_id', true );
			$stripe_source_id   = $parent_order->get_meta( '_stripe_source_id', true );

			// For BW compat will remove in future.
			if ( empty( $stripe_source_id ) ) {
				$stripe_source_id = $parent_order->get_meta( '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				$parent_order->update_meta_data( '_stripe_source_id', $stripe_source_id );
				$parent_order->save();
			}
		}

		$stripe_customer->set_id( $stripe_customer_id );

		$payment_method_to_display = __( 'N/A', 'woocommerce-gateway-stripe' );

		try {
			// Retrieve all possible payment methods for subscriptions.
			foreach ( WC_Stripe_Customer::STRIPE_PAYMENT_METHODS as $payment_method_type ) {
				foreach ( $stripe_customer->get_payment_methods( $payment_method_type ) as $source ) {
					if ( $source->id !== $stripe_source_id ) {
						continue;
					}

					// Legacy handling for Stripe Card objects. ref: https://docs.stripe.com/api/cards/object
					if ( isset( $source->object ) && WC_Stripe_Payment_Methods::CARD === $source->object ) {
						/* translators: 1) card brand 2) last 4 digits */
						$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ), ( isset( $source->brand ) ? wc_get_credit_card_type_label( $source->brand ) : __( 'N/A', 'woocommerce-gateway-stripe' ) ), $source->last4 );
						break 2;
					}

					switch ( $source->type ) {
						case WC_Stripe_Payment_Methods::CARD:
							/* translators: 1) card brand 2) last 4 digits */
							$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ), ( isset( $source->card->brand ) ? wc_get_credit_card_type_label( $source->card->brand ) : __( 'N/A', 'woocommerce-gateway-stripe' ) ), $source->card->last4 );
							break 3;
						case WC_Stripe_Payment_Methods::SEPA_DEBIT:
							/* translators: 1) last 4 digits of SEPA Direct Debit */
							$payment_method_to_display = sprintf( __( 'Via SEPA Direct Debit ending in %1$s', 'woocommerce-gateway-stripe' ), $source->sepa_debit->last4 );
							break 3;
						case WC_Stripe_Payment_Methods::CASHAPP_PAY:
							/* translators: 1) Cash App Cashtag */
							$payment_method_to_display = sprintf( __( 'Via Cash App Pay (%1$s)', 'woocommerce-gateway-stripe' ), $source->cashapp->cashtag );
							break 3;
						case WC_Stripe_Payment_Methods::LINK:
							/* translators: 1) email address associated with the Stripe Link payment method */
							$payment_method_to_display = sprintf( __( 'Via Stripe Link (%1$s)', 'woocommerce-gateway-stripe' ), $source->link->email );
							break 3;
						case WC_Stripe_Payment_Methods::ACH:
							$payment_method_to_display = sprintf(
								/* translators: 1) account type (checking, savings), 2) last 4 digits of account. */
								__( 'Via %1$s Account ending in %2$s', 'woocommerce-gateway-stripe' ),
								ucfirst( $source->us_bank_account->account_type ),
								$source->us_bank_account->last4
							);
							break 3;
						case WC_Stripe_Payment_Methods::BECS_DEBIT:
							$payment_method_to_display = sprintf(
								/* translators: last 4 digits of account. */
								__( 'BECS Direct Debit ending in %s', 'woocommerce-gateway-stripe' ),
								$source->au_becs_debit->last4
							);
							break 3;
						case WC_Stripe_Payment_Methods::ACSS_DEBIT:
							$payment_method_to_display = sprintf(
								/* translators: 1) bank name, 2) last 4 digits of account. */
								__( 'Via %1$s ending in %2$s', 'woocommerce-gateway-stripe' ),
								$source->acss_debit->bank_name,
								$source->acss_debit->last4
							);
							break 3;
						case WC_Stripe_Payment_Methods::BACS_DEBIT:
							/* translators: 1) the Bacs Direct Debit payment method's last 4 numbers */
							$payment_method_to_display = sprintf( __( 'Via Bacs Direct Debit ending in (%1$s)', 'woocommerce-gateway-stripe' ), $source->bacs_debit->last4 );
							break 3;
						case WC_Stripe_Payment_Methods::AMAZON_PAY:
							/* translators: 1) the Amazon Pay payment method's email */
							$payment_method_to_display = sprintf( __( 'Via Amazon Pay (%1$s)', 'woocommerce-gateway-stripe' ), $source->billing_details->email ?? '' );
							break 3;
					}
				}
			}
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}

		return $payment_method_to_display;
	}

	/**
	 * If this is the "Pass the SCA challenge" flow, remove a variable that is checked by WC Subscriptions
	 * so WC Subscriptions doesn't redirect to the checkout
	 */
	public function remove_order_pay_var() {
		global $wp;
		if ( isset( $_GET['wc-stripe-confirmation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->order_pay_var         = $wp->query_vars['order-pay']; // @phpstan-ignore-line (order_pay_var is defined in classes using this class)
			$wp->query_vars['order-pay'] = null;
		}
	}

	/**
	 * Restore the variable that was removed in remove_order_pay_var()
	 */
	public function restore_order_pay_var() {
		global $wp;
		if ( isset( $this->order_pay_var ) ) {
			$wp->query_vars['order-pay'] = $this->order_pay_var;
		}
	}

	/**
	 * Checks if a renewal already failed because a manual authentication is required.
	 *
	 * @param WC_Order $renewal_order The renewal order.
	 * @return boolean
	 */
	public function has_authentication_already_failed( $renewal_order ) {
		$existing_intent = $this->get_intent_from_order( $renewal_order );

		if (
			! $existing_intent
			|| WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD !== $existing_intent->status
			|| empty( $existing_intent->last_payment_error )
			|| 'authentication_required' !== $existing_intent->last_payment_error->code
		) {
			return false;
		}

		// Make sure all emails are instantiated.
		WC_Emails::instance();

		/**
		 * A payment attempt failed because SCA authentication is required.
		 *
		 * @param WC_Order $renewal_order The order that is being renewed.
		 */
		do_action( 'wc_gateway_stripe_process_payment_authentication_required', $renewal_order );

		// Fail the payment attempt (order would be currently pending because of retry rules).
		$charge    = $this->get_latest_charge_from_intent( $existing_intent );
		$charge_id = $charge->id;
		/* translators: %s is the stripe charge Id */
		$renewal_order->update_status( OrderStatus::FAILED, sprintf( __( 'Stripe charge awaiting authentication by user: %s.', 'woocommerce-gateway-stripe' ), $charge_id ) );

		return true;
	}

	/**
	 * Hijacks `wp_redirect` in order to generate a JS-friendly object with the URL.
	 *
	 * @param string $url The URL that Subscriptions attempts a redirect to.
	 * @return void
	 */
	public function redirect_after_early_renewal( $url ) {
		echo wp_json_encode(
			[
				'stripe_sca_required' => false,
				'redirect_url'        => $url,
			]
		);

		exit;
	}

	/**
	 * Once an intent has been verified, perform some final actions for early renewals.
	 *
	 * @param WC_Order $order The renewal order.
	 * @param stdClass $intent The Payment Intent object.
	 */
	protected function maybe_process_subscription_early_renewal_success( $order, $intent ) {
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() && isset( $_GET['early_renewal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( function_exists( 'wcs_update_dates_after_early_renewal' ) && function_exists( 'wcs_get_subscription' ) ) {
				wcs_update_dates_after_early_renewal( wcs_get_subscription( $order->get_meta( '_subscription_renewal' ) ), $order );
			}
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Your early renewal order was successful.', 'woocommerce-gateway-stripe' ), 'success' );
			}
		}
	}

	/**
	 * During early renewals, instead of failing the renewal order, delete it and let Subs redirect to the checkout.
	 *
	 * @param WC_Order $order The renewal order.
	 * @param stdClass $intent The Payment Intent object (unused).
	 */
	protected function maybe_process_subscription_early_renewal_failure( $order, $intent ) {
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() && isset( $_GET['early_renewal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$order->delete( true );
			wc_add_notice( __( 'Payment authorization for the renewal order was unsuccessful, please try again.', 'woocommerce-gateway-stripe' ), 'error' );
			$renewal_url = ( function_exists( 'wcs_get_early_renewal_url' ) && function_exists( 'wcs_get_subscription' ) )
				? wcs_get_early_renewal_url( wcs_get_subscription( $order->get_meta( '_subscription_renewal' ) ) )
				: '';
			wp_safe_redirect( $renewal_url );
			exit;
		}
	}

	/**
	 * Returns true if Stripe will attempt the charges at a future date.
	 *
	 * This applies to subscription payments and Indian 3DS regulations.
	 * https://docs.stripe.com/india-recurring-payments?integration=subscriptions#predebit-notification
	 *
	 * @param StdClass $payment_intent the Payment Intent to be evaluated.
	 * @return bool true if the charge attempt is delayed, false otherwise.
	 */
	protected function is_charge_attempt_delayed( $payment_intent ) {
		return ! empty( $payment_intent->status )
			&& WC_Stripe_Intent_Status::PROCESSING === $payment_intent->status
			&& ! empty( $payment_intent->processing->card->customer_notification->completes_at );
	}

	/**
	 * Updates the payment method for all subscriptions related to an order.
	 *
	 * @param WC_Order $order               The order to update the related subscriptions for.
	 * @param string   $payment_method_type The payment method ID. eg 'stripe', 'stripe_sepa'.
	 */
	public function update_subscription_payment_method_from_order( $order, $payment_method_type ) {
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		foreach ( wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] ) as $subscription ) {
			$subscription->set_payment_method( $payment_method_type );
			$subscription->save();
		}
	}

	/**
	 * Disables the ability to edit a subscription for orders with mandates.
	 *
	 * @param $editable boolean The current editability of the subscription.
	 * @param $order WC_Order The order object.
	 * @return boolean true if the subscription can be edited, false otherwise.
	 */
	public function disable_subscription_edit_for_india( $editable, $order ) {
		$parent_order = wc_get_order( $order->get_parent_id() );
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled()
			&& $this->is_subscription( $order )
			&& $parent_order
			&& ! empty( $parent_order->get_meta( '_stripe_mandate_id', true ) ) ) {
			$editable = false;
		}

		return $editable;
	}

	/**
	 * When handling a subscription change payment method request with deferred intents,
	 * don't immediately update the subscription's payment method to Stripe until we've created and confirmed the setup intent.
	 *
	 * For purchases with a 3DS card specifically, we don't want to update the payment method on the subscription until after the customer has authenticated.
	 *
	 * @param bool            $update_payment_method Whether to update the payment method.
	 * @param string          $new_payment_method    The new payment method.
	 * @param WC_Subscription $subscription          The subscription.
	 *
	 * @return bool
	 */
	public function update_payment_after_deferred_intent( $update_payment_method, $new_payment_method, $subscription ) {
		if ( ! $this->is_changing_payment_method_for_subscription() || $new_payment_method !== $this->id || empty( $_POST['wc-stripe-is-deferred-intent'] ) ) {
			return $update_payment_method;
		}

		return false;
	}
}
