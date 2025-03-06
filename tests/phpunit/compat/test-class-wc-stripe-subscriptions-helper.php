<?php
/**
 * Class WC_Stripe_Subscriptions_Helper_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Subscriptions_Helper
 */

/**
 * Class WC_Stripe_Subscriptions_Helper tests.
 */
class WC_Stripe_Subscriptions_Helper_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_subscriptions_enabled`.
	 *
	 * @return void
	 */
	public function test_is_subscriptions_enabled() {
		$this->assertTrue( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() );
	}

	/**
	 * Test for `get_detached_subscriptions`.
	 *
	 * @return void
	 */
	public function test_get_detached_subscriptions() {
		$subscription_id = 1;
		$customer_id     = 'cus_123';
		$source_id       = 'src_123';

		$subscription = new WC_Subscription();
		$subscription->set_id( $subscription_id );
		$subscription->set_status( 'active' );
		$subscription->save();

		$subscription->update_meta_data( '_stripe_customer_id', $customer_id );
		$subscription->update_meta_data( '_stripe_source_id', $source_id );
		$subscription->save_meta_data();

		WC_Subscriptions_Helpers::$wcs_get_subscriptions = [ $subscription ];

		// Mock response from Stripe API.
		$test_request = function () {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'customer' => null,
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$expected = [
			[
				'id'                        => $subscription_id,
				'customer_id'               => $customer_id,
				'change_payment_method_url' => $subscription->get_change_payment_method_url(),
			],
		];
		$this->assertEquals( $expected, WC_Stripe_Subscriptions_Helper::get_detached_subscriptions() );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );

		// Test cached version
		$this->assertEquals( $expected, WC_Stripe_Subscriptions_Helper::get_detached_subscriptions() );
	}
}
