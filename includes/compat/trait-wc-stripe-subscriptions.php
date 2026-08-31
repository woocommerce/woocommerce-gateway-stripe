<?php

use Automattic\WooCommerce\Enums\OrderStatus;
use Automattic\WooCommerce\Enums\PaymentGatewayFeature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Subscriptions compatibility.
 */
trait WC_Stripe_Subscriptions_Trait {

	use WC_Stripe_Subscriptions_Utilities_Trait;

	/**
	 * Initialize subscription support and hooks.
	 *
	 * @return void
	 * @since 5.6.0
	 */
	public function maybe_init_subscriptions() {
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return;
		}

		$this->supports = array_merge(
			$this->supports,
			[
				PaymentGatewayFeature::SUBSCRIPTIONS,
				PaymentGatewayFeature::SUBSCRIPTION_CANCELLATION,
				PaymentGatewayFeature::SUBSCRIPTION_SUSPENSION,
				PaymentGatewayFeature::SUBSCRIPTION_REACTIVATION,
				PaymentGatewayFeature::SUBSCRIPTION_AMOUNT_CHANGES,
				PaymentGatewayFeature::SUBSCRIPTION_DATE_CHANGES,
				PaymentGatewayFeature::SUBSCRIPTION_PAYMENT_METHOD_CHANGE,
				PaymentGatewayFeature::SUBSCRIPTION_PAYMENT_METHOD_CHANGE_CUSTOMER,
				PaymentGatewayFeature::SUBSCRIPTION_PAYMENT_METHOD_CHANGE_ADMIN,
				PaymentGatewayFeature::MULTIPLE_SUBSCRIPTIONS,
			]
		);

		$this->maybe_register_payment_method_hooks( $this->id );
		$this->maybe_register_plugin_hooks();
	}

	/**
	 * Maybe register the payment method hooks for subscriptions.
	 * These are registered once per payment method.
	 *
	 * @param string $payment_method_id The payment method ID to register the hooks for.
	 * @return void
	 */
	private function maybe_register_payment_method_hooks( string $payment_method_id ): void {
		// Ensure we register the subscription hooks only once per payment method.
		$hook_manager = WC_Stripe_Hook_Manager::get_instance();
		if ( ! $hook_manager->is_valid_payment_method_id( $payment_method_id ) ) {
			WC_Stripe_Logger::error(
				sprintf( "Skipping subscription hook registration: invalid payment method ID '%s'. Renewals will not be processed.", $payment_method_id ),
				[
					'payment_method_id' => $payment_method_id,
					'current_class'     => get_class( $this ),
				]
			);
			return;
		}

		if ( $hook_manager->are_payment_method_hooks_registered( $payment_method_id, WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) ) {
			return;
		}

		/*
		 * The hooks below use the first instance to register for a given payment method ID.
		 * This is especially important for the 'stripe' ID, which is used by the main gateway
		 * {@see WC_Stripe_UPE_Payment_Gateway} and the Optimized Checkout payment method
		 * {@see WC_Stripe_UPE_Payment_Method_OC}.
		 *
		 * Note that the main gateway is constructed during WC_Stripe::init(), while the OCS
		 * payment method is only instantiated lazily during request rendering, after the
		 * main gateway instance has been constructed.
		 */
		add_action( 'woocommerce_scheduled_subscription_payment_' . $payment_method_id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $payment_method_id, [ $this, 'update_failing_payment_method' ], 10, 2 );

		add_action( 'wc_stripe_payment_fields_' . $payment_method_id, [ $this, 'display_update_subs_payment_checkout' ] );
		add_action( 'wc_stripe_add_payment_method_' . $payment_method_id . '_success', [ $this, 'handle_add_payment_method_success' ], 10, 2 );
		add_action( 'woocommerce_stripe_add_payment_method', [ $this, 'handle_upe_add_payment_method_success' ], 10, 2 );

		// Display the payment method used for a subscription in the "My Subscriptions" table.
		add_filter( 'woocommerce_my_subscriptions_payment_method', [ $this, 'maybe_render_subscription_payment_method' ], 10, 2 );

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );

		// Validate the payment method meta data set on a subscription.
		add_action( 'woocommerce_subscription_validate_payment_meta', [ $this, 'validate_subscription_payment_meta' ], 10, 2 );

		$hook_manager->register_payment_method_hooks( $payment_method_id, WC_Stripe_Hook_Categories::SUBSCRIPTIONS );
	}

	/**
	 * Maybe register the plugin-level hooks for subscriptions.
	 * These should only be registered once for the main gateway instance.
	 *
	 * @return bool True if the hooks were registered, false otherwise.
	 * @since 10.9.0
	 */
	private function maybe_register_plugin_hooks(): bool {
		if ( $this instanceof WC_Stripe_UPE_Payment_Gateway ) {
			return $this->maybe_register_gateway_subscription_hooks();
		}

		return false;
	}

	/**
	 * Displays a checkbox to allow users to update all subs payments with new
	 * payment.
	 *
	 * @return void
	 * @since 4.1.11
	 */
	public function display_update_subs_payment_checkout() {
		/**
		 * Filters the subscription statuses eligible for payment method updates from checkout.
		 *
		 * @param string[] $statuses Subscription statuses eligible for payment method updates.
		 */
		$statuses = apply_filters( 'wc_stripe_update_subs_payment_method_card_statuses', [ 'active' ] );

		/**
		 * Filters whether to display the checkbox for updating current subscriptions.
		 *
		 * @param bool $display Whether to display the checkbox.
		 */
		if ( ! apply_filters( 'wc_stripe_display_update_subs_payment_method_card_checkbox', true ) ) {
			return;
		}

		if ( ! is_add_payment_method_page() ) {
			return;
		}

		if ( function_exists( 'wcs_user_has_subscription' ) && wcs_user_has_subscription( get_current_user_id(), '', $statuses ) ) {
			/**
			 * Filters the checkbox label for updating current subscriptions.
			 *
			 * @param string $label Checkbox label.
			 */
			$label = esc_html( apply_filters( 'wc_stripe_save_to_subs_text', __( 'Update the payment method for all of my current subscriptions', 'woocommerce-gateway-stripe' ) ) );
			$id    = sprintf( 'wc-%1$s-update-subs-payment-method-card', $this->id );
			woocommerce_form_field(
				$id,
				[
					'type'        => 'checkbox',
					'label'       => $label,
					/**
					 * Filters whether the update-subscriptions checkbox is checked by default.
					 *
					 * @param bool $checked Whether the checkbox is checked by default.
					 */
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
	 * @param string $source_id     The source ID.
	 * @param object $source_object The source object.
	 * @return void
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
	 * @return void
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

		/** This filter is documented in includes/compat/trait-wc-stripe-subscriptions.php. */
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
	 * @return void
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

			/**
			 * Fires after a subscription payment method is changed successfully.
			 *
			 * @param string $source          Stripe source or payment method ID.
			 * @param object $prepared_source Prepared source object.
			 */
			do_action( 'wc_stripe_change_subs_payment_method_success', $prepared_source->source, $prepared_source );

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $subscription ),
			];
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::error( 'Error processing change subscription payment method for subscription: ' . $order_id, [ 'error_message' => $e->getMessage() ] );
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

		$express_checkout_type = isset( $_POST['express_checkout_type'] ) && is_string( $_POST['express_checkout_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wc_clean( wp_unslash( $_POST['express_checkout_type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';
		$express_checkout_type = in_array( $express_checkout_type, WC_Stripe_Payment_Methods::EXPRESS_PAYMENT_METHODS, true ) ? $express_checkout_type : '';

		$is_express_checkout_submission = '' !== $express_checkout_type;

		// ECE confirms before the shopper sees the "update all subscriptions"
		// checkbox, so its default-checked state can't be treated as consent.
		if ( $is_express_checkout_submission ) {
			unset( $_POST['update_all_subscriptions_payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			// The change-payment form carries the saved-cards selector value, so
			// is_using_saved_payment_method() would otherwise route to the old
			// saved token and discard the ECE-supplied payment method.
			$_POST['wc-stripe-payment-token'] = 'new'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
			$saved_payment_method_to_store = $payment_information['save_payment_method_to_store'] && $upe_payment_method && $upe_payment_method->get_id() === $upe_payment_method->get_retrievable_type();
			if ( $saved_payment_method_to_store ) {
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

				// Persist the express type and the new payment method ID for the
				// post-confirmation hooks (title override + token replacement),
				// or clear any stale markers when this submission isn't express.
				if ( $is_express_checkout_submission ) {
					$subscription->update_meta_data( '_wc_stripe_express_checkout_type', $express_checkout_type );
					$subscription->update_meta_data( '_wc_stripe_express_checkout_payment_method_id', $payment_method_id );
				} else {
					$subscription->delete_meta_data( '_wc_stripe_express_checkout_type' );
					$subscription->delete_meta_data( '_wc_stripe_express_checkout_payment_method_id' );
				}
				$subscription->save();

				wp_safe_redirect( $this->get_redirect_url( $redirect, $payment_intent, $payment_information, $subscription, false ) );
				exit;
			} else {
				// Update the payment method for the subscription.
				WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, $new_payment_method );

				// Attach the new payment method ID and the customer ID to the subscription on success.
				$this->set_payment_method_id_for_subscription( $subscription, $payment_method_id );
				$this->set_customer_id_for_subscription( $subscription, $payment_information['customer'] );

				// If we saved a new token, link the saved token to the subscription for display purposes.
				// Intentionally ignore any failures, as the display update doesn't affect renewals.
				if ( $saved_payment_method_to_store && ! WC_Stripe_Express_Checkout_Helper::replace_subscription_payment_token( $subscription, $payment_method_id ) ) {
					WC_Stripe_Logger::error( 'Could not re-associate the saved token after change-payment for subscription: ' . $subscription_id );
				}

				/**
				 * This action is documented in includes/compat/trait-wc-stripe-subscriptions.php.
				 */
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
			WC_Stripe_Logger::error( 'Error processing change subscription payment method with deferred intent for subscription: ' . $subscription_id, [ 'error_message' => $e->getMessage() ] );

			return [
				'result'   => 'failure',
				'redirect' => '',
			];
		}
	}

	/**
	 * Process a scheduled subscription payment,
	 * most commonly via the 'woocommerce_scheduled_subscription_payment_<payment_method>' action.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $renewal_order    A WC_Order object created to record the renewal payment.
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->process_subscription_payment( $amount_to_charge, $renewal_order, true, false );
	}

	/**
	 * Determines whether a failed payment was blocked by Stripe Radar.
	 *
	 * When Stripe Radar blocks a charge it still creates a charge object (for audit
	 * purposes) with outcome.type === 'blocked'. The charge ID is surfaced in the
	 * error response so we can fetch it and inspect the outcome.
	 *
	 * @param object $response The Stripe API response containing the error.
	 * @return string|false The Radar block reason (e.g. 'highest_risk_level', 'rule') when the
	 *                      charge is blocked, false otherwise.
	 */
	protected function is_charge_blocked_by_radar( $response ) {
		$charge_id = null;

		// For both the Charges API (e.g. SEPA) and the Payment Intents API, the
		// charge ID appears at error.charge when a charge was actually attempted.
		if ( ! empty( $response->error->charge ) && is_string( $response->error->charge ) ) {
			$charge_id = $response->error->charge;
		} elseif ( ! empty( $response->error->payment_intent->latest_charge ) && is_string( $response->error->payment_intent->latest_charge ) ) {
			// Fallback: Payment Intents API may surface the charge ID via latest_charge.
			$charge_id = $response->error->payment_intent->latest_charge;
		}

		if ( empty( $charge_id ) ) {
			return false;
		}

		$charge = WC_Stripe_API::retrieve( "charges/{$charge_id}" );

		if ( is_wp_error( $charge ) || empty( $charge ) || ! empty( $charge->error ) ) {
			return false;
		}

		if ( isset( $charge->outcome->type ) && 'blocked' === $charge->outcome->type ) {
			return isset( $charge->outcome->reason ) ? (string) $charge->outcome->reason : 'unknown';
		}

		return false;
	}

	/**
	 * Process a payment for a subscription renewal.
	 *
	 * @since 3.0
	 * @since 4.0.4 Add third parameter flag to retry.
	 * @since 4.1.0 Add fourth parameter to log previous errors.
	 * @since 5.6.0 Process renewal payments for SEPA and UPE.
	 *
	 * @param float        $amount         The amount to charge.
	 * @param WC_Order     $renewal_order  The renewal order.
	 * @param bool         $retry          Should we retry the process?
	 * @param object|false $previous_error Previous error object.
	 * @return void
	 */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {
		$order_helper       = WC_Stripe_Order_Helper::get_instance();
		$order_id           = $renewal_order->get_id();
		$payment_lock_order = clone $renewal_order;

		// Reject malformed lock metadata before acquisition.
		$existing_lock = $order_helper->get_order_existing_payment_lock( $payment_lock_order );

		if ( null !== $existing_lock && ! is_scalar( $existing_lock ) ) {
			$this->record_invalid_existing_subscription_payment_lock( $renewal_order, $existing_lock );
			return;
		}

		$acquired_lock = $order_helper->acquire_order_payment_lock( $payment_lock_order );

		// Keep lock ownership through retries and response processing.
		if ( false === $acquired_lock ) {
			WC_Stripe_Logger::error( "Stripe: skipping renewal attempt for order {$order_id} because its payment lock could not be acquired or verified; another payment attempt may already be in progress." );
			$renewal_order->add_order_note( __( 'Stripe: skipped this renewal payment attempt because its payment lock could not be acquired or verified. Another payment attempt may already be in progress.', 'woocommerce-gateway-stripe' ) );
			return;
		}

		// Use the acquired token directly to avoid adopting another worker's lock.
		$is_valid_lock = $this->is_valid_subscription_payment_lock( $acquired_lock );

		if ( ! $is_valid_lock ) {
			WC_Stripe_Logger::error(
				"Stripe: cannot process subscription renewal for order {$order_id} because the acquired payment lock is invalid.",
				[
					'order_id'     => $order_id,
					'payment_lock' => $acquired_lock,
				]
			);
			$renewal_order->add_order_note( __( 'Stripe: this renewal payment could not be processed because its payment lock could not be verified.', 'woocommerce-gateway-stripe' ) );
			return;
		}

		$lock_expiry                 = $this->get_subscription_payment_lock_expiry( $acquired_lock );
		$acquired_lock               = (string) $acquired_lock;
		$has_retry_interval          = property_exists( $this, 'retry_interval' ); // @phpstan-ignore function.alreadyNarrowedType
		$retry_interval              = $has_retry_interval
			? max( 0, (int) $this->retry_interval ) // @phpstan-ignore-line (retry_interval is defined on the main gateway)
			: 1;
		$should_release_payment_lock = true;

		try {
			$this->process_subscription_payment_attempt( $amount, $renewal_order, $retry, $previous_error, $lock_expiry, $acquired_lock, $should_release_payment_lock, $retry_interval );
		} finally {
			if ( $should_release_payment_lock && $order_helper->is_order_payment_lock_owned( $payment_lock_order, $acquired_lock ) ) {
				$order_helper->unlock_order_payment_if_owned( $payment_lock_order, $acquired_lock );
			}
		}
	}

	/**
	 * Checks whether a payment lock is valid.
	 *
	 * @param mixed $lock Payment lock metadata value.
	 * @return bool
	 */
	private function is_valid_subscription_payment_lock( $lock ): bool {
		return 0 < $this->get_subscription_payment_lock_expiry( $lock );
	}

	/**
	 * Returns the expiry from a legacy or owner-token lock.
	 *
	 * @param mixed $lock Payment lock metadata value.
	 * @return int Positive expiry timestamp, or zero for an invalid lock.
	 */
	private function get_subscription_payment_lock_expiry( $lock ): int {
		if ( is_int( $lock ) ) {
			return 0 < $lock ? $lock : 0;
		}

		if ( ! is_string( $lock ) || '' === $lock ) {
			return 0;
		}

		$parts = explode( '|', $lock, 2 );

		if ( ! ctype_digit( $parts[0] ) || 0 >= (int) $parts[0] ) {
			return 0;
		}

		// Empty owner tokens are invalid.
		if ( isset( $parts[1] ) && '' === $parts[1] ) {
			return 0;
		}

		return (int) $parts[0];
	}

	/**
	 * Records malformed payment-lock metadata.
	 *
	 * @param WC_Order $renewal_order Renewal order being processed.
	 * @param mixed    $existing_lock Existing lock metadata.
	 * @return void
	 */
	private function record_invalid_existing_subscription_payment_lock( WC_Order $renewal_order, $existing_lock ): void {
		$context = [
			'order_id'     => $renewal_order->get_id(),
			'payment_lock' => $existing_lock,
		];

		WC_Stripe_Logger::error(
			"Stripe: cannot process subscription renewal for order {$context['order_id']} because its existing payment lock is invalid.",
			$context
		);
		$renewal_order->add_order_note( __( 'Stripe: this renewal payment could not be processed because its existing payment lock could not be verified.', 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Checks whether this worker still owns the payment lock.
	 *
	 * @param WC_Order $renewal_order Renewal order being processed.
	 * @param string   $expected_lock Exact lock value acquired by this process.
	 * @return bool
	 * @phpstan-impure Reads mutable order metadata.
	 */
	private function is_subscription_payment_lock_unchanged( WC_Order $renewal_order, string $expected_lock ): bool {
		if ( '' === $expected_lock ) {
			return false;
		}

		return WC_Stripe_Order_Helper::get_instance()->is_order_payment_lock_owned( $renewal_order, $expected_lock );
	}

	/**
	 * Checks payment-lock ownership and expiry.
	 *
	 * @param WC_Order $renewal_order Renewal order being processed.
	 * @param int      $lock_expiry   Unix timestamp at which the lock expires.
	 * @param string   $expected_lock Exact lock value acquired by this process.
	 * @return bool
	 * @phpstan-impure Reads mutable order metadata and the current time.
	 */
	private function is_subscription_payment_lock_unchanged_and_unexpired( WC_Order $renewal_order, int $lock_expiry, string $expected_lock ): bool {
		return $this->is_subscription_payment_lock_unchanged( $renewal_order, $expected_lock )
			&& time() <= $lock_expiry;
	}

	/**
	 * Stops a renewal when its payment lock is invalid.
	 *
	 * @param WC_Order $renewal_order Renewal order being processed.
	 * @param int      $lock_expiry   Unix timestamp at which the lock expires.
	 * @param string   $expected_lock Exact lock value acquired by this process.
	 * @param object|false $previous_error Stripe error from the preceding retry attempt, if any.
	 * @return bool
	 * @phpstan-impure Reads mutable order metadata and the current time.
	 * @throws WC_Stripe_Exception When a previous Stripe error must be reported.
	 */
	private function can_continue_subscription_payment_attempt( WC_Order $renewal_order, int $lock_expiry, string $expected_lock, $previous_error = false ): bool {
		if ( $this->is_subscription_payment_lock_unchanged_and_unexpired( $renewal_order, $lock_expiry, $expected_lock ) ) {
			return true;
		}

		$order_id = $renewal_order->get_id();
		WC_Stripe_Logger::error( "Stripe: not continuing a subscription renewal attempt for order {$order_id} because its payment lock is no longer valid." );
		$renewal_order->add_order_note( __( 'Stripe: this renewal payment was not attempted because its payment lock was no longer valid before the charge began.', 'woocommerce-gateway-stripe' ) );

		// Report the previous Stripe error even if the retry lost its lock.
		if ( is_object( $previous_error ) ) {
			throw $this->get_subscription_payment_exception_from_error( $previous_error );
		}

		return false;
	}

	/**
	 * Checks whether this worker may record a failed payment.
	 *
	 * @param WC_Order $renewal_order Renewal order being processed.
	 * @param string   $expected_lock Exact lock value acquired by this process.
	 * @return bool
	 * @phpstan-impure Reads mutable order metadata.
	 */
	private function can_apply_subscription_payment_failure( WC_Order $renewal_order, string $expected_lock ): bool {
		$lock_expiry = $this->get_subscription_payment_lock_expiry( $expected_lock );

		if ( time() <= $lock_expiry && $this->is_subscription_payment_lock_unchanged( $renewal_order, $expected_lock ) ) {
			return true;
		}

		$order_id = $renewal_order->get_id();
		WC_Stripe_Logger::error( "Stripe: not recording a failed renewal attempt for order {$order_id} because its payment lock no longer matches the acquired value." );

		return false;
	}

	/**
	 * Returns the timeout budget for one renewal attempt.
	 *
	 * Three Stripe calls may run before renewal; a fourth timeout is the safety margin.
	 *
	 * @return int Timeout budget in seconds.
	 */
	private function get_subscription_payment_request_timeout_budget(): int {
		return 4 * WC_Stripe_API::REQUEST_TIMEOUT;
	}

	/**
	 * Checks whether a renewal retry needs a new idempotency key.
	 *
	 * The retry count is passed because UPE gateways proxy these calls.
	 *
	 * @param object       $source_object  Prepared Stripe source.
	 * @param object|false $previous_error Error returned by the preceding attempt.
	 * @param int          $retry_interval Current retry interval and idempotency sequence.
	 * @return bool
	 */
	private function should_change_subscription_renewal_idempotency_key( object $source_object, $previous_error, int $retry_interval ): bool {
		if ( ! is_object( $previous_error ) || 1 >= $retry_interval ) {
			return false;
		}

		$error_data = get_object_vars( $previous_error );

		return ! empty( $source_object->status )
			&& 'chargeable' === $source_object->status
			&& 'idempotency_error' === ( $error_data['type'] ?? '' )
			&& is_string( $error_data['message'] ?? null )
			&& 1 === preg_match( '/Keys for idempotent requests can only be used with the same parameters they were first used with./i', $error_data['message'] );
	}

	/**
	 * Builds a renewal retry idempotency key.
	 *
	 * @param mixed  $idempotency_key Existing idempotency key.
	 * @param mixed  $request         Stripe request data.
	 * @param int    $retry_interval  Current retry interval and idempotency sequence.
	 * @return mixed
	 */
	private function change_subscription_renewal_idempotency_key( $idempotency_key, $request, int $retry_interval ) {
		// Keep the original key for requests without order metadata.
		if ( ! is_array( $request ) || empty( $request['metadata']['order_id'] ) ) {
			return $idempotency_key;
		}

		$customer = ! empty( $request['customer'] ) ? $request['customer'] : '';
		$source   = ! empty( $request['source'] ) ? $request['source'] : $customer;

		return $request['metadata']['order_id'] . '-' . $retry_interval . '-' . $source;
	}

	/**
	 * Builds an exception from the previous Stripe error.
	 *
	 * @param object      $error    Stripe error from the preceding attempt.
	 * @param object|null $response Full Stripe response when available.
	 * @return WC_Stripe_Exception
	 */
	private function get_subscription_payment_exception_from_error( object $error, $response = null ): WC_Stripe_Exception {
		$exception_response = is_object( $response ) ? $response : (object) [ 'error' => $error ];
		$error_data         = get_object_vars( $error );

		if ( 'payment_intent_mandate_invalid' === ( $error_data['type'] ?? '' ) ) {
			$localized_message = __(
				'The mandate used for this renewal payment is invalid. You may need to bring the customer back to your store and ask them to resubmit their payment information.',
				'woocommerce-gateway-stripe'
			);
		} else {
			$localized_message = WC_Stripe_Helper::get_localized_error_message_from_response( $exception_response );
		}

		if ( isset( $error_data['request_log_url'] ) ) {
			$request_log_url    = (string) $error_data['request_log_url'];
			$localized_message .= ' <a href="' . esc_url( $request_log_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $request_log_url ) . '</a>';
		}

		return new WC_Stripe_Exception( print_r( $exception_response, true ), $localized_message );
	}

	/**
	 * Builds an exception from a Stripe renewal error response.
	 *
	 * @param object $response Stripe error response.
	 * @return WC_Stripe_Exception
	 */
	private function get_subscription_payment_exception_from_response( $response ): WC_Stripe_Exception {
		$response_data = is_object( $response ) ? get_object_vars( $response ) : [];
		$error         = $response_data['error'] ?? null;

		if ( ! is_object( $error ) ) {
			return new WC_Stripe_Exception( print_r( $response, true ), __( 'There was a problem processing the Stripe response.', 'woocommerce-gateway-stripe' ) );
		}

		return $this->get_subscription_payment_exception_from_error( $error, $response );
	}

	/**
	 * Processes a renewal payment and its retries.
	 *
	 * Retries share one lock, which is renewed before response handling.
	 *
	 * @param float        $amount         The amount to charge.
	 * @param WC_Order     $renewal_order  The renewal order.
	 * @param bool         $retry          Should we retry the process?
	 * @param object|false $previous_error Previous error object.
	 * @param int          $lock_expiry    Current lock expiry, updated on renewal.
	 * @param string       $acquired_lock  Current owner token, updated on renewal.
	 * @param bool         $should_release_payment_lock Whether cleanup may release the lock.
	 * @param int          $retry_interval Current retry delay and idempotency sequence.
	 * @return void
	 */
	private function process_subscription_payment_attempt( $amount, $renewal_order, $retry = true, $previous_error = false, int &$lock_expiry = 0, string &$acquired_lock = '', bool &$should_release_payment_lock = true, int $retry_interval = 1 ) {
		$radar_reason = false;
		$response     = null;

		try {
			$order_id = $renewal_order->get_id();

			if ( ! $this->can_continue_subscription_payment_attempt( $renewal_order, $lock_expiry, $acquired_lock, $previous_error ) ) {
				return;
			}

			// Check for an existing intent, which is associated with the order.
			if ( $this->has_authentication_already_failed( $renewal_order ) ) {
				return;
			}

			if ( ! $this->can_continue_subscription_payment_attempt( $renewal_order, $lock_expiry, $acquired_lock, $previous_error ) ) {
				return;
			}

			// Get source from order
			$prepared_source = $this->prepare_order_source( $renewal_order );

			if ( ! $this->can_continue_subscription_payment_attempt( $renewal_order, $lock_expiry, $acquired_lock, $previous_error ) ) {
				return;
			}

			$source_object = $prepared_source->source_object;

			if ( ! $prepared_source->customer ) {
				throw new WC_Stripe_Exception(
					'Failed to process renewal for order ' . $renewal_order->get_id() . '. Stripe customer id is missing in the order',
					__( 'Customer not found', 'woocommerce-gateway-stripe' )
				);
			}

			WC_Stripe_Logger::debug(
				"Begin processing subscription payment for order {$order_id} for the amount of {$amount}",
				[
					'order_id' => $order_id,
					'amount'   => $amount,
				]
			);

			/*
			 * If we're doing a retry and source is chargeable, we need to pass
			 * a different idempotency key and retry for success.
			 */
			$should_change_idempotency_key = is_object( $source_object )
				&& empty( $source_object->error )
				&& $this->should_change_subscription_renewal_idempotency_key( $source_object, $previous_error, $retry_interval );

			/**
			 * Filters whether retrying a renewal should fall back to the customer's default source.
			 *
			 * @param bool $use_default_source Whether to use the customer's default source.
			 */
			if ( ( $this->is_no_such_source_error( $previous_error ) || $this->is_no_linked_source_error( $previous_error ) ) && apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
				// Passing empty source will charge customer default.
				$prepared_source->source = '';
			}

			if ( ! $this->can_continue_subscription_payment_attempt( $renewal_order, $lock_expiry, $acquired_lock, $previous_error ) ) {
				return;
			}

			// Start only when the lock covers the full Stripe request chain.
			if ( time() + $this->get_subscription_payment_request_timeout_budget() > $lock_expiry ) {
				// Report the previous Stripe error without starting another request.
				if ( is_object( $previous_error ) ) {
					$payment_exception = $this->get_subscription_payment_exception_from_error( $previous_error );
					if ( '' !== $payment_exception->getLocalizedMessage() ) {
						$renewal_order->add_order_note( $payment_exception->getLocalizedMessage() );
					}
					throw $payment_exception;
				}

				$localized_message = __( 'Stripe: this renewal payment was not attempted because too little time remained on its payment lock.', 'woocommerce-gateway-stripe' );
				WC_Stripe_Logger::error(
					"Stripe: not attempting subscription renewal order {$order_id} because its payment lock cannot cover the complete Stripe request chain."
				);
				$renewal_order->add_order_note( $localized_message );
				throw new WC_Stripe_Exception(
					"Stripe: subscription renewal order {$order_id} was not attempted because its payment lock could not cover the complete Stripe request chain.",
					$localized_message
				);
			}

			$idempotency_key_filter = function ( $idempotency_key, $request ) use ( $retry_interval ) {
				return $this->change_subscription_renewal_idempotency_key( $idempotency_key, $request, $retry_interval );
			};
			if ( $should_change_idempotency_key ) {
				add_filter( 'wc_stripe_idempotency_key', $idempotency_key_filter, 10, 2 );
			}

			try {
				$payment_attempt = $this->attempt_subscription_renewal_payment( $amount, $renewal_order, $prepared_source );
			} finally {
				if ( $should_change_idempotency_key ) {
					remove_filter( 'wc_stripe_idempotency_key', $idempotency_key_filter, 10 );
				}
			}

			$response                   = $payment_attempt['response'];
			$is_authentication_required = $payment_attempt['is_authentication_required'];
			$cached_latest_charge       = $payment_attempt['latest_charge'];

			// It's only a failed payment if it's an error and it's not of the type 'authentication_required'.
			// If it's 'authentication_required', then we should email the user and ask them to authenticate.
			if ( ! empty( $response->error ) && ! $is_authentication_required ) {
				if ( ! $this->can_apply_subscription_payment_failure( $renewal_order, $acquired_lock ) ) {
					throw $this->get_subscription_payment_exception_from_response( $response );
				}

				// Compute once here so the catch block can reuse the result without a second API call.
				$radar_reason = $this->is_charge_blocked_by_radar( $response );

				if ( ! $this->can_apply_subscription_payment_failure( $renewal_order, $acquired_lock ) ) {
					throw $this->get_subscription_payment_exception_from_response( $response );
				}

				// We want to retry — unless Stripe Radar blocked the charge, in which case retrying
				// would just create another blocked charge and inflate the block rate.
				if ( $this->is_retryable_error( $response->error ) && false === $radar_reason ) {
					// Re-check the lock after backoff.
					$lock_expired = time() > $lock_expiry;

					if ( $retry && ! $lock_expired ) {
						// Retry under the same lock and stop after the final attempt.
						if ( 5 <= $retry_interval ) {
							$this->process_subscription_payment_attempt( $amount, $renewal_order, false, $response->error, $lock_expiry, $acquired_lock, $should_release_payment_lock, $retry_interval );
							return;
						}

						sleep( $retry_interval );

						++$retry_interval;

						$lock_expired = time() > $lock_expiry;
						if ( ! $lock_expired ) {
							$this->process_subscription_payment_attempt( $amount, $renewal_order, true, $response->error, $lock_expiry, $acquired_lock, $should_release_payment_lock, $retry_interval );
							return;
						}
					}

					if ( ! $this->can_apply_subscription_payment_failure( $renewal_order, $acquired_lock ) ) {
						throw $this->get_subscription_payment_exception_from_response( $response );
					}

					if ( $lock_expired ) {
						WC_Stripe_Logger::error( "Stripe: abandoning renewal payment retries for order {$order_id} because the payment lock has expired." );
					}

					$localized_message = sprintf(
						/* translators: 1) error message from Stripe; 2) request log URL */
						__( 'Sorry, we are unable to process the payment at this time. Reason: %1$s %2$s', 'woocommerce-gateway-stripe' ),
						$response->error->message,
						isset( $response->error->request_log_url ) ? '<a href="' . esc_url( $response->error->request_log_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $response->error->request_log_url ) . '</a>' : ''
					);
					$renewal_order->add_order_note( $localized_message );
					throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
				}

				$payment_exception = $this->get_subscription_payment_exception_from_response( $response );
				$renewal_order->add_order_note( $payment_exception->getLocalizedMessage() );

				throw $payment_exception;
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::error(
				'Error processing subscription renewal payment: ' . $e->getMessage(),
				[
					'order_id'          => $renewal_order->get_id(),
					'amount'            => $amount,
					'error_message'     => $e->getMessage(),
					'localized_message' => $e->getLocalizedMessage(),
				]
			);

			/**
			 * Fires after Stripe payment processing fails for an order.
			 *
			 * @param WC_Stripe_Exception $e     The exception raised during payment processing.
			 * @param WC_Order            $order The order that failed payment processing.
			 */
			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );

			// Always fire the error hook; guard order and Radar updates below.
			if ( ! $this->can_apply_subscription_payment_failure( $renewal_order, $acquired_lock ) ) {
				return;
			}

			$renewal_order->update_status( OrderStatus::FAILED );

			// Guard the Radar side effects, which include a hook that runs listener code.
			try {
				// If the payment was blocked by Stripe Radar, cancel any scheduled
				// retry attempt. Without this, WC Subscriptions schedules a retry
				// that would create another charge for Radar to block, inflating
				// the block rate.
				if ( false !== $radar_reason ) {
					$radar_cause = '';
					switch ( $radar_reason ) {
						case 'rule':
							$radar_cause = __( 'Stripe Radar blocked payment for the saved payment method due to a custom Radar rule.', 'woocommerce-gateway-stripe' );
							break;
						case 'low_probability_of_authorization':
							$radar_cause = __( 'Stripe blocked payment for the saved payment method due to low probability of authorization.', 'woocommerce-gateway-stripe' );
							break;
						case 'highest_risk_level':
							$radar_cause = __( 'Stripe Radar blocked payment for the saved payment method as high risk.', 'woocommerce-gateway-stripe' );
							break;
						default:
							$radar_cause = sprintf(
								/* translators: %s is the Stripe Radar reason code returned by the API. */
								__( 'Stripe Radar blocked payment for the saved payment method (reason: %s).', 'woocommerce-gateway-stripe' ),
								$radar_reason
							);
							break;
					}
					$retry_cancelled_suffix = __( 'The automatic retry has been cancelled to prevent further blocked payment attempts.', 'woocommerce-gateway-stripe' );

					try {
						$subscriptions = function_exists( 'wcs_get_subscriptions_for_renewal_order' )
							? wcs_get_subscriptions_for_renewal_order( $renewal_order )
							: [];

						$retry_cancelled = false;
						if ( class_exists( 'WCS_Retry_Manager' ) && method_exists( 'WCS_Retry_Manager', 'is_retry_enabled' ) && WCS_Retry_Manager::is_retry_enabled() && method_exists( 'WCS_Retry_Manager', 'store' ) ) {
							$retry_store = WCS_Retry_Manager::store();
							$last_retry  = method_exists( $retry_store, 'get_last_retry_for_order' ) ? $retry_store->get_last_retry_for_order( $renewal_order->get_id() ) : null;
							if ( $last_retry && 'pending' === $last_retry->get_status() ) {
								$last_retry->update_status( 'cancelled' );
								$retry_cancelled = true;
							}
							foreach ( $subscriptions as $subscription ) {
								if ( $subscription->get_date( 'payment_retry' ) > 0 ) {
									$subscription->delete_date( 'payment_retry' );
									$retry_cancelled = true;
								}
							}
						}

						$radar_note = $retry_cancelled
							? $radar_cause . ' ' . $retry_cancelled_suffix
							: $radar_cause;

						foreach ( $subscriptions as $subscription ) {
							$subscription->add_order_note( $radar_note );
						}
						$renewal_order->add_order_note( $radar_note );
					} catch ( Exception $radar_e ) {
						WC_Stripe_Logger::error(
							'Failed to cancel scheduled retry after Stripe Radar blocked subscription renewal: ' . $radar_e->getMessage(),
							[ 'order_id' => $renewal_order->get_id() ]
						);
					}

					/**
					 * Fires when a subscription renewal payment is blocked by Stripe Radar.
					 *
					 * @param WC_Order    $renewal_order The renewal order blocked by Radar.
					 * @param object|null $response      The Stripe API error response.
					 * @param string      $radar_reason  The Radar block reason (e.g. 'rule', 'highest_risk_level').
					 */
					do_action( 'wc_stripe_subscription_renewal_blocked_by_radar', $renewal_order, $response, $radar_reason );
				}
			} catch ( Exception $exception ) {
				WC_Stripe_Logger::error(
					'Error while handling a Stripe Radar-blocked subscription renewal: ' . $exception->getMessage(),
					[ 'order_id' => $renewal_order->get_id() ]
				);
			}

			return;
		}

		try {
			// Keep the lock after response-processing failures to prevent a duplicate charge.
			$should_release_payment_lock = false;

			// Renew the owner token before hooks or order updates.
			$renewed_lock = WC_Stripe_Order_Helper::get_instance()->renew_order_payment_lock_if_owned( $renewal_order, $acquired_lock );
			if ( ! is_string( $renewed_lock ) || ! $this->is_valid_subscription_payment_lock( $renewed_lock ) ) {
				throw new WC_Stripe_Exception(
					"Stripe: subscription renewal order {$order_id} response was not processed because its payment lock could not be renewed.",
					__( 'The Stripe renewal response could not be processed because its payment lock could not be renewed.', 'woocommerce-gateway-stripe' )
				);
			}

			$acquired_lock = $renewed_lock;
			$lock_expiry   = $this->get_subscription_payment_lock_expiry( $renewed_lock );

			// Either the charge was successfully captured, or it requires further authentication.
			if ( $is_authentication_required ) {
				$charge = is_object( $cached_latest_charge )
					? $cached_latest_charge
					: $this->get_latest_charge_from_intent( $response->error->payment_intent );
				$charge = $this->resolve_subscription_renewal_charge_object( $charge );

				if ( ! is_object( $charge ) || empty( $charge->id ) ) {
					throw new WC_Stripe_Exception(
						'The authentication-required renewal response did not contain an identifiable Stripe charge.',
						__( 'The Stripe charge awaiting authentication could not be identified.', 'woocommerce-gateway-stripe' )
					);
				}
				$id = (string) $charge->id;

				/**
				 * Fires when a Stripe payment requires customer authentication.
				 *
				 * @param WC_Order          $order    The order that requires authentication.
				 * @param object|array|null $response Stripe API response.
				 */
				do_action( 'wc_gateway_stripe_process_payment_authentication_required', $renewal_order, $response );

				$error_message = __( 'This transaction requires authentication.', 'woocommerce-gateway-stripe' );
				$renewal_order->add_order_note( $error_message );

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

				/**
				 * Fires when a subscription charge attempt is delayed by Stripe.
				 *
				 * @param object|array $response      Stripe API response.
				 * @param WC_Order     $renewal_order Renewal order associated with the charge attempt.
				 */
				do_action( 'wc_gateway_stripe_process_payment_subscription_charge_attempt_delayed', $response, $renewal_order );
			} else {
				// The charge was successfully captured
				/** This action is documented in includes/class-wc-stripe-webhook-handler.php. */
				do_action_deprecated(
					'wc_gateway_stripe_process_payment',
					[ $response, $renewal_order ],
					'9.7.0',
					'wc_gateway_stripe_process_payment_charge',
					'The wc_gateway_stripe_process_payment action is deprecated. Use wc_gateway_stripe_process_payment_charge instead.'
				);

				// Reuse the cached Charge; fall back for SEPA and overrides.
				$latest_charge = is_object( $cached_latest_charge )
					? $cached_latest_charge
					: $this->get_latest_charge_from_intent( $response );
				$latest_charge = $this->resolve_subscription_renewal_charge_object( $latest_charge );

				$charge_response = ( ! empty( $latest_charge ) ) ? $latest_charge : $response;

				if ( is_array( $charge_response ) ) {
					$charge_response = $this->convert_subscription_renewal_response_to_object( $charge_response );
				}

				$this->process_response( $charge_response, $renewal_order );
			}

			$should_release_payment_lock = true;
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::error(
				'Error processing subscription renewal payment: ' . $e->getMessage(),
				[
					'order_id'          => $renewal_order->get_id(),
					'amount'            => $amount,
					'error_message'     => $e->getMessage(),
					'localized_message' => $e->getLocalizedMessage(),
				]
			);

			/**
			 * This action is documented in includes/compat/trait-wc-stripe-subscriptions.php.
			 */
			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );
		}
	}

	/**
	 * Makes one Stripe payment attempt.
	 *
	 * The caller holds the lock through response processing.
	 *
	 * @param float    $amount         The amount to charge.
	 * @param WC_Order $renewal_order  The renewal order.
	 * @param object   $prepared_source The prepared renewal order source.
	 * @return array{response: stdClass, is_authentication_required: bool, latest_charge: object|null}
	 * @throws WC_Stripe_Exception When the Stripe API request fails.
	 */
	private function attempt_subscription_renewal_payment( $amount, WC_Order $renewal_order, $prepared_source ): array {
		// If the payment gateway is SEPA, use the charges API.
		// TODO: Remove when SEPA is migrated to payment intents.
		if ( 'stripe_sepa' === $this->id ) {
			$request            = $this->generate_payment_request( $renewal_order, $prepared_source );
			$request['capture'] = 'true';
			$request['amount']  = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
			$response           = WC_Stripe_API::request( $request );

			if ( is_array( $response ) ) {
				$response = $this->convert_subscription_renewal_response_to_object( $response );
			}

			return [
				'response'                   => $response,
				'is_authentication_required' => false,
				'latest_charge'              => null,
			];
		}

		$latest_charge = null;
		WC_Stripe_Subscriptions_Helper::begin_renewal_charge_enrichment( $renewal_order );
		try {
			/**
			 * Decoded Stripe API response.
			 *
			 * @var stdClass $response
			 */
			$response = $this->create_and_confirm_intent_for_off_session( $renewal_order, $prepared_source, $amount );
			$intent   = ! empty( $response->error->payment_intent ) && is_object( $response->error->payment_intent )
				? $response->error->payment_intent
				: $response;

			$latest_charge = WC_Stripe_Subscriptions_Helper::get_cached_renewal_charge( $renewal_order, $intent );
		} finally {
			WC_Stripe_Subscriptions_Helper::end_renewal_charge_enrichment( $renewal_order );
		}

		return [
			'response'                   => $response,
			'is_authentication_required' => $this->is_authentication_required_for_payment( $response ),
			'latest_charge'              => $latest_charge,
		];
	}

	/**
	 * Returns a Charge object, resolving a bare ID when needed.
	 *
	 * @param mixed $charge Expanded Charge, bare Charge ID, or an empty value.
	 * @return object|null
	 * @throws WC_Stripe_Exception When a bare charge ID cannot be resolved safely.
	 */
	private function resolve_subscription_renewal_charge_object( $charge ) {
		if ( is_string( $charge ) && '' !== $charge ) {
			$main_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
			if ( ! is_object( $main_gateway ) || ! is_callable( [ $main_gateway, 'get_charge_object' ] ) ) {
				throw new WC_Stripe_Exception(
					'The main Stripe gateway was unavailable while resolving a subscription renewal charge.',
					__( 'The complete Stripe charge details could not be retrieved while processing this renewal.', 'woocommerce-gateway-stripe' )
				);
			}
			$charge = $main_gateway->get_charge_object( $charge );
		}

		return is_object( $charge ) ? $charge : null;
	}

	/**
	 * Converts a nested response array to an object.
	 *
	 * @param array $response Stripe API response.
	 * @return stdClass
	 * @throws WC_Stripe_Exception When the response cannot be converted to an object.
	 */
	private function convert_subscription_renewal_response_to_object( array $response ): stdClass {
		$encoded_response = wp_json_encode( $response );

		if ( ! is_string( $encoded_response ) ) {
			throw new WC_Stripe_Exception( print_r( $response, true ), __( 'There was a problem processing the Stripe response.', 'woocommerce-gateway-stripe' ) );
		}

		$decoded_response = json_decode( $encoded_response, false );

		if ( ! $decoded_response instanceof stdClass ) {
			throw new WC_Stripe_Exception( print_r( $response, true ), __( 'There was a problem processing the Stripe response.', 'woocommerce-gateway-stripe' ) );
		}

		return $decoded_response;
	}

	/**
	 * Updates other subscription sources.
	 *
	 * @since 5.6.0
	 *
	 * @param WC_Order $order              The order object.
	 * @param stdClass $source             The source object.
	 * @param string   $payment_gateway_id The payment method ID. eg 'stripe'.
	 * @return void
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

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		foreach ( $subscriptions as $subscription ) {
			if ( $source->customer ) {
				$order_helper->update_stripe_customer_id( $subscription, $source->customer );
			}

			if ( ! empty( $source->payment_method ) ) {
				$order_helper->update_stripe_source_id( $subscription, $source->payment_method );
			} else {
				$order_helper->update_stripe_source_id( $subscription, $source->source );
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
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		if ( ! $resubscribe_order instanceof WC_Order ) {
			return;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->delete_stripe_source_id( $resubscribe_order );

		$order_helper->delete_stripe_customer_id( $resubscribe_order );
		// For BW compat will remove in future.
		$order_helper->delete_stripe_card_id( $resubscribe_order );
		// Delete payment intent ID.
		$order_helper->delete_stripe_intent_id( $resubscribe_order );
		$this->delete_renewal_meta( $resubscribe_order );
		$resubscribe_order->save();
	}

	/**
	 * Don't transfer Stripe fee/ID meta to renewal orders.
	 *
	 * @param WC_Order|null $renewal_order The renewal order.
	 * @return WC_Order|null The renewal order.
	 */
	public function delete_renewal_meta( $renewal_order ) {
		if ( ! $renewal_order instanceof WC_Order ) {
			return $renewal_order;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->delete_stripe_fee( $renewal_order );
		$order_helper->delete_stripe_net( $renewal_order );

		// Delete payment intent ID.
		$order_helper->delete_stripe_intent_id( $renewal_order );

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
		if ( ! $subscription instanceof WC_Subscription ) {
			return;
		}

		if ( ! $renewal_order instanceof WC_Order ) {
			return;
		}

		$order_helper       = WC_Stripe_Order_Helper::get_instance();
		$stripe_customer_id = $order_helper->get_stripe_customer_id( $renewal_order );
		$stripe_source_id   = $order_helper->get_stripe_source_id( $renewal_order );

		$order_helper->update_stripe_customer_id( $subscription, $stripe_customer_id ? $stripe_customer_id : '' );
		$order_helper->update_stripe_source_id( $subscription, $stripe_source_id ? $stripe_source_id : '' );
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
		$order_helper    = WC_Stripe_Order_Helper::get_instance();
		$source_id       = $order_helper->get_stripe_source_id( $subscription );

		// For BW compat will remove in future.
		if ( empty( $source_id ) ) {
			$source_id = $order_helper->get_stripe_card_id( $subscription );

			// Take this opportunity to update the key name.
			$order_helper->update_stripe_source_id( $subscription, $source_id ? $source_id : '' );
			$order_helper->delete_stripe_card_id( $subscription );
			$subscription->save();
		}

		$payment_meta[ $this->id ] = [
			'post_meta' => [
				'_stripe_customer_id' => [
					'value' => $order_helper->get_stripe_customer_id( $subscription ),
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
	 * @return void
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
	 * @param bool     $is_setup_intent  Whether this is a setup intent.
	 * @return array
	 */
	public function add_subscription_information_to_intent( $request, $order, $prepared_source, $is_setup_intent = false ) {
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
			$mandate = WC_Stripe_Order_Helper::get_instance()->get_stripe_mandate_id( $order );
			if ( isset( $request['confirm'] ) && filter_var( $request['confirm'], FILTER_VALIDATE_BOOLEAN ) && ! empty( $mandate ) ) {
				$request['mandate'] = $mandate;

				// We already have a mandate -- unset mandate_data and setup_future_usage, if set.
				unset( $request['mandate_data'] );
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

					// We already have a mandate -- unset mandate_data and setup_future_usage, if set.
					unset( $request['mandate_data'] );
					unset( $request['setup_future_usage'] );

					return $request;
				}
			}
		}

		// Add mandate options to request to create new mandate if mandate id does not already exist in a previous renewal or parent order.
		// Note: This is for backwards compatibility if `_stripe_mandate_id` is not set.
		$mandate_options = $this->create_mandate_options_for_order( $order, $subscriptions_for_renewal_order, $is_setup_intent );
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
	 * @param string   $payment_method The payment method ID.
	 * @return string The mandate id or empty string if no valid mandate id is found.
	 */
	private function get_mandate_for_subscription( $order, $payment_method ) {
		$renewal_order_ids = $order->get_related_orders( 'ids' );
		foreach ( $renewal_order_ids as $renewal_order_id ) {
			$renewal_order = wc_get_order( $renewal_order_id );
			if ( ! $renewal_order instanceof WC_Order ) {
				continue;
			}

			$order_helper                 = WC_Stripe_Order_Helper::get_instance();
			$mandate                      = $order_helper->get_stripe_mandate_id( $renewal_order );
			$renewal_order_payment_method = $order_helper->get_stripe_source_id( $renewal_order );

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
	 * @param bool $is_setup_intent Whether the intent is a setup intent.
	 * @return array the mandate_options for the subscription order.
	 */
	private function create_mandate_options_for_order( $order, $subscriptions, $is_setup_intent = false ) {
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

			// If the total amount is 0 and it's a setup intent, then calculate the amount from the subscription subtotal.
			// The total could be 0 if the subscription has a free trial or a coupon is used.
			if ( 0 === $sub_amount && $is_setup_intent ) {
				foreach ( $subscriptions as $sub ) {
					$sub_amount += WC_Stripe_Helper::get_stripe_amount( $sub->get_subtotal(), $currency );
				}
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

		// Currency is required for mandate options when creating a setup intent for card payment methods.
		if ( $is_setup_intent ) {
			$mandate_options['currency'] = $currency;
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

		$order_helper     = WC_Stripe_Order_Helper::get_instance();
		$stripe_source_id = $order_helper->get_stripe_source_id( $subscription );

		// For BW compat will remove in future.
		if ( empty( $stripe_source_id ) ) {
			$stripe_source_id = $order_helper->get_stripe_card_id( $subscription );

			// Take this opportunity to update the key name.
			$order_helper->update_stripe_source_id( $subscription, $stripe_source_id ? $stripe_source_id : '' );
			$subscription->save();
		}

		$stripe_customer_id = $order_helper->get_stripe_customer_id( $subscription );

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
			$order_helper       = WC_Stripe_Order_Helper::get_instance();
			$parent_order       = wc_get_order( $subscription->get_parent_id() );
			$stripe_customer_id = $order_helper->get_stripe_customer_id( $parent_order );
			$stripe_source_id   = $order_helper->get_stripe_source_id( $parent_order );

			// For BW compat will remove in future.
			if ( empty( $stripe_source_id ) ) {
				$stripe_source_id = $order_helper->get_stripe_card_id( $parent_order );

				// Take this opportunity to update the key name.
				$order_helper->update_stripe_source_id( $parent_order, $stripe_source_id );
				$parent_order->save();
			}
		}

		try {
			$saved_payment_method = WC_Stripe_Subscriptions_Helper::get_subscription_payment_method_details( $stripe_customer_id, $stripe_source_id );

			if ( null !== $saved_payment_method ) {
				$wallet_type_override = $this->get_saved_token_wallet_type( $customer_user, $stripe_source_id );
				return $this->get_payment_method_to_display_for_payment_method( $saved_payment_method, $wallet_type_override );
			}
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::error( 'Error rendering subscription payment method for subscription: ' . $subscription->get_id(), [ 'error_message' => $e->getMessage() ] );
		}

		return __( 'N/A', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns the pinned `wallet_type` of the saved token backing a Stripe PaymentMethod.
	 *
	 * Fingerprint dedup can repoint a saved card token at a newer (wallet-flavored)
	 * PaymentMethod while leaving its `wallet_type` pinned. The subscription
	 * row must reflect that pinned branding rather than the live PaymentMethod's wallet,
	 * so the row and the saved-token list agree.
	 *
	 * @param int    $customer_user     WP user ID owning the subscription.
	 * @param string $stripe_source_id  Stripe PaymentMethod ID stored on the subscription.
	 * @return string|null The token's pinned wallet type (possibly an empty string), or null
	 *                     when no matching saved token exists (e.g. guest / parent-order lookups).
	 */
	protected function get_saved_token_wallet_type( $customer_user, $stripe_source_id ): ?string {
		if ( ! $customer_user || empty( $stripe_source_id ) ) {
			return null;
		}

		$tokens = WC_Payment_Tokens::get_customer_tokens( $customer_user, WC_Stripe_UPE_Payment_Gateway::ID );
		foreach ( $tokens as $token ) {
			if ( $token->get_token() === $stripe_source_id && $token instanceof WC_Stripe_Payment_Token_CC ) {
				return $token->get_wallet_type();
			}
		}

		return null;
	}

	/**
	 * Helper function to get the descriptive text for a payment method or source.
	 *
	 * @param object      $payment_method      The payment method or source object.
	 * @param string|null $wallet_type_override Authoritative wallet type from the matching saved
	 *                                          token, or null when no token matched. When provided
	 *                                          (including an empty string), it wins over the live
	 *                                          PaymentMethod's `card->wallet->type` so the row matches
	 *                                          the saved-token list's pinned branding.
	 * @return string The descriptive text for the payment method or source.
	 */
	protected function get_payment_method_to_display_for_payment_method( object $payment_method, ?string $wallet_type_override = null ): string {
		// Legacy handling for Stripe Card objects. ref: https://docs.stripe.com/api/cards/object
		if ( isset( $payment_method->object ) && WC_Stripe_Payment_Methods::CARD === $payment_method->object ) {
			return sprintf(
				/* translators: 1) card brand 2) last 4 digits */
				__( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ),
				( isset( $payment_method->brand ) ? wc_get_credit_card_type_label( $payment_method->brand ) : __( 'N/A', 'woocommerce-gateway-stripe' ) ),
				$payment_method->last4
			);
		}

		switch ( $payment_method->type ) {
			case WC_Stripe_Payment_Methods::CARD:
				$card_brand = isset( $payment_method->card->brand ) ? wc_get_credit_card_type_label( $payment_method->card->brand ) : __( 'N/A', 'woocommerce-gateway-stripe' );
				$card_last4 = $payment_method->card->last4;

				// Surface the wallet brand (Apple Pay / Google Pay) used; `link` and manual cards stay bare.
				// When a saved token matched the PaymentMethod, its pinned `wallet_type` is
				// authoritative so this row matches the saved-token list; otherwise
				// fall back to the live PaymentMethod's wallet type.
				$wallet_type  = $wallet_type_override ?? ( isset( $payment_method->card->wallet->type ) ? $payment_method->card->wallet->type : '' );
				$wallet_label = WC_Stripe_Payment_Methods::EXPRESS_METHODS_LABELS[ $wallet_type ] ?? '';
				if ( '' !== $wallet_label ) {
					return sprintf(
						/* translators: 1) wallet brand e.g. "Google Pay"; 2) card brand e.g. Visa; 3) last 4 digits */
						__( 'Via %1$s (%2$s) ending in %3$s', 'woocommerce-gateway-stripe' ),
						$wallet_label,
						$card_brand,
						$card_last4
					);
				}

				return sprintf(
					/* translators: 1) card brand 2) last 4 digits */
					__( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-stripe' ),
					$card_brand,
					$card_last4
				);
			case WC_Stripe_Payment_Methods::SEPA_DEBIT:
				/* translators: 1) last 4 digits of SEPA Direct Debit */
				return sprintf( __( 'Via SEPA Direct Debit ending in %1$s', 'woocommerce-gateway-stripe' ), $payment_method->sepa_debit->last4 );
			case WC_Stripe_Payment_Methods::CASHAPP_PAY:
				/* translators: 1) Cash App Cashtag */
				return sprintf( __( 'Via Cash App Pay (%1$s)', 'woocommerce-gateway-stripe' ), $payment_method->cashapp->cashtag );
			case WC_Stripe_Payment_Methods::LINK:
				/* translators: 1) email address associated with the Stripe Link payment method */
				return sprintf( __( 'Via Stripe Link (%1$s)', 'woocommerce-gateway-stripe' ), $payment_method->link->email );
			case WC_Stripe_Payment_Methods::ACH:
				return sprintf(
					/* translators: 1) account type (checking, savings), 2) last 4 digits of account. */
					__( 'Via %1$s Account ending in %2$s', 'woocommerce-gateway-stripe' ),
					ucfirst( $payment_method->us_bank_account->account_type ),
					$payment_method->us_bank_account->last4
				);
			case WC_Stripe_Payment_Methods::BECS_DEBIT:
				return sprintf(
					/* translators: last 4 digits of account. */
					__( 'BECS Direct Debit ending in %s', 'woocommerce-gateway-stripe' ),
					$payment_method->au_becs_debit->last4
				);
			case WC_Stripe_Payment_Methods::ACSS_DEBIT:
				return sprintf(
					/* translators: 1) bank name, 2) last 4 digits of account. */
					__( 'Via %1$s ending in %2$s', 'woocommerce-gateway-stripe' ),
					$payment_method->acss_debit->bank_name,
					$payment_method->acss_debit->last4
				);
			case WC_Stripe_Payment_Methods::BACS_DEBIT:
				/* translators: 1) the Bacs Direct Debit payment method's last 4 numbers */
				return sprintf( __( 'Via Bacs Direct Debit ending in (%1$s)', 'woocommerce-gateway-stripe' ), $payment_method->bacs_debit->last4 );
			case WC_Stripe_Payment_Methods::AMAZON_PAY:
				/* translators: 1) the Amazon Pay payment method's email */
				return sprintf( __( 'Via Amazon Pay (%1$s)', 'woocommerce-gateway-stripe' ), $payment_method->billing_details->email ?? '' );
		}

		return __( 'N/A', 'woocommerce-gateway-stripe' );
	}

	/**
	 * If this is the "Pass the SCA challenge" flow, remove a variable that is checked by WC Subscriptions
	 * so WC Subscriptions doesn't redirect to the checkout
	 *
	 * @return void
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
	 *
	 * @return void
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
	 * @throws WC_Stripe_Exception When the existing intent has no identifiable Charge.
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

		// Resolve the Charge before firing the authentication hook.
		$charge = $this->resolve_subscription_renewal_charge_object( $this->get_latest_charge_from_intent( $existing_intent ) );
		if ( ! is_object( $charge ) || empty( $charge->id ) ) {
			throw new WC_Stripe_Exception(
				'The existing authentication-required renewal intent did not contain an identifiable Stripe charge.',
				__( 'The Stripe charge awaiting authentication could not be identified.', 'woocommerce-gateway-stripe' )
			);
		}
		$charge_id = (string) $charge->id;

		// Make sure all emails are instantiated.
		WC_Emails::instance();

		/**
		 * This action is documented in includes/compat/trait-wc-stripe-subscriptions.php.
		 */
		do_action( 'wc_gateway_stripe_process_payment_authentication_required', $renewal_order );

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
	 * @return void
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
	 * @return void
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
	 * @return void
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
	 * Disable edits for subscriptions that have a mandate and a card payment method from India.
	 *
	 * @param bool     $editable The current editability of the subscription.
	 * @param WC_Order $order    The order object.
	 * @return bool Returns true if the subscription can be edited, false otherwise.
	 */
	public function disable_subscription_edit_for_india( $editable, $order ) {
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() || ! $this->is_subscription( $order ) ) {
			return $editable;
		}

		// Only disable editing if we're on the subscription edit page.
		if ( ! WC_Stripe_Subscriptions_Helper::is_subscription_edit_page() ) {
			return $editable;
		}

		$parent_order = wc_get_order( $order->get_parent_id() );
		if ( ! $parent_order ) {
			return $editable;
		}

		// Bail if subscription's parent order does not have a mandate ID
		if ( empty( WC_Stripe_Order_Helper::get_instance()->get_stripe_mandate_id( $parent_order ) ) ) {
			return $editable;
		}

		$source_id = WC_Stripe_Order_Helper::get_instance()->get_stripe_source_id( $order );
		if ( empty( $source_id ) ) {
			return $editable;
		}

		// Retrieve the payment method object from Stripe.
		$cache_key      = 'payment_method_for_source_' . $source_id;
		$payment_method = WC_Stripe_Database_Cache::get( $cache_key );
		if ( ! $payment_method ) {
			$payment_method = $this->stripe_request( 'payment_methods/' . $source_id );
			WC_Stripe_Database_Cache::set( $cache_key, $payment_method, HOUR_IN_SECONDS );
		}

		// If the payment method is a card and the card's country is India, disable subscription editing.
		if ( $payment_method && WC_Stripe_Payment_Methods::CARD === $payment_method->type && WC_Stripe_Country_Code::INDIA === ( $payment_method->card->country ?? '' ) ) {
			return false;
		}

		// Fallback to the default behavior.
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
		if ( ! $this->is_changing_payment_method_for_subscription() || $new_payment_method !== $this->id ) {
			return $update_payment_method;
		}

		return false;
	}
}
