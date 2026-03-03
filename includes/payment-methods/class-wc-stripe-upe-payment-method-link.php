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
		// Note that the title and label are not translated, as "Link" should not be translated.
		$this->title       = 'Link';
		$this->is_reusable = true;
		$this->label       = 'Stripe Link';
		$this->description = sprintf(
			/* translators: %s: "Link" - a product name that should not be translated. */
			__(
				'%s is a payment method that allows customers to save payment information and use the payment details for further payments.',
				'woocommerce-gateway-stripe'
			),
			'Link'
		);

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
	}

	/**
	 * Return if Stripe Link is enabled
	 *
	 * @param WC_Stripe_UPE_Payment_Gateway $gateway The gateway instance.
	 * @return bool
	 */
	public static function is_link_enabled( WC_Stripe_UPE_Payment_Gateway $gateway ) {
		$upe_enabled_method_ids = $gateway->get_upe_enabled_payment_method_ids();

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
		$country_availablity = [ WC_Stripe_Country_Code::AE, WC_Stripe_Country_Code::AT, WC_Stripe_Country_Code::AU, WC_Stripe_Country_Code::BE, WC_Stripe_Country_Code::BG, WC_Stripe_Country_Code::CA, WC_Stripe_Country_Code::CH, WC_Stripe_Country_Code::CY, WC_Stripe_Country_Code::CZ, WC_Stripe_Country_Code::DE, WC_Stripe_Country_Code::DK, WC_Stripe_Country_Code::EE, WC_Stripe_Country_Code::ES, WC_Stripe_Country_Code::FI, WC_Stripe_Country_Code::FR, WC_Stripe_Country_Code::GB, WC_Stripe_Country_Code::GI, WC_Stripe_Country_Code::GR, WC_Stripe_Country_Code::HK, WC_Stripe_Country_Code::HR, WC_Stripe_Country_Code::HU, WC_Stripe_Country_Code::IE, WC_Stripe_Country_Code::IT, WC_Stripe_Country_Code::JP, WC_Stripe_Country_Code::LI, WC_Stripe_Country_Code::LT, WC_Stripe_Country_Code::LU, WC_Stripe_Country_Code::LV, WC_Stripe_Country_Code::MT, WC_Stripe_Country_Code::MX, WC_Stripe_Country_Code::MY, WC_Stripe_Country_Code::NL, WC_Stripe_Country_Code::NO, WC_Stripe_Country_Code::NZ, WC_Stripe_Country_Code::PL, WC_Stripe_Country_Code::PT, WC_Stripe_Country_Code::RO, WC_Stripe_Country_Code::SE, WC_Stripe_Country_Code::SG, WC_Stripe_Country_Code::SI, WC_Stripe_Country_Code::SK, WC_Stripe_Country_Code::US ];

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
	 *
	 * @param string $title The gateway title.
	 * @param string $id The gateway ID.
	 * @return string
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
			if ( WC_Stripe_Payment_Methods::LINK_LABEL === $method_title ) {
				return $method_title;
			}
		}

		return $title;
	}
}
