<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_Sessions_Ajax_Handler class.
 */
class WC_Stripe_Checkout_Sessions_Ajax_Handler {
	/**
	 * Checkout session metadata value identifying an Adaptive Pricing checkout.
	 *
	 * @var string
	 */
	public const ADAPTIVE_PRICING_CHECKOUT_TYPE = 'adaptive_pricing_checkout';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wc_ajax_wc_stripe_create_checkout_session', [ $this, 'create_checkout_session' ] );
		add_action( 'wc_ajax_wc_stripe_update_checkout_session', [ $this, 'update_checkout_session' ] );
	}

	/**
	 * Create a Stripe Checkout Session and return the client secret.
	 *
	 * @return void
	 */
	public function create_checkout_session(): void {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_checkout_session_nonce', 'security', false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this request. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
			}

			$cart_context = $this->build_cart_context();

			$request = [
				'ui_mode'                       => 'custom',
				'line_items'                    => $this->build_line_items( $cart_context ),
				'excluded_payment_method_types' => WC_Stripe::get_instance()->get_main_stripe_gateway()->get_excluded_payment_method_types(),
				'payment_intent_data'           => $this->build_payment_intent_data(),
				'mode'                          => 'payment',
				'adaptive_pricing'              => [
					'enabled' => 'true',
				],
				'metadata'                      => [
					'checkout_type' => self::ADAPTIVE_PRICING_CHECKOUT_TYPE,
				],
			];

			if ( 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ) ) {
				$request['phone_number_collection'] = [ 'enabled' => 'true' ];
			}

			if ( is_user_logged_in() && WC()->customer instanceof WC_Customer ) {
				try {
					$stripe_customer = new WC_Stripe_Customer( WC()->customer->get_id() );
					$stripe_customer->maybe_create_customer();
				} catch ( Exception $e ) {
					throw new Exception( __( 'Unable to create or retrieve Stripe customer.', 'woocommerce-gateway-stripe' ) );
				}

				$request['customer']                     = $stripe_customer->get_id();
				$request['saved_payment_method_options'] = [
					'payment_method_save' => 'enabled',
				];
			}

			$checkout_session = WC_Stripe_API::request( $request, 'checkout/sessions' );

			if ( ! empty( $checkout_session->error ) ) {
				$message = empty( $checkout_session->error->message ) ? __( 'Checkout Sessions API returned an error', 'woocommerce-gateway-stripe' ) : $checkout_session->error->message;
				throw new Exception( $message );
			}

			if ( empty( $checkout_session->client_secret ) || empty( $checkout_session->id ) ) {
				throw new Exception( __( 'Unable to create Stripe Checkout Session.', 'woocommerce-gateway-stripe' ) );
			}

			WC_Stripe_Checkout_Session_Context::store_for_cart( $checkout_session->id, $cart_context );

			wp_send_json_success(
				[
					'client_secret' => $checkout_session->client_secret,
					'session_id'    => $checkout_session->id,
				]
			);
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Create checkout session error.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Update a Stripe Checkout Session. Currently only used to update the line items.
	 *
	 * @since 10.6.0
	 * @return void
	 */
	public function update_checkout_session(): void {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_update_checkout_session_nonce', 'security', false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this request. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			$session_id = isset( $_POST['checkout_session_id'] )
				? wc_clean( wp_unslash( $_POST['checkout_session_id'] ) )
				: '';
			if ( ! is_string( $session_id ) || '' === $session_id ) {
				throw new Exception( __( 'Checkout session ID is required.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
			}

			WC_Stripe_Checkout_Session_Context::with_mutation_lock(
				$session_id,
				function () use ( $session_id ): void {
					WC_Stripe_Checkout_Session_Context::validate_update_request( $session_id );
					$cart_context = $this->build_cart_context();

					$request = [
						'line_items' => $this->build_line_items( $cart_context ),
					];

					$checkout_session = WC_Stripe_API::request( $request, "checkout/sessions/$session_id" );

					if ( ! empty( $checkout_session->error ) ) {
						$message = empty( $checkout_session->error->message ) ? __( 'Checkout Sessions update API returned an error', 'woocommerce-gateway-stripe' ) : $checkout_session->error->message;
						throw new Exception( $message );
					}

					WC_Stripe_Checkout_Session_Context::store_for_cart( $session_id, $cart_context );
				}
			);

			wp_send_json_success( [ 'result' => 'success' ] );

		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Update checkout session error.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Build the current cart context used to validate Checkout Session updates.
	 *
	 * @return array
	 */
	private function build_cart_context(): array {
		$this->calculate_cart_totals();

		$currency = get_woocommerce_currency();

		return [
			'amount'   => WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), $currency ),
			'currency' => strtolower( $currency ),
		];
	}

	/**
	 * Recalculate cart totals using the same checkout conditional third-party tax integrations expect.
	 *
	 * @return void
	 */
	private function calculate_cart_totals(): void {
		$force_checkout_context = static function (): bool {
			return true;
		};

		add_filter( 'woocommerce_is_checkout', $force_checkout_context );

		try {
			WC()->cart->calculate_totals();
		} finally {
			remove_filter( 'woocommerce_is_checkout', $force_checkout_context );
		}
	}

	/**
	 * Build the line items array for the Stripe Checkout Session request based on the WooCommerce cart contents.
	 *
	 * @param array|null $cart_context Current cart context.
	 * @return array
	 */
	private function build_line_items( ?array $cart_context = null ): array {
		$cart_context = $cart_context ?? $this->build_cart_context();

		$line_items = [
			[
				'price_data' => [
					'currency'     => $cart_context['currency'],
					'product_data' => [
						'name' => __( 'Cart total', 'woocommerce-gateway-stripe' ),
					],
					'unit_amount'  => $cart_context['amount'],
				],
				// As we are using one aggregate line item: the payable total lives in unit_amount, not in quantity × unit price.
				'quantity'   => 1,
			],
		];

		return $line_items;
	}

	/**
	 * Build the payment intent data array, including metadata information.
	 *
	 * @return array
	 */
	private function build_payment_intent_data(): array {
		$data     = [];
		$metadata = [
			'site_url'      => esc_url_raw( get_site_url() ),
			'payment_type'  => 'single',
			'checkout_type' => self::ADAPTIVE_PRICING_CHECKOUT_TYPE,
		];

		/** Documented in includes/abstracts/abstract-wc-stripe-payment-gateway.php */
		$data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, null, null );

		return $data;
	}
}
