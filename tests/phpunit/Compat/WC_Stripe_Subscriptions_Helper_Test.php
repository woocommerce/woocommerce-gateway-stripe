<?php

namespace WooCommerce\Stripe\Tests\Compat;

use WC_Stripe_Subscriptions_Helper;
use WC_Subscription;
use WC_Subscriptions_Helpers;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_Subscriptions_Helper_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Subscriptions_Helper
 *
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
		add_filter(
			'wc_stripe_unit_test_get_subscription_time_next_payment_date',
			function () {
				return strtotime( '+1 week' );
			}
		);

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

		// Mock the change payment method URL.
		$mocked_payment_method_url = 'https://example.com/my-account/subscription-payment-method/' . $subscription_id;
		add_filter(
			'wcs_get_change_payment_method_url',
			function () use ( $mocked_payment_method_url ) {
				return $mocked_payment_method_url;
			},
			10,
			2
		);

		$expected = [
			[
				'id'                        => $subscription_id,
				'customer_id'               => $customer_id,
				'change_payment_method_url' => $mocked_payment_method_url,
			],
		];
		$this->assertEquals( $expected, WC_Stripe_Subscriptions_Helper::get_detached_subscriptions() );

		remove_filter( 'pre_http_request', $test_request, 10, 3 );

		// Test cached version
		$this->assertEquals( $expected, WC_Stripe_Subscriptions_Helper::get_detached_subscriptions() );

		WC_Subscriptions_Helpers::$wcs_get_subscriptions = null;

		remove_filter(
			'wc_stripe_unit_test_get_subscription_time_next_payment_date',
			function () {
				return strtotime( '+1 week' );
			}
		);
	}

	/**
	 * Test for `build_subscriptions_detached_messages`.
	 *
	 * @param array $subscriptions An array of subscriptions to build messages for.
	 * @param string $expected The expected messages content.
	 * @return void
	 *
	 * @dataProvider provide_test_build_subscriptions_detached_messages
	 */
	public function test_build_subscriptions_detached_messages( $subscriptions, $expected ) {
		$messages = WC_Stripe_Subscriptions_Helper::build_subscriptions_detached_messages( $subscriptions );
		$this->assertStringContainsString( $expected, $messages );
	}

	/**
	 * Provides data for testing `test_build_subscriptions_detached_messages`.
	 *
	 * @return array
	 */
	public function provide_test_build_subscriptions_detached_messages() {
		return [
			'empty list'             => [
				'subscriptions' => [],
				'expected'      => '',
			],
			'non-empty list'         => [
				'subscriptions' => [
					[
						'id'                        => 1,
						'customer_id'               => 'cus_123',
						'change_payment_method_url' => 'https://example.com/my-account/subscription-payment-method/1',
					],
				],
				'expected'      => '1 subscription is missing the payment method,',
			],
			'multiple subscriptions' => [
				'subscriptions' => [
					[
						'id'                        => 1,
						'customer_id'               => 'cus_123',
						'change_payment_method_url' => 'https://example.com/my-account/subscription-payment-method/1',
					],
					[
						'id'                        => 2,
						'customer_id'               => 'cus_456',
						'change_payment_method_url' => 'https://example.com/my-account/subscription-payment-method/2',
					],
				],
				'expected'      => '2 subscriptions are missing payment methods,',
			],
		];
	}
}
