<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

class WC_Payment_Token_Bacs extends WC_Payment_Token implements WC_Stripe_Payment_Method_Comparison_Interface {
	use WC_Stripe_Fingerprint_Trait;

	protected $type = WC_Stripe_Payment_Methods::BACS_DEBIT;

	protected $extra_data = [
		'last4'               => '',
		'payment_method_type' => WC_Stripe_Payment_Methods::BACS_DEBIT,
		'fingerprint'         => '',
	];

	public function is_equal_payment_method( $payment_method ): bool {
		if ( WC_Stripe_Payment_Methods::BACS_DEBIT === $payment_method->type
			&& ( $payment_method->bacs_debit->fingerprint ?? null ) === $this->get_fingerprint() ) {
			return true;
		}

		return false;
	}

	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	public function set_payment_method_type( $type ) {
		$this->set_prop( 'payment_method_type', $type );
	}

	public function get_payment_method_type( $context = 'view' ) {
		return $this->get_prop( 'payment_method_type', $context );
	}
}

