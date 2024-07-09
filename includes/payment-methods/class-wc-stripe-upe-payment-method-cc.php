<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_UPE_Payment_Method_CC
 */

/**
 * Credit card Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_CC extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'card';

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe::class;

	/**
	 * Constructor for card payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id   = self::STRIPE_ID;
		$this->title       = __( 'Credit / Debit Card', 'woocommerce-gateway-stripe' );
		$this->is_reusable = true;
		$this->label       = __( 'Credit / Debit Card', 'woocommerce-gateway-stripe' );
		$this->supports[]  = 'subscriptions';
		$this->supports[]  = 'tokenization';
		$this->description = __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		if ( ! $payment_details ) {
			return parent::get_title();
		}

		$details       = $payment_details[ $this->stripe_id ];
		$funding_types = [
			'credit'  => __( 'credit', 'woocommerce-gateway-stripe' ),
			'debit'   => __( 'debit', 'woocommerce-gateway-stripe' ),
			'prepaid' => __( 'prepaid', 'woocommerce-gateway-stripe' ),
			'unknown' => __( 'unknown', 'woocommerce-gateway-stripe' ),
		];

		return sprintf(
			// Translators: %1$s card brand, %2$s card funding (prepaid, credit, etc.).
			__( '%1$s %2$s card', 'woocommerce-gateway-stripe' ),
			ucfirst( $details->network ),
			$funding_types[ $details->funding ]
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
	 * Create and return WC payment token for user.
	 *
	 * This will be used from the WC_Stripe_Payment_Tokens service
	 * as opposed to WC_Stripe_UPE_Payment_Gateway.
	 *
	 * @param string $user_id        WP_User ID
	 * @param object $payment_method Stripe payment method object
	 *
	 * @return WC_Payment_Token_CC
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_CC();
		$token->set_expiry_month( $payment_method->card->exp_month );
		$token->set_expiry_year( $payment_method->card->exp_year );
		$token->set_card_type( strtolower( $payment_method->card->display_brand ?? $payment_method->card->networks->preferred ?? $payment_method->card->brand ) );
		$token->set_last4( $payment_method->card->last4 );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Returns boolean dependent on whether capability
	 * for site account is enabled for payment method.
	 *
	 * @return bool
	 */
	public function is_capability_active() {
		return true;
	}

	/**
	 * The Credit Card method allows automatic capture.
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
	public function get_testing_instructions() {
		return sprintf(
			/* translators: 1) HTML strong open tag 2) HTML strong closing tag 3) HTML anchor open tag 2) HTML anchor closing tag */
			esc_html__( '%1$sTest mode:%2$s use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed %3$shere%4$s.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>',
			'<a href="https://stripe.com/docs/testing" target="_blank">',
			'</a>'
		);
	}
}
