<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Bacs Direct Debit Payment Method class extending UPE base class.
 *
 * @since 9.3.0
 */
class WC_Stripe_UPE_Payment_Method_Bacs_Debit extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * The Stripe ID for the payment method.
	 */
	const STRIPE_ID = WC_Stripe_Payment_Methods::BACS_DEBIT;

	/**
	 * Constructor for Bacs Direct Debit payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Bacs Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = true;
		$this->supported_currencies         = [ WC_Stripe_Currency_Code::POUND_STERLING ];
		$this->supported_countries          = [ 'GB' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Bacs Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description                  = __( 'Bacs Direct Debit enables customers in the UK to pay by providing their bank account details.', 'woocommerce-gateway-stripe' );
		$this->supports[]                   = 'tokenization';

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();

		$this->maybe_hide_bacs_payment_gateway();
	}

	/**
	 * Determines if the Stripe Account country supports Bacs Direct Debit.
	 *
	 * @return bool
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Returns true if Bacs Direct Debit is available for processing payments.
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout( $order_id = null, $account_domestic_currency = null ) {
		if ( ! WC_Stripe_Feature_Flags::is_bacs_lpm_enabled() ) {
			return false;
		}

		return parent::is_enabled_at_checkout( $order_id, $account_domestic_currency );
	}

	/**
	 * Returns a string representing payment method type to query for when retrieving saved payment methods from Stripe.
	 *
	 * @return string The payment method type.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Creates a Bacs Direct Debit payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_Bacs_Debit();
		$token->set_token( $payment_method->id );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$token->set_last4( $payment_method->bacs_debit->last4 );
		$token->set_fingerprint( $payment_method->bacs_debit->fingerprint );
		$token->set_payment_method_type( $this->get_id() );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Conditionally hides the Bacs payment gateway for specific scenarios.
	 */
	public function maybe_hide_bacs_payment_gateway() {
		add_filter(
			'woocommerce_available_payment_gateways',
			function ( $available_gateways ) {
				if (
					$this->should_hide_bacs_for_pre_orders_charge_upon_release() ||
					$this->should_hide_bacs_for_subscriptions_with_free_trials() ||
					$this->should_hide_bacs_on_add_payment_method_page()
				) {
					unset( $available_gateways['stripe_bacs_debit'] );
				}
				return $available_gateways;
			}
		);
	}

	/**
	 * Determines whether the Bacs payment gateway should be hidden on the "Add Payment Method" page.
	 *
	 * @return bool True if the Bacs payment gateway should be hidden, false otherwise.
	 */
	public function should_hide_bacs_on_add_payment_method_page() {
		if ( is_wc_endpoint_url( 'add-payment-method' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines whether the Bacs payment gateway should be hidden for pre-orders that are charged upon release.
	 *
	 * WooCommerce Pre-Orders allows merchants to choose when to charge customers.
	 * If a product is set to be charged upon release, Bacs can't be used for now as setup intents are not supported for Bacs.
	 *
	 * @return bool True if Bacs should be hidden, false otherwise.
	 */
	public function should_hide_bacs_for_pre_orders_charge_upon_release() {
		if ( is_checkout() && class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() ) {
			$cart = WC()->cart->get_cart();
			// Iteration is unnecessary since only one pre-order product can be in the cart.
			$product_id = reset( $cart )['product_id'];
			if ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determines whether the Bacs payment gateway should be hidden for subscriptions with free trials.
	 *
	 * If the cart contains a subscription with a free trial and the cart total amount is zero,
	 * Bacs can't be used for now as setup intents are not supported for Bacs.
	 *
	 * @return bool True if Bacs should be hidden, false otherwise.
	 */
	public function should_hide_bacs_for_subscriptions_with_free_trials() {
		$is_update_order_review_ajax_request = defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['wc-ajax'] ) && 'update_order_review' === $_REQUEST['wc-ajax'];
		if ( is_checkout() || $is_update_order_review_ajax_request ) {
			// Checking if the amount is zero allows us to process orders that include subscriptions with a free trial,
			// as long as another product increases the total amount, ensuring compatibility with Bacs.
			if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_free_trial() && (float) WC()->cart->total === 0.00 ) {
				return true;
			}
		}
		return false;
	}
}
