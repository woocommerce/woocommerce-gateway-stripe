<?php

namespace WooCommerce\Stripe\Tests\Compat;

use WC_Order;
use WC_Stripe_Email_Admin_Failed_Refund;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_Email_Admin_Failed_Refund_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Email_Admin_Failed_Refund
 *
 * Class WC_Stripe_Email_Admin_Failed_Refund tests.
 */
class WC_Stripe_Email_Admin_Failed_Refund_Test extends WP_UnitTestCase {
	/**
	 * Tests for the `__constructor` method.
	 *
	 * @return void
	 */
	public function test_instance() {
		$email = new WC_Stripe_Email_Admin_Failed_Refund();

		$this->assertInstanceOf( \WC_Stripe_Email_Admin_Failed_Refund::class, $email );

		$this->assertEquals( 'wc_stripe_failed_refund_admin', $email->id );
		$this->assertEquals( 'Refund failure emails are sent to the admin when an attempt to process a refund fails.', $email->description );
		$this->assertEquals( 'emails/failed-refund-admin.php', $email->template_html );
		$this->assertEquals( 'emails/plain/failed-refund-admin.php', $email->template_plain );
		$this->assertEquals( plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/', $email->template_base );
		$this->assertNull( $email->recipient ); // Recipient is set in the `trigger` method, so it should be null here.
	}

	/**
	 * Tests for the `get_template_params` method.
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
		$this->assertTrue( $params['sent_to_admin'] );
		$this->assertFalse( $params['plain_text'] );
		$this->assertInstanceOf( WC_Stripe_Email_Admin_Failed_Refund::class, $params['email'] );
	}
}
