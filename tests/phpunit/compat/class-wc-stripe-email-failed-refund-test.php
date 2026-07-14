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

	/**
	 * Create a mock class for WC_Stripe_Email_Failed_Refund.
	 *
	 * @return WC_Stripe_Email_Failed_Refund
	 */
	protected function get_mocked_class() {
		return new class() extends WC_Stripe_Email_Failed_Refund {
			public function __construct() {
				parent::__construct();

				$this->id          = 'failed_refund_custom';
				$this->description = __( 'Refund request failure emails are sent to chosen recipient(s) when an attempt to process refund fails.', 'woocommerce-gateway-stripe' );

				$this->template_html  = 'emails/failed-refund-admin.php';
				$this->template_plain = 'emails/plain/failed-refund-admin.php';
				$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

				WC_Email::__construct();

				// Set after calling the parent constructor, so it is not override.
				$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
			}

			/**
			 * Returns the list of template parameters.
			 *
			 * @inheritDoc
			 */
			public function get_template_params() {
				return [
					'order'         => $this->object,
					'reason'        => $this->get_reason( $this->object ),
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => true,
					'plain_text'    => false,
					'email'         => $this,
				];
			}
		};
	}
}
