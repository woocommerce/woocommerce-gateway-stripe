<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_Sessions Class.
 */
class WC_Stripe_Checkout_Sessions {
	/**
	 * Create a new checkout session.
	 *
	 * @param WC_Order $order The WC Order for which we're handling a setup intent.
	 * @param array $payment_information The payment information to be used for the setup intent.
	 * @return stdClass
	 */
	public static function create( WC_Order $order, array $payment_information ): stdClass {
		$params = [
			'ui_mode'              => 'custom',
			'customer'             => $payment_information['customer'],
			'payment_method_types' => $payment_information['payment_method_types'],
			'line_items' => [
				[
					'price_data' => [
						'currency' => strtolower( $order->get_currency() ),
						'product_data' => [
							'name' => sprintf( /* translators: %s: Order number. */
								__( 'Order #%s', 'woocommerce-gateway-stripe' ),
								$order->get_order_number()
							),
						],
						'unit_amount' => WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $order->get_currency() ),
					],
					'quantity' => 1,
				]
			],
			'payment_intent_data' => self::build_payment_intent_data( $order, $payment_information ),
			'mode' => 'payment',
			'return_url' => $payment_information['return_url'],
		];

		return WC_Stripe_API::request_with_level3_data( $params, 'checkout/sessions', $payment_information['level3'], $order );
	}

	private static function build_payment_intent_data( WC_Order $order, array $payment_information ): array {
		// Throws a WC_Stripe_Exception if required information is missing.
		$required_params = [
			'amount',
			'level3',
			'metadata',
			'order',
			'save_payment_method_to_store',
			'shipping',
		];

		$non_empty_params = [];

		// The payment method is not required if we're using the confirmation token flow.
		if ( empty( $payment_information['confirmation_token'] ) ) {
			$required_params[] = 'capture_method';
		}

		$instance_params = [ 'order' => 'WC_Order' ];

		self::validate_payment_intent_required_params( $required_params, $non_empty_params, $instance_params, $payment_information );

		$order                 = $payment_information['order'];
		$selected_payment_type = $payment_information['selected_payment_type'];

		$request = self::build_base_payment_intent_request_params( $payment_information );

		$request = array_merge(
			$request,
			[
				/* translators: 1) blog name 2) order number */
				'description'          => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
				'metadata'             => $payment_information['metadata'],
			]
		);

		if ( isset( $payment_information['statement_descriptor_suffix'] ) ) {
			$request['statement_descriptor_suffix'] = $payment_information['statement_descriptor_suffix'];
		}

		if ( ! empty( $payment_information['payment_method_options'] ) ) {
			$request['payment_method_options'] = $payment_information['payment_method_options'];
		}

		// Run the necessary filter to make sure mandate information is added when it's required.
		$request = apply_filters(
			'wc_stripe_generate_create_intent_request',
			$request,
			$order,
			null // $prepared_source parameter is not necessary for adding mandate information.
		);

		// Only update the payment_type if we have a reference to the payment type the customer selected.
		if ( '' !== $selected_payment_type ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $selected_payment_type );
		}

		return $request;
	}

	/**
	 * Determines if the request contains all the required params for creating or updating a payment intent.
	 *
	 * @param array $required_params The required parameters for the payment intent.
	 * @param array $non_empty_params The parameters that must not contain an empty value.
	 * @param array $instance_params The parameters that must be of a specific type.
	 * @param array $payment_information The payment information to be validated.
	 * @return void
	 * @throws WC_Stripe_Exception
	 */
	private static function validate_payment_intent_required_params( $required_params, $non_empty_params, $instance_params, $payment_information ) {
		$missing_params = [];
		foreach ( $required_params as $param ) {
			// Check if they're set. Some can be null.
			if ( ! array_key_exists( $param, $payment_information ) ) {
				$missing_params[] = $param;
			}
		}

		// Some params must not contain an empty value.
		foreach ( $non_empty_params as $param ) {
			if ( empty( $payment_information[ $param ] ) ) {
				$missing_params[] = $param;
			}
		}

		$shopper_error_message = __( 'There was a problem processing the payment.', 'woocommerce-gateway-stripe' );

		// Bail out if we're missing required information.
		if ( ! empty( $missing_params ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$calling_method = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? '';
			throw new WC_Stripe_Exception(
				sprintf(
					'The information for creating and confirming the intent is missing the following data: %s. Payment information received: %s. Calling method: %s',
					implode( ', ', $missing_params ),
					wp_json_encode( $payment_information ),
					$calling_method
				),
				$shopper_error_message
			);
		}

		// Check if the instance params are of the correct type.
		foreach ( $instance_params as $param => $type ) {
			if ( ! is_a( $payment_information[ $param ], $type ) ) {
				throw new WC_Stripe_Exception(
					sprintf(
						'The provided value for the "%s" parameter is not a %s.',
						$param,
						$type
					),
					__( 'Please reach out to us if the problem persists.', 'woocommerce-gateway-stripe' )
				);
			}
		}
	}

	/**
	 * Builds the base request parameters for creating/updating and confirming a payment intent.
	 *
	 * @param array $payment_information The payment information needed for creating/updating and confirming the intent.
	 *
	 * @return array The request parameters for creating/updating and confirming a payment intent.
	 */
	private static function build_base_payment_intent_request_params( $payment_information ) {
		$selected_payment_type = $payment_information['selected_payment_type'];

		$payment_method_types = $payment_information['payment_method_types'];

		$request = [
			'shipping' => $payment_information['shipping'],
		];

		$is_using_confirmation_token = ! empty( $payment_information['confirmation_token'] );
		if ( $is_using_confirmation_token ) {
			$request['confirmation_token'] = $payment_information['confirmation_token'];
		} else {
			$request['capture_method'] = $payment_information['capture_method'];

		}

		// Does not set the return URL if the request needs redirection.
		if ( self::request_needs_redirection( $payment_method_types ) ) {
			$request['return_url'] = $payment_information['return_url'];
		}

		// If the customer is saving the payment method to the store or has a subscription, we should set the setup_future_usage to off_session.
		// Only exceptions are when using a confirmation token or manual renewal is required.
		// For confirmations tokens, the setup_future_usage is set within the payment method.
		$payment_method                 = WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( $selected_payment_type );
		$has_auto_renewing_subscription = ! empty( $payment_information['has_subscription'] ) && ! self::is_manual_renewal_required( $payment_method->is_reusable() );
		if ( ! $is_using_confirmation_token && ( $payment_information['save_payment_method_to_store'] || $has_auto_renewing_subscription ) ) {
			$request['setup_future_usage'] = 'off_session';
		}

		// BLIK requires additional information in the payment method options.
		if ( WC_Stripe_Payment_Methods::BLIK === $selected_payment_type && isset( $payment_information['payment_method_options'] ) ) {
			$request['payment_method_options'] = $payment_information['payment_method_options'];
		}

		return $request;
	}

	/**
	 * Determines if mandate data is required for deferred intent UPE payment.
	 *
	 * A mandate must be provided before a deferred intent UPE payment can be processed.
	 * This applies to SEPA, Bancontact, iDeal, Sofort, Cash App, Link payment methods,
	 * ACH, ACSS Debit and BACS.
	 * https://docs.stripe.com/payments/finalize-payments-on-the-server
	 *
	 * @param string $selected_payment_type         The name of the selected UPE payment type.
	 * @param bool   $is_using_saved_payment_method Option. True if the customer is using a saved payment method, false otherwise.
	 *
	 * @return bool True if a mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed, false otherwise.
	 */
	private static function is_mandate_data_required( $selected_payment_type, $is_using_saved_payment_method = false ) {
		$payment_methods_with_mandates = [
			WC_Stripe_Payment_Methods::ACH,
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			WC_Stripe_Payment_Methods::BACS_DEBIT,
			WC_Stripe_Payment_Methods::BECS_DEBIT,
			WC_Stripe_Payment_Methods::SEPA_DEBIT,
			WC_Stripe_Payment_Methods::BANCONTACT,
			WC_Stripe_Payment_Methods::IDEAL,
			WC_Stripe_Payment_Methods::SOFORT,
			WC_Stripe_Payment_Methods::LINK,
		];
		if ( in_array( $selected_payment_type, $payment_methods_with_mandates, true ) ) {
			return true;
		}

		$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		return WC_Stripe_Payment_Methods::CARD === $selected_payment_type && in_array( WC_Stripe_Payment_Methods::LINK, $gateway->get_upe_enabled_payment_method_ids(), true );
	}

	/**
	 * Determines whether the request needs to redirect customer off-site to authorize payment.
	 * This is needed for the non-card UPE payment method (i.e. iDeal, giropay, etc.)
	 *
	 * @param array $payment_methods The list of payment methods used for the processing the payment.
	 *
	 * @return boolean True if the array consist of only one payment method and it isn't card, Boleto, Oxxo or Multibanco. False otherwise.
	 */
	private static function request_needs_redirection( $payment_methods ) {
		return 1 === count( $payment_methods ) && ! in_array( $payment_methods[0], [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::BOLETO, WC_Stripe_Payment_Methods::OXXO, WC_Stripe_Payment_Methods::MULTIBANCO, WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
	}

	/**
	 * Check if manual renewal is required for the payment method.
	 *
	 * @return bool
	 */
	private static function is_manual_renewal_required( $is_payment_method_reusable ) {
		return ( ! $is_payment_method_reusable && WC_Stripe_Subscriptions_Helper::is_manual_renewal_enabled() )
			|| WC_Stripe_Subscriptions_Helper::is_manual_renewal_required();
	}

	/**
	 * Determines whether the intent needs to be confirmed later.
	 *
	 * Some payment methods such as CashApp, Boleto, Oxxo and Multibanco require the payment to be confirmed later when
	 * displaying the voucher or QR code to the customer on the checkout or pay for order page.
	 *
	 * @param array $payment_methods The list of payment methods used for the processing the payment.
	 *
	 * @return boolean
	 */
	private static function is_delayed_confirmation_required( $payment_methods ) {
		return ! empty( array_intersect( $payment_methods, [ WC_Stripe_Payment_Methods::BOLETO, WC_Stripe_Payment_Methods::OXXO, WC_Stripe_Payment_Methods::MULTIBANCO, WC_Stripe_Payment_Methods::CASHAPP_PAY ] ) );
	}
}
