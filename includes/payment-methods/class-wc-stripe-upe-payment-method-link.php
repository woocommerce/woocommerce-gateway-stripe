<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Link extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::LINK;

	/**
	 * Constructor for Link payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id   = self::STRIPE_ID;
		$this->title       = __( 'Link', 'woocommerce-gateway-stripe' );
		$this->is_reusable = true;
		$this->label       = __( 'Stripe Link', 'woocommerce-gateway-stripe' );
		$this->description = __(
			'Link is a payment method that allows customers to save payment information  and use the payment details
			for further payments.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
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

		$upe_enabled_method_ids = WC_Stripe_Helper::get_settings( null, 'upe_checkout_experience_accepted_payments' );

		return is_array( $upe_enabled_method_ids ) && in_array( self::STRIPE_ID, $upe_enabled_method_ids, true );
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
	 * Determines if the Stripe Account country this UPE method supports.
	 *
	 * @return bool
	 */
	public function is_available_for_account_country() {
		// If merchant is outside US, Link payment method should not be available.
		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country     = $cached_account_data['country'] ?? null;

		// List of available countries for each PM:
		// https://docs.stripe.com/payments/payment-methods/integration-options#country-currency-support
		$country_availablity = [ 'AE', 'AT', 'AU', 'BE', 'BG', 'CA', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GI', 'GR', 'HK', 'HR', 'HU', 'IE', 'IT', 'JP', 'LI', 'LT', 'LU', 'LV', 'MT', 'MX', 'MY', 'NL', 'NO', 'NZ', 'PL', 'PT', 'RO', 'SE', 'SG', 'SI', 'SK', 'US' ];

		return in_array( $account_country, $country_availablity, true );
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * Link isn't like a traditional UPE payment method as it is not shown as a standard payment method at checkout.
	 * Customers use the Stripe Link button and the existing credit card fields to enter their payment details. The payment is then treated as a card.
	 *
	 * We return false here so the payment method isn't considered available by WooCommerce and rendered as a payment method at checkout.
	 *
	 * @return bool
	 */
	public function is_available() {
		return false;
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 * By default all the UPE payment methods require automatic capture, except for "card" and "link".
	 *
	 * @return bool
	 */
	public function requires_automatic_capture() {
		return false;
	}

	/**
	 * Filters the gateway title to reflect Link as the payment method.
	 */
	public function filter_gateway_title( $title, $id ) {
		global $theorder;

		// If $theorder is empty (i.e. non-HPOS), fallback to using the global post object.
		if ( empty( $theorder ) && ! empty( $GLOBALS['post']->ID ) ) {
			$theorder = wc_get_order( $GLOBALS['post']->ID );
		}

		if ( ! is_object( $theorder ) ) {
			return $title;
		}

		$method_title = $theorder->get_payment_method_title();

		if ( 'stripe' === $id && ! empty( $method_title ) ) {
			if ( 'Link' === $method_title ) {
				return $method_title;
			}
		}

		return $title;
	}
}
