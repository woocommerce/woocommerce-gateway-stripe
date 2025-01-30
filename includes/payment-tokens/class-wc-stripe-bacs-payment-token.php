<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

class WC_Payment_Token_Bacs_Debit extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
	use WC_Stripe_Fingerprint_Trait;

	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected $type = WC_Stripe_Payment_Methods::BACS_DEBIT;

	/**
	 * Bacs Debit payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'last4'               => '',
		'payment_method_type' => WC_Stripe_Payment_Methods::BACS_DEBIT,
		'fingerprint'         => '',
	];

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param  object $payment_method Payment method object.
	 * @return bool
	 */
	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::BACS_DEBIT === $payment_method->type
			&& ( $payment_method->bacs_debit->fingerprint ?? null ) === $this->get_fingerprint() ) {
			return true;
		}

		return false;
	}

	/**
	 * Set the last four digits for the Bacs Debit Token.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the last four digits of the Bacs Debit Token.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string The last 4 digits.
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set Stripe payment method type.
	 *
	 * @param string $type Payment method type.
	 */
	public function set_payment_method_type( $type ) {
		$this->set_prop( 'payment_method_type', $type );
	}

	/**
	 * Returns Stripe payment method type.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string $payment_method_type
	 */
	public function get_payment_method_type( $context = 'view' ) {
		return $this->get_prop( 'payment_method_type', $context );
	}
}

