<?php
/**
 * Trait WC_Stripe_Fingerprint_Trait tests.
 */
class WC_Stripe_Fingerprint_Test extends WP_UnitTestCase {
	/**
	 * Test for `get_fingerprint` and `set_fingerprint`.
	 *
	 * @return void
	 */
	public function test_fingerprint() {
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_fingerprint( '123abc' );
		$this->assertEquals( '123abc', $token->get_fingerprint() );
	}
}
