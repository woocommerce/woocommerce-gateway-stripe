<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Affirm Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Affirm extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = 'affirm';

	/**
	 * Constructor for Affirm payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = false;
		$this->supported_currencies         = [ 'CAD', 'USD' ];
		$this->supported_countries          = [ 'US', 'CA' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Affirm', 'woocommerce-gateway-stripe' );
		$this->description                  = __(
			'Allow customers to pay over time with Affirm.',
			'woocommerce-gateway-stripe'
		);
		$this->limits_per_currency          = [
			'CAD' => [
				'CA' => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents CAD 50 - 30,000 CAD.
			],
			'USD' => [
				'US' => [
					'min' => 5000,
					'max' => 3000000,
				], // Represents USD 50 - 30,000 USD.
			],
		];
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Affirm is only available domestic transactions in the United States or Canada.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 *
	 * @inheritDoc
	 */
	public function requires_automatic_capture() {
		return false;
	}
}
