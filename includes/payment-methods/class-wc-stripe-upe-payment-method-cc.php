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

	const STRIPE_ID = WC_Stripe_Payment_Methods::CARD;

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
	 * @param stdClass|array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		$wallet_type = $payment_details->card->wallet->type ?? null;
		if ( $payment_details ) {
			if ( $wallet_type ) {
				return $this->get_card_wallet_type_title( $wallet_type );
			}

			// Setting title for the order details page / thank you page (classic checkout) when OC is enabled.
			if ( $this->oc_enabled ) {
				$payment_method = WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( $payment_details->type );
				return $payment_method->get_title();
			}
		}

		if ( $this->oc_enabled ) {
			if ( $payment_details ) { // Setting title for the order details page / thank you page.
				$payment_method = WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( $payment_details->type );
				return $payment_method->get_title();
			}

			// Classic checkout page
			return $this->oc_title;
		}

		return parent::get_title();
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
	 * @return WC_Stripe_Payment_Token_CC
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_expiry_month( $payment_method->card->exp_month );
		$token->set_expiry_year( $payment_method->card->exp_year );
		$token->set_card_type( strtolower( $payment_method->card->display_brand ?? $payment_method->card->networks->preferred ?? $payment_method->card->brand ) );
		$token->set_last4( $payment_method->card->last4 );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->set_fingerprint( $payment_method->card->fingerprint );
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
	 * @param bool $show_optimized_checkout_instruction Whether this is being called through the Optimized Checkout instructions method. Used to avoid an infinite loop call.
	 * @return string
	 */
	public function get_testing_instructions( $show_optimized_checkout_instruction = false ) {
		if ( $this->oc_enabled && ! $show_optimized_checkout_instruction ) {
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

	/**
	 * Returns the title for the card wallet type.
	 * This is used to display the title for Apple Pay and Google Pay.
	 *
	 * @param $express_payment_type string The type of express payment method.
	 *
	 * @return string The title for the card wallet type.
	 */
	private function get_card_wallet_type_title( $express_payment_type ) {
		$express_payment_titles = WC_Stripe_Payment_Methods::EXPRESS_METHODS_LABELS;
		$payment_method_title   = $express_payment_titles[ $express_payment_type ] ?? false;

		if ( ! $payment_method_title ) {
			return parent::get_title();
		}

		return $payment_method_title . WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();
	}
}
