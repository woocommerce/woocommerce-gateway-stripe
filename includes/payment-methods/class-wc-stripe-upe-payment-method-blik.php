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
		$this->supported_countries      = [ WC_Stripe_Country_Code::AT, WC_Stripe_Country_Code::BE, WC_Stripe_Country_Code::BG, WC_Stripe_Country_Code::HR, WC_Stripe_Country_Code::CY, WC_Stripe_Country_Code::CZ, WC_Stripe_Country_Code::DK, WC_Stripe_Country_Code::EE, WC_Stripe_Country_Code::FI, WC_Stripe_Country_Code::FR, WC_Stripe_Country_Code::DE, WC_Stripe_Country_Code::GR, WC_Stripe_Country_Code::HU, WC_Stripe_Country_Code::IS, WC_Stripe_Country_Code::IE, WC_Stripe_Country_Code::IT, WC_Stripe_Country_Code::LV, WC_Stripe_Country_Code::LI, WC_Stripe_Country_Code::LT, WC_Stripe_Country_Code::LU, WC_Stripe_Country_Code::MT, WC_Stripe_Country_Code::NL, WC_Stripe_Country_Code::NO, WC_Stripe_Country_Code::PL, WC_Stripe_Country_Code::PT, WC_Stripe_Country_Code::RO, WC_Stripe_Country_Code::SK, WC_Stripe_Country_Code::SI, WC_Stripe_Country_Code::ES, WC_Stripe_Country_Code::SE ];
		$this->label                    = 'BLIK';
		$this->description              = __(
			'BLIK enables customers in Poland to pay directly via online payouts from their bank account.',
			'woocommerce-gateway-stripe'
		);
		$this->supports_deferred_intent = false;

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();

		$this->maybe_hide_blik();
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

	/**
	 * Returns testing instructions to be printed at checkout in test mode.
	 *
	 * @param bool $show_optimized_checkout_instruction Deprecated. Whether to show optimized checkout instructions.
	 * @return string
	 */
	public function get_testing_instructions( $show_optimized_checkout_instruction = false ) {
		if ( false !== $show_optimized_checkout_instruction ) {
			_deprecated_argument(
				__FUNCTION__,
				'9.9.0'
			);
		}

		return sprintf(
			/* translators: 1) HTML strong open tag 2) HTML strong closing tag */
			esc_html__( '%1$sTest mode:%2$s use any 6-digit number to authorize payment.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>',
		);
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
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>">
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
				</div>
				<p>
					<?php echo esc_html__( 'After submitting your order, please authorize the payment in your mobile banking application.', 'woocommerce-gateway-stripe' ); ?>
				</p>
			</fieldset>

			<?php
			do_action( 'wc_stripe_payment_fields_' . $this->id, $this->id );
		} catch ( Exception $e ) {
			// Output the error message.
			WC_Stripe_Logger::error( 'Error in BLIK payment fields', [ 'error_message' => $e->getMessage() ] );
			?>
			<div>
				<?php echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Returns the supported customer locations for which charges for BLIK can be processed.
	 *
	 * @return array Supported customer locations.
	 */
	public function get_available_billing_countries() {
		return [ WC_Stripe_Country_Code::PL ];
	}

	/**
	 * Determines whether BLIK should be hidden.
	 *
	 * It should hide for pre-orders that are charged upon release.
	 * WooCommerce Pre-Orders allows merchants to choose when to charge customers.
	 * BLIK only supports upfront charges.
	 *
	 * @return bool True if BLIK should be hidden, false otherwise.
	 */
	public function should_hide_blik() {
		if ( $this->is_pre_order_item_in_cart() ) {
			$product = $this->get_pre_order_product_from_cart();

			if ( $this->is_pre_order_product_charged_upon_release( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Conditionally hides BLIK in specific scenarios.
	 */
	public function maybe_hide_blik() {
		add_filter(
			'woocommerce_available_payment_gateways',
			function ( $available_gateways ) {
				if ( $this->should_hide_blik() ) {
					unset( $available_gateways['stripe_blik'] );
				}

				return $available_gateways;
			}
		);
	}
}
