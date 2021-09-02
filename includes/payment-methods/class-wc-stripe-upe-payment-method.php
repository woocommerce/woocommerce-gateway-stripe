<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 */


/**
 * Extendable abstract class for payment methods.
 */
abstract class WC_Stripe_UPE_Payment_Method {

	/**
	 * Stripe key name
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Display title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $is_reusable;

	/**
	 * Array of currencies supported by this UPE method
	 *
	 * @var array
	 */
	protected $supported_currencies;

	/**
	 * Wether this UPE method is enabled
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Create instance of payment method
	 */
	public function __construct() {
		$main_settings       = get_option( 'woocommerce_stripe_settings' );
		$enabled_upe_methods = $main_settings['upe_checkout_experience_accepted_payments'];
		$this->enabled       = in_array( static::STRIPE_ID, $enabled_upe_methods, true );
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->stripe_id;
	}

	/**
	 * Returns true if the UPE method is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		return $this->title;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout() {
		if ( $this->is_subscription_item_in_cart() ) {
			return $this->is_reusable();
		}
		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_reusable() {
		return $this->is_reusable;
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		// TODO: Use const from SEPA upe method class, when implemented.
		return $this->is_reusable() ? 'sepa_debit' : null;
	}

	/**
	 * Add payment method from intent to user and return WC payment token.
	 *
	 * By default we use WC_Payment_Token_SEPA, because most
	 * payment methods support saving payment methods via
	 * conversion to SEPA Direct Debit.
	 *
	 * @param WP_User $user User to add payment token to.
	 * @param object $intent JSON object for Stripe payment intent.
	 *
	 * @return WC_Payment_Token_SEPA|null WC object for payment token.
	 */
	public function add_token_to_user( $user, $intent ) {
		// TODO: Need to test this with SEPA, instead of methods converted to SEPA.
		if ( ! $this->is_reusable() ) {
			return null;
		}
		$payment_method_details = $this->get_payment_method_details_from_intent( $intent );
		return $this->create_payment_token_for_user( $user->ID, $payment_method_details->generated_sepa_debit, $payment_method_details->iban_last4 );
	}

	/**
	 * Add payment method to user and return WC payment token.
	 *
	 * This will be used from the WC_Stripe_Payment_Tokens service
	 * as opposed to WC_Stripe_UPE_Payment_Gateway.
	 *
	 * @param string $user_id        WP_User ID
	 * @param object $payment_method Stripe payment method object
	 *
	 * @return WC_Payment_Token_SEPA
	 */
	public function add_token_to_user_from_payment_method( $user_id, $payment_method ) {
		return $this->create_payment_token_for_user( $user_id, $payment_method->id, $payment_method->sepa_debit->last4 );
	}

	/**
	 * Create new WC payment token and add to user.
	 *
	 * @param string $user_id           WP_User ID
	 * @param string $payment_method_id Stripe payment method ID
	 * @param string $last4             IBAN/SEPA Debit last 4 digits
	 *
	 * @return WC_Payment_Token_SEPA
	 */
	private function create_payment_token_for_user( $user_id, $payment_method_id, $last4 ) {
		$token = new WC_Payment_Token_SEPA();
		$token->set_last4( $last4 );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method_id );
		$token->set_payment_method_type( $this->get_id() );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @return bool
	 */
	public function is_subscription_item_in_cart() {
		if ( class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ) ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || 0 < count( wcs_get_order_type_cart_items( 'renewal' ) );
		}
		return false;
	}

	/**
	 * Returns payment method details from Payment Intent
	 * in order to save payment method.
	 *
	 * @param object $intent JSON object for Stripe payment intent.
	 *
	 * @return object
	 */
	protected function get_payment_method_details_from_intent( $intent ) {
		if ( 'payment_intent' === $intent->object ) {
			$charge                 = end( $intent->charges->data );
			$payment_method_details = (array) $charge->payment_method_details;
			return $payment_method_details[ $this->stripe_id ];
		} elseif ( 'setup_intent' === $intent->object ) {
			// TODO: I think we will need to do something different here to get the generated SEPA pm...
			return null;
		}
	}

	/**
	 * Returns the currencies this UPE method supports.
	 *
	 * @return array|null
	 */
	public function get_supported_currencies() {
		return apply_filters(
			'wc_stripe_' . static::STRIPE_ID . '_upe_supported_currencies',
			$this->supported_currencies
		);
	}
}
