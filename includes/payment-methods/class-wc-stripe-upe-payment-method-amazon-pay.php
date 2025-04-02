<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amazon Pay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Amazon_Pay extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	const STRIPE_ID = WC_Stripe_Payment_Methods::AMAZON_PAY;

	/**
	 * Constructor for Amazon Pay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Amazon Pay', 'woocommerce-gateway-stripe' );
		$this->supported_currencies = [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ];
		$this->is_reusable          = true;
		$this->label                = __( 'Amazon Pay', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Amazon Pay is a payment method that allows customers to pay with their Amazon account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports[]           = 'tokenization';

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Create new WC payment token and add to user.
	 *
	 * @param int $user_id        WP_User ID
	 * @param object $payment_method Stripe payment method object
	 *
	 * @return WC_Payment_Token_Amazon_Pay
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_Amazon_Pay();
		$token->set_email( $payment_method->billing_details->email ?? '' );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Return if Amazon Pay is enabled.
	 *
	 * @param WC_Gateway_Stripe $gateway The gateway instance.
	 *
	 * @return bool
	 */
	public static function is_amazon_pay_enabled( WC_Gateway_Stripe $gateway ) {
		// Amazon Pay is disabled if feature flag is disabled.
		if ( ! WC_Stripe_Feature_Flags::is_amazon_pay_available() ) {
			return false;
		}

		// Amazon Pay is disabled if UPE is disabled.
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return false;
		}

		$upe_enabled_method_ids = $gateway->get_upe_enabled_payment_method_ids();

		return is_array( $upe_enabled_method_ids ) && in_array( self::STRIPE_ID, $upe_enabled_method_ids, true );
	}

	/**
	 * Returns whether the payment method is available.
	 *
	 * Amazon Pay is rendered as an express checkout method only, for now.
	 * We return false here so that it isn't considered available by WooCommerce
	 * and rendered as a standard payment method at checkout.
	 *
	 * @return bool
	 */
	public function is_available() {
		return false;
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @return bool
	 */
	public function requires_automatic_capture() {
		// Amazon Pay supports manual capture.
		return false;
	}
}
