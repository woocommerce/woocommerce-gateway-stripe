<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The BLIK Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_BLIK extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::BLIK;

	/**
	 * Constructor for BLIK payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = 'BLIK';
		$this->is_reusable              = true;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::POLISH_ZLOTY ];
		$this->supported_countries      = [ 'PL' ];
		$this->label                    = 'BLIK';
		$this->description              = __(
			'BLIK enables customers in Poland to pay directly via online payouts from their bank account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;
		$this->supports[]               = 'tokenization';
	}

	/**
	 * Checks if BLIK is available for the Stripe account's country.
	 *
	 * @return bool True if PL-based account; false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

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

			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-payment-form" style="font-size: inherit;">
				<?php
					woocommerce_form_field(
						'wc-stripe-blik-code',
						[
							'maxlength' => 6,
							'label' => esc_html__( 'BLIK Code', 'woocommerce-gateway-stripe' ),
							'required' => true,
							'type' => 'text',
						]
					);
				?>
				<p>
					<?php echo esc_html__( 'After submitting your order, please authorize the payment in your mobile banking application.', 'woocommerce-gateway-stripe' ); ?>
				</p>
			</fieldset>

			<?php
			if ( $this->should_show_save_option() ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				if ( is_user_logged_in() ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}

			do_action( 'wc_stripe_payment_fields_' . $this->id, $this->id );
		} catch ( Exception $e ) {
			// Output the error message.
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			?>
			<div>
				<?php echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Creates a BLIK payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token_BLIK|null The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		if ( ! isset( $payment_method->id ) || ! isset( $payment_method->blik ) ) {
			return null;
		}

		$payment_token = new WC_Payment_Token_BLIK();
		$payment_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$payment_token->set_user_id( $user_id );
		$payment_token->set_token( $payment_method->id );
		$payment_token->set_last4( $payment_method->blik->last4 );
		$payment_token->set_bank_name( $payment_method->blik->bank_name );
		$payment_token->set_fingerprint( $payment_method->blik->fingerprint );
		$payment_token->save();

		return $payment_token;
	}
}
