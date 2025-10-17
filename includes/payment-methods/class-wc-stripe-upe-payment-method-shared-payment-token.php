<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_UPE_Payment_Method_Shared_Payment_Token
 *
 * This class represents the Stripe UPE payment method for the agentic Shared Payment Token flow.
 */
class WC_Stripe_UPE_Payment_Method_Shared_Payment_Token extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::SHARED_PAYMENT_TOKEN;

	/**
	 * The Agentic Commerce provider identifier for Stripe.
	 * While it overlaps with the payment gateway ID, they refer to different concepts.
	 *
	 * @var string
	 */
	public const STRIPE_AGENTIC_PROVIDER_ID = 'stripe';

	/**
	 * Constructor for the Shared Payment Token payment method (which renders all methods).
	 */
	public function __construct() {
		parent::__construct();
		$main_settings     = WC_Stripe_Helper::get_stripe_settings();
		$is_stripe_enabled = ! empty( $main_settings['enabled'] ) && 'yes' === $main_settings['enabled'];

		$shared_payment_token_enabled = $is_stripe_enabled &&
			$this->shared_payment_token_enabled &&
			[] !== $this->get_enabled_agentic_commerce_payment_methods();

		$this->enabled     = $shared_payment_token_enabled ? 'yes' : 'no';
		$this->id          = WC_Gateway_Stripe::ID; // Force the ID to be the same as the main payment gateway.
		$this->stripe_id   = self::STRIPE_ID;
		$this->title       = __( 'Stripe Agentic Commerce', 'woocommerce-gateway-stripe' );
		$this->is_reusable = false;
		$this->supports[]  = 'subscriptions';
		$this->supports[]  = 'tokenization';
		$this->supports[]  = 'agentic_commerce';
	}

	/**
	 * Get the supported agentic commerce payment methods.
	 *
	 * @return string[] Array of supported payment methods for agentic commerce.
	 */
	public static function get_supported_agentic_commerce_payment_methods(): array {
		return [ WC_Stripe_Payment_Methods::CARD ];
	}

	/**
	 * Get the enabled agentic commerce payment methods.
	 *
	 * @return string[] Array of enabled payment methods for agentic commerce.
	 */
	public function get_enabled_agentic_commerce_payment_methods(): array {
		return array_intersect(
			self::get_supported_agentic_commerce_payment_methods(),
			$this->get_upe_enabled_payment_method_ids()
		);
	}

	/**
	 * Get the agentic commerce payment provider identifier.
	 *
	 * @return string Payment provider identifier.
	 */
	public function get_agentic_commerce_provider() {
		return self::STRIPE_AGENTIC_PROVIDER_ID;
	}

	/**
	 * Get the supported payment methods for agentic commerce.
	 *
	 * @return array Array of supported payment methods.
	 */
	public function get_agentic_commerce_payment_methods() {
		return self::get_supported_agentic_commerce_payment_methods();
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
		if ( $payment_details && ! empty( $payment_details->card->wallet->type ) ) {
			return $this->get_card_wallet_type_title( $payment_details->card->wallet->type );
		}

		if ( $payment_details && ! empty( $payment_details->type ) ) { // Setting title for the order details page / thank you page.
			$payment_method = WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( $payment_details->type );

			// Avoid potential recursion by checking instance type. This fixes the title on pay for order confirmation page.
			return $payment_method instanceof self ? parent::get_title() : $payment_method->get_title();
		}

		// Block checkout and pay for order (checkout) page.
		if ( ( has_block( 'woocommerce/checkout' ) || ! empty( $_GET['pay_for_order'] ) ) && ! is_wc_endpoint_url( 'order-received' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'Stripe Agentic Commerce';
		}

		return parent::get_title();
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @inheritDoc
	 */
	public function is_available() {
		if ( ! $this->shared_payment_token_enabled ) {
			return false;
		}

		if ( [] === $this->get_enabled_agentic_commerce_payment_methods() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 *
	 * @inheritDoc
	 */
	public function get_retrievable_type() {
		return WC_Stripe_Payment_Methods::CARD;
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
	 * @return string
	 */
	public function get_testing_instructions( $show_optimized_checkout_instruction = false ) {
		if ( false !== $show_optimized_checkout_instruction ) {
			_deprecated_argument(
				__FUNCTION__,
				'9.9.0'
			);
		}

		$instructions          = '';
		$base_instruction_html = '<div id="wc-stripe-payment-method-instructions-%s" class="wc-stripe-payment-method-instruction" style="display: none;">%s</div>';

		foreach ( $this->get_enabled_agentic_commerce_payment_methods() as $payment_method_id ) {
			$payment_method = WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( $payment_method_id );
			if ( ! $payment_method ) {
				continue;
			}

			$payment_method_instructions = $payment_method->get_testing_instructions();
			if ( $payment_method_instructions ) {
				$instructions .= sprintf( $base_instruction_html, $payment_method::STRIPE_ID, $payment_method_instructions );
			}
		}

		return $instructions;
	}
}
