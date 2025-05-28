<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Afterpay / Clearpay Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY;

	/**
	 * Constructor for afterpay / clearpay payment method
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Clearpay / Afterpay', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR,
			WC_Stripe_Currency_Code::POUND_STERLING,
			WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR,
			WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR,
		];
		$this->supported_countries          = [ 'AU', 'CA', 'GB', 'NZ', 'US' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Clearpay / Afterpay', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Clearpay / Afterpay.',
			'woocommerce-gateway-stripe'
		);
		$this->limits_per_currency          = [
			WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR    => [
				'AU' => [
					'min' => 100,
					'max' => 200000,
				], // Represents AUD 1 - 2,000 AUD.
			],
			WC_Stripe_Currency_Code::CANADIAN_DOLLAR      => [
				'CA' => [
					'min' => 100,
					'max' => 200000,
				], // Represents CAD 1 - 2,000 CAD.
			],
			WC_Stripe_Currency_Code::NEW_ZEALAND_DOLLAR   => [
				'NZ' => [
					'min' => 100,
					'max' => 200000,
				], // Represents NZD 1 - 2,000 NZD.
			],
			WC_Stripe_Currency_Code::POUND_STERLING       => [
				'GB' => [
					'min' => 100,
					'max' => 120000,
				], // Represents GBP 1 - 1,200 GBP.
			],
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR => [
				'US' => [
					'min' => 100,
					'max' => 400000,
				], // Represents USD 1 - 4,000 USD.
			],
		];
	}

	/**
	 * Returns payment method title
	 *
	 * @param stdClass|array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		if ( $this->is_gb_country() ) {
			$this->title = __( 'Clearpay', 'woocommerce-gateway-stripe' );
		} else {
			$this->title = __( 'Afterpay', 'woocommerce-gateway-stripe' );
		}

		return parent::get_title( $payment_details );
	}

	/**
	 * Returns payment method icon.
	 *
	 * @return string|null
	 */
	public function get_icon() {
		$icon_file_name = $this->is_gb_country() ? 'clearpay' : 'afterpay';

		$asset_url = sprintf( '%s/assets/images/%s.svg', WC_STRIPE_PLUGIN_URL, $icon_file_name );

		return sprintf(
			'<img src="%s" class="stripe-%s-icon stripe-icon" alt="%s">',
			esc_url( $asset_url ),
			esc_attr( $this->stripe_id ),
			esc_attr( $this->get_title() )
		);
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}

	/**
	 * Returns true if the Stripe account country is GB
	 *
	 * @return boolean
	 */
	private function is_gb_country() {
		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country     = $cached_account_data['country'] ?? null;
		return 'GB' === $account_country;
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Afterpay / Clearpay is available for the following countries: AU, CA, GB, NZ, US.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}
}
