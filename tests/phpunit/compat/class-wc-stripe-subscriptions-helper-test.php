<?php

/**
 * Class WC_Stripe_Subscriptions_Helper_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Subscriptions_Helper
 *
 * Class WC_Stripe_Subscriptions_Helper tests.
 */
class WC_Stripe_Subscriptions_Helper_Test extends WP_UnitTestCase {
	/**
	 * The original value of the HPOS option.
	 *
	 * @var string
	 */
	private static $original_hpos_value;

	/**
	 * @inheritDoc
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$original_hpos_value = get_option( 'woocommerce_custom_orders_table_enabled' );
	}

	/**
	 * @inheritDoc
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		update_option( 'woocommerce_custom_orders_table_enabled', self::$original_hpos_value );
	}

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
		$subscription->set_payment_method( 'stripe_klarna' );
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

	/**
	 * Tests for {@see WC_Stripe_Subscriptions_Helper::is_subscription_payment_method_detached()}.
	 *
	 * @param string $payment_method The payment method type for the subscription.
	 * @param array|null $source_meta The source meta data for the subscription.
	 * @param array|\WP_Error $mocked_response The mocked response from the Stripe API.
	 * @param bool $expected The expected result of the check.
	 * @return void
	 *
	 * @dataProvider provide_test_is_subscription_payment_method_detached
	 */
	public function test_is_subscription_payment_method_detached( $payment_method, $source_meta, $mocked_response, $expected ) {
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_' . $source_meta );

		$subscription = new WC_Subscription();
		$subscription->set_id( 1 );
		$subscription->set_status( 'active' );
		$subscription->set_payment_method( $payment_method );
		$subscription->save();

		if ( ! is_null( $source_meta ) ) {
			$subscription->update_meta_data( '_stripe_source_id', $source_meta );
		} else {
			$subscription->delete_meta_data( '_stripe_source_id' );
		}
		$subscription->save_meta_data();

		$mock_response_fn = function () use ( $mocked_response ) {
			return $mocked_response;
		};

		// Mock response from Stripe API.
		add_filter( 'pre_http_request', $mock_response_fn, 10, 3 );

		$actual = WC_Stripe_Subscriptions_Helper::is_subscription_payment_method_detached( $subscription );

		// Clean up.
		remove_filter( 'pre_http_request', $mock_response_fn, 10, 3 );
		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_' . $source_meta );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_is_subscription_payment_method_detached`.
	 *
	 * @return array[]
	 */
	public function provide_test_is_subscription_payment_method_detached() {
		return [
			'not a Stripe subscription'               => [
				'payment method' => 'not_stripe',
				'source meta'     => null,
				'mocked response' => null,
				'expected'        => false,
			],
			'missing meta'                            => [
				'payment method'  => 'stripe_klarna',
				'source meta'     => null,
				'mocked response' => null,
				'expected'        => false,
			],
			'wp error response, assumed detached'     => [
				'payment method'  => 'stripe_klarna',
				'source meta'     => 'src_123',
				'mocked response' => new \WP_Error( 'error', 'An error occurred.' ),
				'expected'        => true,
			],
			'Stripe error response, assumed detached' => [
				'payment method'  => 'stripe_klarna',
				'source meta'     => 'src_123',
				'mocked response' => [
					'response' => 400,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode(
						[
							'error' => [
								'type'    => 'invalid_request_error',
								'message' => 'Invalid request.',
							],
						]
					),
				],
				'expected'        => true,
			],
			'existing customer data'                  => [
				'payment method'  => 'stripe_klarna',
				'source meta'     => 'src_123',
				'mocked response' => [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode(
						[
							'customer' => 'cus_123',
						]
					),
				],
				'expected'        => false,
			],
			'detached payment method'                 => [
				'payment method'  => 'stripe_klarna',
				'source meta'     => 'src_123',
				'mocked response' => [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode(
						[
							'customer' => null,
						]
					),
				],
				'expected'        => true,
			],
		];
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

	/**
	 * Tests for `get_detached_payment_data_from_subscription`.
	 *
	 * @return void
	 */
	public function test_get_detached_payment_data_from_subscription() {
		$subscription = new WC_Subscription();
		$subscription->set_id( 1 );
		$subscription->set_status( 'active' );
		$subscription->save();

		$subscription->update_meta_data( '_stripe_customer_id', 'cus_1234' );
		$subscription->save_meta_data();

		$expected = [
			'id'                        => 1,
			'customer_id'               => 'cus_1234',
			'change_payment_method_url' => 'http://example.org?subscription-payment-method=1',
		];

		$actual = WC_Stripe_Subscriptions_Helper::get_detached_payment_data_from_subscription( $subscription );

		$this->assertSame( $expected, $actual );
	}
}
