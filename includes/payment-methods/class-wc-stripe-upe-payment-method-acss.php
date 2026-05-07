<?php

use Automattic\WooCommerce\Enums\PaymentGatewayFeature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Canadian Pre-Authorized Debit (ACSS Debit) Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_ACSS extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	const STRIPE_ID = WC_Stripe_Payment_Methods::ACSS_DEBIT;

	/**
	 * Constructor for ACSS Debit payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable              = true;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::CANADIAN_DOLLAR ]; // The US dollar is also supported, but has a high risk of failure since only a few Canadian bank accounts support it.
		$this->supported_countries      = [ WC_Stripe_Country_Code::CANADA ];
		$this->label                    = __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' );
		$this->description              = __(
			'Canadian Pre-Authorized Debit is a payment method that allows customers to pay using their Canadian bank account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;
		$this->supports[]               = PaymentGatewayFeature::TOKENIZATION;

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();
	}

	/**
	 * Renders the UPE payment fields.
	 *
	 * Overrides the base class to add an informational message since the Stripe Payment Element
	 * renders empty for ACSS Debit — the bank account authorization happens via a Stripe-hosted
	 * mandate modal during payment confirmation.
	 *
	 * @return void
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->is_reusable() && is_checkout();

			if ( $this->testmode && ! empty( $this->get_testing_instructions() ) ) : ?>
				<p class="testmode-info"><?php echo wp_kses_post( $this->get_testing_instructions() ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php
			if ( $display_tokenization ) {
				$this->tokenization_script();
				$this->saved_payment_methods();
			}
			?>
			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-upe-form" class="wc-upe-form wc-payment-form">
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
				<div id="wc-<?php echo esc_attr( $this->id ); ?>-upe-errors" role="alert"></div>
			</fieldset>
			<fieldset class="wc-stripe-redirect-notice">
				<svg class="wc-stripe-redirect-notice__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 40" fill="currentColor" role="presentation">
					<use href="<?php echo esc_url( WC_STRIPE_PLUGIN_URL . '/assets/images/payment-redirect.svg#icon' ); ?>" />
				</svg>
				<span class="wc-stripe-redirect-notice__text">
					<?php echo esc_html__( 'After submission, you will need to authorize the payment with your bank.', 'woocommerce-gateway-stripe' ); ?>
				</span>
			</fieldset>
			<?php
			if ( $this->should_show_save_option() ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page() || WC_Stripe_Helper::should_force_save_payment_method();
				if ( is_user_logged_in() ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}

			do_action( 'wc_stripe_payment_fields_' . $this->id, $this->id );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Error in ACSS payment fields', [ 'error_message' => $e->getMessage() ] );
			?>
			<div>
				<?php echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Creates an ACSS payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token_ACSS|null The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$payment_token = new WC_Payment_Token_ACSS();
		$payment_token->set_token( $payment_method->id );
		$payment_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_last4( $payment_method->acss_debit->last4 );
		$payment_token->set_bank_name( $payment_method->acss_debit->bank_name );
		$payment_token->set_fingerprint( $payment_method->acss_debit->fingerprint );
		$payment_token->save();

		return $payment_token;
	}
}
