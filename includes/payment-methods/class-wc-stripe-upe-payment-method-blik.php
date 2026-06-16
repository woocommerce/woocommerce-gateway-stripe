<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The BLIK Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_BLIK extends WC_Stripe_UPE_Payment_Method {

	public const STRIPE_ID = WC_Stripe_Payment_Methods::BLIK;

	/**
	 * Stripe account countries that may not enable BLIK.
	 *
	 * @var string[]
	 */
	protected const UNSUPPORTED_ACCOUNT_COUNTRIES = [
		WC_Stripe_Country_Code::BRAZIL,
		WC_Stripe_Country_Code::GIBRALTAR,
		WC_Stripe_Country_Code::HONG_KONG,
		WC_Stripe_Country_Code::JAPAN,
		WC_Stripe_Country_Code::MALAYSIA,
		WC_Stripe_Country_Code::MEXICO,
		WC_Stripe_Country_Code::NEW_ZEALAND,
		WC_Stripe_Country_Code::THAILAND,
		WC_Stripe_Country_Code::UNITED_ARAB_EMIRATES,
	];

	/**
	 * Shopper billing countries permitted to use BLIK.
	 *
	 * @var string[]
	 */
	protected const SUPPORTED_BILLING_COUNTRIES = [ WC_Stripe_Country_Code::POLAND ];

	/**
	 * Constructor for BLIK payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                = self::STRIPE_ID;
		$this->title                    = 'BLIK';
		$this->is_reusable              = false;
		$this->supported_currencies     = [ WC_Stripe_Currency_Code::POLISH_ZLOTY ];
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
	 * Returns testing instructions to be printed at checkout in test mode.
	 *
	 * @param bool $show_optimized_checkout_instruction Deprecated. Whether to show optimized checkout instructions.
	 * @param bool $include_test_mode_label Whether to include the "Test mode:" label prefix. Pass false for
	 *                                      Blocks checkout, which already displays a Test Mode badge.
	 * @return string
	 */
	public function get_testing_instructions( bool $show_optimized_checkout_instruction = false, bool $include_test_mode_label = true ) {
		if ( false !== $show_optimized_checkout_instruction ) {
			_deprecated_argument(
				__FUNCTION__,
				'9.9.0'
			);
		}

		if ( $include_test_mode_label ) {
			return sprintf(
				/* translators: 1) HTML strong open tag 2) HTML strong closing tag */
				esc_html__( '%1$sTest mode:%2$s use any 6-digit number.', 'woocommerce-gateway-stripe' ),
				'<strong>',
				'</strong>',
			);
		}

		return esc_html__( 'Use any 6-digit number.', 'woocommerce-gateway-stripe' );
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
								'label'     => esc_html__( 'BLIK Code', 'woocommerce-gateway-stripe' ),
								'required'  => true,
								'type'      => 'text',
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
		return self::SUPPORTED_BILLING_COUNTRIES;
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
