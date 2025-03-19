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
		$this->is_reusable              = false;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::POLISH_ZLOTY ];
		$this->supported_countries      = [ 'PL' ];
		$this->label                    = 'BLIK';
		$this->description              = __(
			'BLIK enables customers in Poland to pay directly via online payouts from their bank account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;
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
			if ( $this->testmode && ! empty( $this->get_testing_instructions() ) ) : ?>
				<p class="testmode-info"><?php echo wp_kses_post( $this->get_testing_instructions() ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

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
}
