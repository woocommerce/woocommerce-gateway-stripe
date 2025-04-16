<?php
/**
 * Class WC_Stripe_Payment_Method_Configurations tests.
 */
class WC_Stripe_Payment_Method_Configurations_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_parent_configuration_id`.
	 *
	 * @return void
	 */
	public function test_get_parent_configuration_id() {
		$this->assertNull( WC_Stripe_Payment_Method_Configurations::get_parent_configuration_id() );
	}
}
