<?php

class WC_Stripe_Email_Admin_Failed_Refund_Test extends WP_UnitTestCase {
	/**
	 * Tests for the `__constructor` method.
	 *
	 * @return void
	 */
	public function test_instance() {
		$email = new WC_Stripe_Email_Admin_Failed_Refund();

		$this->assertInstanceOf( WC_Stripe_Email_Admin_Failed_Refund::class, $email );

		$this->assertEquals( 'wc_stripe_failed_refund_admin', $email->id );
		$this->assertEquals( 'Refund failure emails are sent to the admin when an attempt to process a refund fails.', $email->description );
		$this->assertEquals( 'emails/failed-refund-admin.php', $email->template_html );
		$this->assertEquals( 'emails/plain/failed-refund-admin.php', $email->template_plain );
		$this->assertEquals( plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/', $email->template_base );
		$this->assertNull( $email->recipient ); // Recipient is set in the `trigger` method, so it should be null here.
	}

	/**
	 * Tests for the `get_template_params` method, including the `additional_content` field.
	 *
	 * @return void
	 */
	public function test_get_template_params() {
		$email = new WC_Stripe_Email_Admin_Failed_Refund();

		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->getMock();

		$email->object = $order;

		$params = $email->get_template_params();

		$this->assertEquals( $order, $params['order'] );
		$this->assertEquals( 'Unknown reason', $params['reason'] );
		$this->assertEquals( 'Refund failed', $params['email_heading'] );
		$this->assertArrayHasKey( 'additional_content', $params );
		$this->assertTrue( $params['sent_to_admin'] );
		$this->assertFalse( $params['plain_text'] );
		$this->assertInstanceOf( WC_Stripe_Email_Admin_Failed_Refund::class, $params['email'] );
	}

	/**
	 * The merchant's "Additional content" setting is rendered in the HTML email body.
	 *
	 * @return void
	 */
	public function test_renders_additional_content_in_html() {
		$order = WC_Helper_Order::create_order();
		$email = new WC_Stripe_Email_Admin_Failed_Refund();

		$email->settings['additional_content'] = 'Please review this failed refund.';
		$email->object                         = $order;

		$this->assertStringContainsString( 'Please review this failed refund.', $email->get_content_html() );
	}

	/**
	 * The merchant's "Additional content" setting is rendered in the plain-text email body.
	 *
	 * @return void
	 */
	public function test_renders_additional_content_in_plain() {
		$order = WC_Helper_Order::create_order();
		$email = new WC_Stripe_Email_Admin_Failed_Refund();

		$email->settings['additional_content'] = 'Please review this failed refund.';
		$email->object                         = $order;

		$this->assertStringContainsString( 'Please review this failed refund.', $email->get_content_plain() );
	}

	/**
	 * With no saved additional content, the email must not fall back to WC_Email_Failed_Order's
	 * merchant-facing "failed payments" default, which is wrong for a refund-failed email.
	 *
	 * @return void
	 */
	public function test_does_not_inherit_failed_order_default_additional_content() {
		$order         = WC_Helper_Order::create_order();
		$email         = new WC_Stripe_Email_Admin_Failed_Refund();
		$email->object = $order;

		$this->assertSame( '', $email->get_default_additional_content() );
		$this->assertStringNotContainsString( 'troubleshooting failed payments', $email->get_content_html() );
		$this->assertStringNotContainsString( 'troubleshooting failed payments', $email->get_content_plain() );
	}
}
