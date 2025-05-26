<?php
/**
 * Tests for the WC_Stripe_Status class.
 */

/**
 * Class WC_Stripe_Status_Test.
 */
class WC_Stripe_Status_Test extends WP_UnitTestCase {
	/**
	 * Test for `render_status_report_section`.
	 *
	 * @return void
	 */
	public function test_render_status_report_section() {
		$gateway = $this->getMockBuilder( 'WC_Gateway_Stripe' )
			->disableOriginalConstructor()
			->getMock();

		$gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn(
				[
					'card',
				]
			);

		$account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->getMock();

		$account->method( 'get_cached_account_data' )
			->willReturn(
				[
					'id'    => 'acct_123',
					'email' => 'john.doe@example.com',
				]
			);

		$status = new WC_Stripe_Status( $gateway, $account );

		ob_start();
		$status->render_status_report_section();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WooCommerce Stripe Payment Gateway', $output );
		$this->assertStringContainsString( 'acct_123', $output );
		$this->assertStringContainsString( 'john.doe@example.com', $output );
		$this->assertStringContainsString( 'card', $output );
	}
}
