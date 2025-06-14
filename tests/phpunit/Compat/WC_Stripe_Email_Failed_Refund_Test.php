<?php

namespace WooCommerce\Stripe\Tests\Compat;

use WC_Email;
use WC_Stripe_Email_Failed_Refund;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_Email_Failed_Refund_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Email_Failed_Refund
 *
 * Class WC_Stripe_Email_Failed_Refund tests.
 */
class WC_Stripe_Email_Failed_Refund_Test extends WP_UnitTestCase {

	/**
	 * Test that the WC_Stripe_Email_Failed_Refund class is instantiated correctly.
	 * Test also the setters.
	 */
	public function test_instance() {
		$email = $this->get_mocked_class();

		$this->assertInstanceOf( WC_Stripe_Email_Failed_Refund::class, $email );
		$this->assertEquals( 'Refund request failed', $email->get_title() );
		$this->assertEquals( 'Refund request failed', $email->get_default_heading() );
		$this->assertEquals( '[{site_title}] Refund request failed for #{order_number}.', $email->get_default_subject() );
	}

	/**
	 * Test that the `get_content_html` and `get_content_plan` methods returns the expected HTML content.
	 * @return void
	 */
	public function test_get_content() {
		$order = WC_Helper_Order::create_order();

		$email = $this->get_mocked_class();
		$email->set_object( $order );

		$html_content  = $email->get_content_html();
		$plain_content = $email->get_content_plain();

		$this->assertStringContainsString( 'The refund request for order', $html_content );
		$this->assertStringContainsString( 'The refund request for order', $plain_content );
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
