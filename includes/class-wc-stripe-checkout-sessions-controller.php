<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_Sessions_Controller class.
 */
class WC_Stripe_Checkout_Sessions_Controller {
	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wc_ajax_wc_stripe_create_checkout_session', [ $this, 'create_checkout_session' ] );
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

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}

			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
			}

			$user_logged_in = is_user_logged_in() && WC()->customer instanceof WC_Customer;
			$request        = [
				'ui_mode'                       => 'custom',
				'line_items'                    => $this->build_line_items(),
				'excluded_payment_method_types' => WC_Stripe::get_instance()->get_main_stripe_gateway()->get_excluded_payment_method_types(),
				'payment_intent_data'           => $this->build_payment_intent_data( $user_logged_in ),
				'mode'                          => 'payment',
				'adaptive_pricing'              => [
					'enabled' => 'true',
				],
			];

			if ( $user_logged_in ) {
				try {
					$stripe_customer = new WC_Stripe_Customer( WC()->customer->get_id() );
					$stripe_customer->maybe_create_customer();
				} catch ( Exception $e ) {
					throw new Exception( __( 'Unable to create or retrieve Stripe customer.', 'woocommerce-gateway-stripe' ) );
				}

				$request['customer'] = $stripe_customer->get_id();
			}

			$checkout_session = WC_Stripe_API::request( $request, 'checkout/sessions' );

			if ( ! empty( $checkout_session->error ) ) {
				$message = empty( $checkout_session->error->message ) ? __( 'Checkout Sessions API returned an error', 'woocommerce-gateway-stripe' ) : $checkout_session->error->message;
				throw new Exception( $message );
			}

			if ( empty( $checkout_session->client_secret ) ) {
				throw new Exception( __( 'Unable to create Stripe Checkout Session.', 'woocommerce-gateway-stripe' ) );
			}

			wp_send_json_success( [ 'client_secret' => $checkout_session->client_secret ] );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Create checkout session error.', [ 'error_message' => $e->getMessage() ] );
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Build the line items array for the Stripe Checkout Session request based on the WooCommerce cart contents.
	 *
	 * @return array
	 */
	private function build_line_items(): array {
		$currency   = strtolower( get_woocommerce_currency() );
		$line_items = [];
		foreach ( WC_Stripe_Helper::build_line_items() as $raw_line_item ) {
			if ( 'total_discount' === ( $raw_line_item['key'] ?? '' ) ) {
				// TODO: Stripe Checkout handles discounts/coupons differently. Skip for now.
				continue;
			}

			$line_items[] = [
				'price_data' => [
					'currency' => $currency,
					'product_data' => [
						'name' => $raw_line_item['label'],
					],
					'unit_amount' => $raw_line_item['amount'],
				],
				'quantity' => 1, // @TODO: Handle quantity properly if needed (#4984).
			];
		}

		return $line_items;
	}

	/**
	 * Build the payment intent data array, including metadata and customer information if the user is logged in.
	 *
	 * @param bool $user_logged_in Whether the user is logged in and has a valid WC_Customer instance.
	 * @return array
	 */
	private function build_payment_intent_data( bool $user_logged_in ): array {
		$data     = [];
		$metadata = [
			'site_url'     => esc_url_raw( get_site_url() ),
			'payment_type' => 'single',
		];

		if ( $user_logged_in ) {
			$wc_customer = WC()->customer;
			$user_id     = $wc_customer->get_id();
			$email       = $wc_customer->get_email();
			$first_name  = get_user_meta( $user_id, 'first_name', true );
			$last_name   = get_user_meta( $user_id, 'last_name', true );
			$full_name   = trim( sanitize_text_field( $first_name ) . ' ' . sanitize_text_field( $last_name ) );

			$data = [
				'receipt_email' => $email,
				'shipping'      => [
					'name'    => $full_name,
					'address' => [
						'line1'       => $wc_customer->get_shipping_address_1(),
						'line2'       => $wc_customer->get_shipping_address_2(),
						'city'        => $wc_customer->get_shipping_city(),
						'country'     => $wc_customer->get_shipping_country(),
						'postal_code' => $wc_customer->get_shipping_postcode(),
						'state'       => $wc_customer->get_shipping_state(),
					],
				],
			];

			$metadata = array_merge(
				$metadata,
				[
					'customer_name'  => $full_name,
					'customer_email' => $email,
				]
			);
		}

		/**
		 * Filter the metadata sent with the Stripe Checkout Session payment intent.
		 *
		 * @since 4.0.0
		 *
		 * @param array            $metadata The metadata array to be sent with the payment intent.
		 * @param WC_Order|null    $order    The WC_Order object if available, otherwise null.
		 * @param WC_Customer|null $customer The WC_Customer object if available, otherwise null.
		 */
		$data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, null, null );

		return $data;
	}
}
