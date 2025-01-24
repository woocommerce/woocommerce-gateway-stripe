<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Bacs Direct Debit Payment Method class extending UPE base class.
 */
class WC_Stripe_UPE_Payment_Method_Bacs extends WC_Stripe_UPE_Payment_Method {
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
}
