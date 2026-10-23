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
		$this->assertEquals( 'emails/failed-refund-admin.php', $email->template_html );
		$this->assertEquals( 'emails/plain/failed-refund-admin.php', $email->template_plain );
	}

	/**
	 * The `additional_content` field is passed to the template.
	 *
	 * @return void
	 */
	public function test_get_template_params_includes_additional_content() {
		$email = new WC_Stripe_Email_Admin_Failed_Refund();

		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->getMock();

		$email->object = $order;

		$this->assertArrayHasKey( 'additional_content', $email->get_template_params() );
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
