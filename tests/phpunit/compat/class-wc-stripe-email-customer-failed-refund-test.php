<?php

class WC_Stripe_Email_Customer_Failed_Refund_Test extends WP_UnitTestCase {
	/**
	 * Tests for the `__constructor` method.
	 *
	 * @return void
	 */
	public function test_instance() {
		$email = new WC_Stripe_Email_Customer_Failed_Refund();

		$this->assertInstanceOf( WC_Stripe_Email_Customer_Failed_Refund::class, $email );

		$this->assertEquals( 'wc_stripe_failed_refund_customer', $email->id );
		$this->assertEquals( 'Sent to a customer when a refund fails or is cancelled. The email contains the original order information.', $email->description );
		$this->assertEquals( 'emails/failed-refund-customer.php', $email->template_html );
		$this->assertEquals( 'emails/plain/failed-refund-customer.php', $email->template_plain );
		$this->assertEquals( plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/', $email->template_base );
	}

	/**
	 * Tests for the `get_template_params` method.
	 *
	 * @return void
	 */
	public function test_get_template_params() {
		$email = new WC_Stripe_Email_Customer_Failed_Refund();

		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->getMock();

		$email->object = $order;

		$params = $email->get_template_params();

		$this->assertEquals( $order, $params['order'] );
		$this->assertEquals( 'Unknown reason', $params['reason'] );
		$this->assertEquals( 'Refund failed', $params['email_heading'] );
		$this->assertFalse( $params['sent_to_admin'] );
		$this->assertFalse( $params['plain_text'] );
		$this->assertInstanceOf( WC_Stripe_Email_Customer_Failed_Refund::class, $params['email'] );
	}
}
