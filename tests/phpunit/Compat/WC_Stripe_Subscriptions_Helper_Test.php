<?php

namespace WooCommerce\Stripe\Tests\Compat;

use WC_Stripe_Database_Cache;
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
	 * Test for `test_is_manual_renewal_required`.
	 *
	 * @return void
	 */
	public function test_is_manual_renewal_required() {
		// This test assumes that manual renewal is not required.
		// If the logic changes, this test should be updated accordingly.
		$this->assertFalse( WC_Stripe_Subscriptions_Helper::is_manual_renewal_required() );
	}

	/**
	 * Test for `is_manual_renewal_enabled`.
	 *
	 * @return void
	 */
	public function test_is_manual_renewal_enabled() {
		// This test assumes that manual renewal is not enabled.
		// If the logic changes, this test should be updated accordingly.
		$this->assertFalse( WC_Stripe_Subscriptions_Helper::is_manual_renewal_enabled() );
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
		$subscription->set_time( 'next_payment_date', strtotime( '+1 week' ) );
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
		$result   = WC_Stripe_Subscriptions_Helper::get_detached_subscriptions();

		// Clean up before we run any assertions
		remove_filter( 'pre_http_request', $test_request, 10, 3 );

		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_' . $source_id );

		$this->assertEquals( $expected, $result );

		// Test the full cached version
		$result = WC_Stripe_Subscriptions_Helper::get_detached_subscriptions();

		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_' . $source_id );
		WC_Stripe_Database_Cache::delete( 'detached_subscriptions_-1' );

		$this->assertEquals( $expected, $result );

		WC_Subscriptions_Helpers::$wcs_get_subscriptions = null;
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
