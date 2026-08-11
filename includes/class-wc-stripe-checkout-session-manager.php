<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and synchronizes the Stripe Checkout Session owned by a WooCommerce session.
 */
class WC_Stripe_Checkout_Session_Manager {
	/**
	 * Checkout session metadata value identifying an Adaptive Pricing checkout.
	 *
	 * @var string
	 */
	public const ADAPTIVE_PRICING_CHECKOUT_TYPE = 'adaptive_pricing_checkout';

	/**
	 * WooCommerce session key containing the active Stripe Checkout Session.
	 *
	 * @var string
	 */
	private const WC_SESSION_KEY = 'wc_stripe_checkout_session';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Return the shared manager instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a new Stripe Checkout Session for the current WooCommerce cart.
	 *
	 * @return array Public session data.
	 * @throws Exception When the cart or Stripe response is invalid.
	 */
	public function create_session(): array {
		$this->validate_cart();

		$cart_context = $this->build_cart_context();
		$request      = [
			'ui_mode'                       => 'elements',
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
				// Creation happens before checkout fields are complete, so only the saved account identity can be used here.
				$stripe_customer = new WC_Stripe_Customer( WC()->customer->get_id() );
				$stripe_customer->maybe_create_customer( WC_Stripe_Customer::CUSTOMER_CONTEXT_CHECKOUT_SESSION );
			} catch ( Exception $e ) {
				throw new Exception( __( 'Unable to create or retrieve Stripe customer.', 'woocommerce-gateway-stripe' ) );
			}

			$request['customer']                     = $stripe_customer->get_id();
			$request['saved_payment_method_options'] = [
				'payment_method_save' => 'enabled',
			];

			// Stripe must collect and backfill an address when the saved customer was created without one.
			$has_billing_address = '' !== trim( (string) get_user_meta( WC()->customer->get_id(), 'billing_address_1', true ) );
			if ( ! $has_billing_address ) {
				$request['customer_update'] = [
					'address' => 'auto',
					'name'    => 'auto',
				];
			}
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

		// Note: we don't store the amount or currency here, as the Checkout Session context is
		// the source of truth for the amount and currency. That code controls the data sent
		// to Stripe and is used to validate amounts later.
		$record = [
			'session_id'    => (string) $checkout_session->id,
			'client_secret' => (string) $checkout_session->client_secret,
			'revision'      => 1,
			'status'        => 'success',
			'message'       => '',
		];
		$this->set_record( $record );

		return $this->get_public_data( $record );
	}

	/**
	 * Update an existing Stripe Checkout Session from the current cart.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return array Public session data.
	 * @throws Exception When the session cannot be updated.
	 */
	public function update_session( string $session_id ): array {
		$this->validate_cart();

		if ( '' === $session_id ) {
			throw new Exception( __( 'Checkout session ID is required.', 'woocommerce-gateway-stripe' ) );
		}

		WC_Stripe_Checkout_Session_Context::with_mutation_lock(
			$session_id,
			function () use ( $session_id ): void {
				WC_Stripe_Checkout_Session_Context::validate_update_request( $session_id );

				// Read the cart only once the lock is held and the session is known not to be
				// linked to an order, so a concurrent request cannot push a snapshot taken
				// against cart state that validation has already moved past.
				$cart_context = $this->build_cart_context();

				$checkout_session = WC_Stripe_API::request(
					[ 'line_items' => $this->build_line_items( $cart_context ) ],
					"checkout/sessions/$session_id"
				);

				if ( ! empty( $checkout_session->error ) ) {
					$message = empty( $checkout_session->error->message ) ? __( 'Checkout Sessions update API returned an error', 'woocommerce-gateway-stripe' ) : $checkout_session->error->message;
					throw new Exception( $message );
				}

				WC_Stripe_Checkout_Session_Context::store_for_cart( $session_id, $cart_context );

				// Make sure we read the record while we have the lock.
				$record = $this->get_record();

				if ( is_array( $record ) && (string) ( $record['session_id'] ?? '' ) === $session_id ) {
					$record['revision'] = (int) ( $record['revision'] ?? 0 ) + 1;
					$record['status']   = 'success';
					$record['message']  = '';
					$this->set_record( $record );
				}
			}
		);

		// Ensure we (re)fetch the current data.
		return $this->get_current_data();
	}

	/**
	 * Create or update the session only when the native WooCommerce cart has changed.
	 *
	 * @return array Public session data.
	 * @throws Exception When synchronization fails.
	 */
	public function synchronize(): array {
		$this->validate_cart();

		$record       = $this->get_record();
		$cart_context = $this->build_cart_context();

		if ( ! is_array( $record ) || empty( $record['session_id'] ) || empty( $record['client_secret'] ) ) {
			return $this->create_session();
		}

		$context = WC_Stripe_Checkout_Session_Context::get_context( (string) $record['session_id'] );
		if ( empty( $context ) || ! empty( $context['order_id'] ) ) {
			return $this->create_session();
		}

		// The context is the source of truth for amount and currency, so we need to compare
		// against the data that was pushed to Stripe.
		if (
			(int) ( $context['amount'] ?? 0 ) === $cart_context['amount']
			&& (string) ( $context['currency'] ?? '' ) === $cart_context['currency']
			&& 'success' === ( $record['status'] ?? '' )
		) {
			return $this->get_public_data( $record );
		}

		return $this->update_session( (string) $record['session_id'] );
	}

	/**
	 * Return the current session data without causing a Stripe API request.
	 *
	 * @return array
	 */
	public function get_current_data(): array {
		return $this->get_public_data( $this->get_record() );
	}

	/**
	 * Record a synchronization failure while retaining the last usable session identifiers.
	 *
	 * @param string $message Failure message.
	 * @return array
	 */
	public function mark_failed( string $message ): array {
		$record            = $this->get_record() ?? [];
		$record['status']  = 'error';
		$record['message'] = $message;
		$this->set_record( $record );

		return $this->get_public_data( $record );
	}

	/**
	 * Build the current cart amount and currency after WooCommerce has applied checkout integrations.
	 *
	 * @return array
	 */
	private function build_cart_context(): array {
		$currency = get_woocommerce_currency();

		return [
			'amount'   => WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), $currency ),
			'currency' => strtolower( $currency ),
		];
	}

	/**
	 * Build the aggregate line item expected by Stripe Custom Checkout.
	 *
	 * @param array $cart_context Current cart context.
	 * @return array
	 */
	private function build_line_items( array $cart_context ): array {
		return [
			[
				'price_data' => [
					'currency'     => $cart_context['currency'],
					'product_data' => [
						'name' => __( 'Cart total', 'woocommerce-gateway-stripe' ),
					],
					'unit_amount'  => $cart_context['amount'],
				],
				// The payable total is already aggregated into unit_amount, so quantity must remain one.
				'quantity'   => 1,
			],
		];
	}

	/**
	 * Build PaymentIntent metadata for a Checkout Session.
	 *
	 * @return array
	 */
	private function build_payment_intent_data(): array {
		$metadata = [
			'site_url'      => esc_url_raw( get_site_url() ),
			'payment_type'  => 'single',
			'checkout_type' => self::ADAPTIVE_PRICING_CHECKOUT_TYPE,
		];

		/** Documented in includes/abstracts/abstract-wc-stripe-payment-gateway.php */
		return [ 'metadata' => apply_filters( 'wc_stripe_payment_metadata', $metadata, null, null ) ];
	}

	/**
	 * Ensure checkout services needed by the manager exist in this request context.
	 *
	 * @return void
	 * @throws Exception When the cart or WooCommerce session is unavailable.
	 */
	private function validate_cart(): void {
		if ( ! WC()->session ) {
			throw new Exception( WC_Stripe_Checkout_Session_Context::get_unavailable_message() );
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			throw new Exception( __( 'Your cart is currently empty.', 'woocommerce-gateway-stripe' ) );
		}
	}

	/**
	 * Get the active record from the WooCommerce session.
	 *
	 * @return array|null
	 */
	private function get_record(): ?array {
		if ( ! WC()->session || ! is_callable( [ WC()->session, 'get' ] ) ) {
			return null;
		}

		$record = WC()->session->get( self::WC_SESSION_KEY );
		return is_array( $record ) ? $record : null;
	}

	/**
	 * Store the active record in the WooCommerce session.
	 *
	 * @param array $record Session record.
	 * @return void
	 */
	private function set_record( array $record ): void {
		if ( WC()->session && is_callable( [ WC()->session, 'set' ] ) ) {
			WC()->session->set( self::WC_SESSION_KEY, $record );
		}
	}

	/**
	 * Limit session data exposed to checkout clients.
	 *
	 * @param array|null $record Stored session record.
	 * @return array
	 */
	private function get_public_data( ?array $record ): array {
		return [
			'session_id'    => (string) ( $record['session_id'] ?? '' ),
			'client_secret' => (string) ( $record['client_secret'] ?? '' ),
			'revision'      => (int) ( $record['revision'] ?? 0 ),
			'status'        => (string) ( $record['status'] ?? 'uninitialized' ),
			'message'       => (string) ( $record['message'] ?? '' ),
		];
	}
}
