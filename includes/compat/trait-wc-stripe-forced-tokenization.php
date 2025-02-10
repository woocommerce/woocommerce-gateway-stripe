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

		add_action( 'wc_checkout_tokenization_' . $this->id . '_charge_order_token', [ $this, 'process_order_tokenization_payment' ], 10, 2 ); // @phpstan-ignore-line (id is defined in the classes that use this trait)

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
}
