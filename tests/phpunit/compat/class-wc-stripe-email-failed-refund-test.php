<?php

/**
 * Class WC_Stripe_Email_Failed_Refund_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Email_Failed_Refund
 *
 * Class WC_Stripe_Email_Failed_Refund tests.
 */
class WC_Stripe_Email_Failed_Refund_Test extends WP_UnitTestCase {
	/**
	 * Tests for the `trigger` method.
	 *
	 * @param bool   $is_enabled     Whether the email is enabled.
	 * @param string $recipient      The recipient email address.
	 * @param bool   $expect_to_send Whether the email is expected to be sent.
	 * @return void
	 *
	 * @dataProvider provide_test_trigger
	 */
	public function test_trigger( $is_enabled, $recipient, $expect_to_send ) {
		$order = WC_Helper_Order::create_order();

		$email = $this->getMockBuilder( WC_Stripe_Email_Failed_Refund::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'send', 'is_enabled', 'get_recipient', 'get_content', 'get_template_params' ] )
			->getMock();

		$email->expects( $expect_to_send ? $this->once() : $this->never() )
			->method( 'send' );

		$email->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( $is_enabled );

		$email->expects( $is_enabled && $recipient ? $this->exactly( 2 ) : ( $is_enabled ? $this->once() : $this->never() ) )
			->method( 'get_recipient' )
			->willReturn( $recipient );

		$email->expects( $is_enabled && $recipient ? $this->once() : $this->never() )
			->method( 'get_content' )
			->willReturn( 'Email content' );

		$email->trigger( $order->get_id(), $order );
	}

	/**
	 * Provider for the `test_trigger` method.
	 *
	 * @return array
	 */
	public function provide_test_trigger() {
		return [
			'not enabled'  => [
				'is enabled'     => false,
				'recipient'      => 'test@example.com',
				'expect to send' => false,
			],
			'no recipient' => [
				'is enabled'     => true,
				'recipient'      => '',
				'expect to send' => false,
			],
			'email sent'   => [
				'is enabled'     => true,
				'recipient'      => 'admin@example.org',
				'expect to send' => true,
			],
		];
	}
}
