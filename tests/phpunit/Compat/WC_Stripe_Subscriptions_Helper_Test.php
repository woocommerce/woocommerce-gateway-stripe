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
			'empty list'     => [
				'subscriptions' => [],
				'expected'      => '',
			],
			'non-empty list' => [
				'subscriptions' => [
					[
						'id'                        => 1,
						'customer_id'               => 'cus_123',
						'change_payment_method_url' => 'https://example.com/my-account/subscription-payment-method/1',
					],
				],
				'expected'      => 'Some subscriptions are missing payment methods,',
			],
		];
	}

	/**
	 * Tests for `is_subscription_payment_method_detached`.
	 *
	 * @return void
	 */
	public function test_is_subscription_payment_method_detached() {
		$subscription = new WC_Subscription();
		$subscription->set_id( 1 );
		$subscription->set_status( 'active' );
		$subscription->save();

		$subscription->update_meta_data( '_stripe_customer_id', 'cus_123' );
		$subscription->update_meta_data( '_stripe_source_id', 'src_123' );
		$subscription->save_meta_data();

		// Mock response from Stripe API.
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode(
						[
							'customer' => null,
						]
					),
				];
			},
			10,
			3
		);

		$this->assertTrue( WC_Stripe_Subscriptions_Helper::is_subscription_payment_method_detached( $subscription ) );

		remove_filter( 'pre_http_request', '__return_null', 10, 3 );
	}

	/**
	 * Tests for `build_subscription_detached_message`.
	 *
	 * @return void
	 */
	public function test_build_subscription_detached_message() {
		$subscription_data = [
			'id'                        => 1,
			'customer_id'               => 'cus_123',
			'change_payment_method_url' => 'https://example.com/my-account/subscription-payment-method/1',
		];

		$expected = '#1: <a href="https://example.com/my-account/subscription-payment-method/1">Payment method page &rarr;</a> | <a href="https://dashboard.stripe.com/customers/cus_123">Stripe customer page &rarr;</a><br/>';
		$actual   = WC_Stripe_Subscriptions_Helper::build_subscription_detached_message( $subscription_data );
		$this->assertEquals( $expected, $actual );
	}
}
