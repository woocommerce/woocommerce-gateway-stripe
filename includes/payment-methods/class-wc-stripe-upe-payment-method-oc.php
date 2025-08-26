<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_UPE_Payment_Method_OC
 *
 * This class represents the Stripe UPE payment method for the Optimized Checkout (OC) flow.
 */
class WC_Stripe_UPE_Payment_Method_OC extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::OC;

	/**
	 * Constructor for the Optimized Checkout payment method (which renders all methods).
	 */
	public function __construct() {
		parent::__construct();
		$main_settings     = WC_Stripe_Helper::get_stripe_settings();
		$is_stripe_enabled = ! empty( $main_settings['enabled'] ) && 'yes' === $main_settings['enabled'];

		$this->enabled     = $is_stripe_enabled && $this->oc_enabled ? 'yes' : 'no';
		$this->id          = WC_Gateway_Stripe::ID; // Force the ID to be the same as the main payment gateway.
		$this->stripe_id   = self::STRIPE_ID;
		$this->title       = 'Stripe';
		$this->is_reusable = true;
		$this->supports[]  = 'subscriptions';
		$this->supports[]  = 'tokenization';
	}

	/**
	 * Returns payment method title
	 *
	 * @param stdClass|array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		// Wallet type
		$wallet_type = $payment_details->card->wallet->type ?? null;
		if ( $wallet_type ) {
			return $this->get_card_wallet_type_title( $wallet_type );
		}

		if ( $payment_details ) { // Setting title for the order details page / thank you page.
			$payment_method = WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( $payment_details->type );

			// Avoid potential recursion by checking instance type. This fixes the title on pay for order confirmation page.
			return $payment_method instanceof self ? parent::get_title() : $payment_method->get_title();
		}

		// Block checkout and pay for order (checkout) page.
		if ( ( has_block( 'woocommerce/checkout' ) || ! empty( $_GET['pay_for_order'] ) ) && ! is_wc_endpoint_url( 'order-received' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'Stripe';
		}

		return parent::get_title();
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @inheritDoc
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 *
	 * @inheritDoc
	 */
	public function get_retrievable_type() {
		return WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID;
	}

	/**
	 * Returns boolean dependent on whether capability
	 * for site account is enabled for payment method.
	 *
	 * @inheritDoc
	 */
	public function is_capability_active() {
		return true;
	}

	/**
	 * The Optimized Checkout method allows automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param bool $show_optimized_checkout_instruction Whether this is being called through the Optimized Checkout instructions method. Used to avoid an infinite loop call.
	 * @return string
	 */
	public function get_testing_instructions( $show_optimized_checkout_instruction = false ) {
		if ( ! $show_optimized_checkout_instruction ) {
			return WC_Stripe_UPE_Payment_Gateway::get_testing_instructions_for_optimized_checkout();
		}

		return sprintf(
			/* translators: 1) HTML strong open tag 2) HTML strong closing tag 3) HTML anchor open tag 2) HTML anchor closing tag */
			esc_html__( '%1$sTest mode:%2$s use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed %3$shere%4$s.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>',
			'<a href="https://docs.stripe.com/testing" target="_blank">',
			'</a>'
		);
	}
}
