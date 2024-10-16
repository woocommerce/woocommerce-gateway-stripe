<?php
/**
 * Class WC_Stripe_Payment_Tokens tests.
 */
class WC_Stripe_Payment_Tokens_Test extends WP_UnitTestCase {

	/**
	 * WC_Stripe_Payment_Tokens instance.
	 *
	 * @var WC_Stripe_Payment_Tokens
	 */
	private $stripe_payment_tokens;

	public function set_up() {
		parent::set_up();
		$this->stripe_payment_tokens = new WC_Stripe_Payment_Tokens();
	}

	public function test_is_valid_payment_method_id() {
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'pm_1234567890' ) );
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'pm_1234567890', 'card' ) );
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'pm_1234567890', 'sepa' ) );

		// Test with source id (only card payment method type is valid).
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'src_1234567890', 'card' ) );
		$this->assertFalse( $this->stripe_payment_tokens->is_valid_payment_method_id( 'src_1234567890', 'sepa' ) );
		$this->assertFalse( $this->stripe_payment_tokens->is_valid_payment_method_id( 'src_1234567890', 'giropay' ) );
	}
}
