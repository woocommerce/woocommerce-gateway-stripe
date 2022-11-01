<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Link extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'link';

	/**
	 * Constructor for Link payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Pay with Link', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'USD' ];
		$this->label                = __( 'Stripe Link', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Link is a payment method that allows customers to save payment information  and use the payment details
			for further payments.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Return if Stripe Link is enabled
	 *
	 * @return bool
	 */
	public static function is_link_enabled() {

		// Assume Link is disabled if UPE is disabled.
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return false;
		}

		return in_array(
			self::STRIPE_ID,
			woocommerce_gateway_stripe()->get_main_stripe_gateway()->get_upe_enabled_payment_method_ids(),
			true
		);
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
	 * @return WC_Payment_Token_Link
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_Link();
		$token->set_email( $payment_method->link->email );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method->id );
		$token->set_payment_method_type( $this->get_id() );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		//if merchant is outside US, Link payment method should not be available
		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country     = $cached_account_data['country'] ?? null;

		return 'US' === $account_country && parent::is_available();
	}
}
