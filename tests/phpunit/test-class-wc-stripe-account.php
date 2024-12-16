<?php
/**
 * Class WC_Stripe_Account_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Account
 */

/**
 * Class WC_Stripe_Account tests.
 */
class WC_Stripe_Account_Test extends WP_UnitTestCase {
	/**
	 * The Stripe account instance.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_connect = $this->getMockBuilder( 'WC_Stripe_Connect' )
									->disableOriginalConstructor()
									->setMethods(
										[
											'is_connected',
										]
									)
									->getMock();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-account.php';
		$this->account = new WC_Stripe_Account( $this->mock_connect, 'WC_Helper_Stripe_Api' );
	}

	public function tear_down() {
		delete_transient( 'wcstripe_account_data_test' );
		delete_transient( 'wcstripe_account_data_live' );
		WC_Stripe_Helper::delete_main_stripe_settings();

		WC_Helper_Stripe_Api::reset();

		parent::tear_down();
	}

	public function test_get_cached_account_data_returns_empty_when_stripe_is_not_connected() {
		$this->mock_connect->method( 'is_connected' )->willReturn( false );
		$cached_data = $this->account->get_cached_account_data();

		$this->assertEmpty( $cached_data );
	}

	public function test_get_cached_account_data_returns_data_when_cache_is_valid() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $account );

		$cached_data = $this->account->get_cached_account_data();

		$this->assertSame( $cached_data, $account );
	}

	public function test_get_cached_account_data_fetch_data_when_cache_is_invalid() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$expected_cached_data = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];

		$cached_data = $this->account->get_cached_account_data();

		$this->assertSame( $cached_data, $expected_cached_data );
	}

	public function test_clear_cache() {
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $account );
		set_transient( 'wcstripe_account_data_live', $account );

		$this->account->clear_cache();
		$this->assertFalse( get_transient( 'wcstripe_account_data_test' ) );
		$this->assertFalse( get_transient( 'wcstripe_account_data_live' ) );
	}

	public function test_no_pending_requirements() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertFalse( $this->account->has_pending_requirements() );
	}

	public function test_has_pending_requirements() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'           => '1234',
			'email'        => 'test@example.com',
			'requirements' => [
				'currently_due' => [ 'example' ],
			],
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertTrue( $this->account->has_pending_requirements() );
	}

	public function test_has_no_overdue_requirements() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'           => '1234',
			'email'        => 'test@example.com',
			'requirements' => [
				'currently_due' => [ 'example' ],
			],
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertFalse( $this->account->has_overdue_requirements() );
	}

	public function test_has_overdue_requirements() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'           => '1234',
			'email'        => 'test@example.com',
			'requirements' => [
				'past_due' => [ 'example' ],
			],
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertTrue( $this->account->has_overdue_requirements() );
	}

	public function test_account_status_complete() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertEquals( 'complete', $this->account->get_account_status() );
	}

	public function test_account_status_restricted() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'           => '1234',
			'email'        => 'test@example.com',
			'requirements' => [
				'disabled_reason' => 'other',
			],
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertEquals( 'restricted', $this->account->get_account_status() );
	}

	public function test_account_status_restricted_soon() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'           => '1234',
			'email'        => 'test@example.com',
			'requirements' => [
				'eventually_due' => [ 'example' ],
			],
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertEquals( 'restricted_soon', $this->account->get_account_status() );
	}

	/**
	 * Test for `get_account_country` method.
	 *
	 * @return void
	 */
	public function test_get_account_country() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'      => '1234',
			'email'   => 'test@example.com',
			'country' => 'US',
		];
		set_transient( 'wcstripe_account_data_test', $account );
		$this->assertEquals( 'US', $this->account->get_account_country() );
	}

	/**
	 * Test for get_cached_account_data() with test mode parameter.
	 */
	public function test_get_cached_account_data_test_mode() {
		$this->mock_connect->method( 'is_connected' )->with( 'test' )->willReturn( true );

		// Test mode account data.
		$account = [
			'id'      => 'acct_1234',
			'email'   => 'test@example.com',
			'country' => 'US',
		];
		set_transient( 'wcstripe_account_data_test', $account );

		$this->assertSame( $this->account->get_cached_account_data( 'test' ), $account );
	}

	/**
	 * Test for get_cached_account_data() with live mode parameter.
	 */
	public function test_get_cached_account_data_live_mode() {
		$this->mock_connect->method( 'is_connected' )->with( 'live' )->willReturn( true );

		// Live mode account data.
		$account = [
			'id'      => 'acct_1234',
			'email'   => 'live@example.com',
			'country' => 'US',
		];
		set_transient( 'wcstripe_account_data_live', $account );

		$this->assertSame( $this->account->get_cached_account_data( 'live' ), $account );
	}

	/**
	 * Test for get_cached_account_data() with no mode parameter.
	 */
	public function test_get_cached_account_data_no_mode() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->mock_connect->method( 'is_connected' )->with( null )->willReturn( true );

		$test_account = [
			'id'      => 'acct_test-1234',
			'email'   => 'john@example.com',
		];

		$live_account = [
			'id'      => 'acct_live-1234',
			'email'   => 'john@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $test_account );
		set_transient( 'wcstripe_account_data_live', $live_account );

		// Enable TEST mode.
		$stripe_settings['testmode'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Confirm test mode data is returned.
		$this->assertSame( $this->account->get_cached_account_data(), $test_account );

		// Enable LIVE mode.
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Confirm live mode data is returned.
		$this->assertSame( $this->account->get_cached_account_data(), $live_account );
	}

	/**
	 * Tests for delete_previously_configured_webhooks() with an excluded webhook.
	 */
	public function test_delete_previously_configured_webhooks_with_exclusion() {
		$webhook_url = WC_Stripe_Helper::get_webhook_url();

		// Mock the API retrieve.
		WC_Helper_Stripe_Api::$retrieve_response = (object) [
			'data' => [
				(object) [
					'id' => 'wh_000', // Invalid data - no URL.
				],
				(object) [
					'id'  => 'wh_123',
					'url' => $webhook_url, // Should be deleted.
				],
				(object) [
					'id'  => 'wh_456',
					'url' => $webhook_url, // Should not be deleted - excluded.
				],
				(object) [
					'id'  => 'wh_789',
					'url' => 'https://some-other-site.com', // Should not be deleted - different URL.
				],
				(object) [
					'id'  => 'wh_101112',
					'url' => $webhook_url, // Should be deleted.
				],
				(object) [
					'url' => $webhook_url, // Invalid data - no ID.
				],
				(object) [
					'id'  => 'wh_131415',
					'url' => str_replace( 'https', 'http', $webhook_url ), // Should be deleted - different protocol.
				],
				(object) [
					'id'  => 'wh_161718',
					'url' => $webhook_url . '/', // Should be deleted - trailing slash.
				],
			],
		];

		// Assert that the webhooks are deleted.
		WC_Helper_Stripe_Api::$expected_request_call_params = [
			[ [], 'webhook_endpoints/wh_123', 'DELETE' ],
			[ [], 'webhook_endpoints/wh_101112', 'DELETE' ],
			[ [], 'webhook_endpoints/wh_131415', 'DELETE' ],
			[ [], 'webhook_endpoints/wh_161718', 'DELETE' ],
		];

		$this->account->delete_previously_configured_webhooks( 'wh_456' );

		// Confirm that all expected request call params were called.
		$this->assertEmpty( WC_Helper_Stripe_Api::$expected_request_call_params );
	}

	/**
	 * Tests for delete_previously_configured_webhooks()
	 */
	public function test_delete_previously_configured_webhooks_without_exclusion() {
		$webhook_url = WC_Stripe_Helper::get_webhook_url();

		// Mock the API retrieve.
		WC_Helper_Stripe_Api::$retrieve_response = (object) [
			'data' => [
				(object) [
					'id' => 'wh_000', // Invalid data - no URL.
				],
				(object) [
					'id'  => 'wh_123',
					'url' => $webhook_url, // Should be deleted.
				],
				(object) [
					'id'  => 'wh_456',
					'url' => $webhook_url, // Should be deleted.
				],
				(object) [
					'id'  => 'wh_789',
					'url' => 'https://some-other-site.com', // Should not be deleted - different URL.
				],
				(object) [
					'id'  => 'wh_101112',
					'url' => $webhook_url, // Should be deleted.
				],
				(object) [
					'url' => $webhook_url, // Invalid data - no ID.
				],
			],
		];

		// Assert that the webhooks are deleted.
		WC_Helper_Stripe_Api::$expected_request_call_params = [
			[ [], 'webhook_endpoints/wh_123', 'DELETE' ],
			[ [], 'webhook_endpoints/wh_456', 'DELETE' ],
			[ [], 'webhook_endpoints/wh_101112', 'DELETE' ],
		];

		$this->account->delete_previously_configured_webhooks();

		// Confirm that all expected request call params were called.
		$this->assertEmpty( WC_Helper_Stripe_Api::$expected_request_call_params );
	}

	/**
	 * Tests for is_webhook_enabled().
	 */
	public function test_is_webhook_enabled() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// False if webhook secrets are not set.
		$stripe_settings['testmode']          = 'yes';
		$stripe_settings['test_webhook_data'] = [
			'id'     => 'wh_123_test',
			'secret' => '',
		];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		$this->clear_webhook_status_cache();
		$this->assertFalse( $this->account->is_webhook_enabled() );

		$stripe_settings['test_webhook_data'] = [];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		$this->clear_webhook_status_cache();
		$this->assertFalse( $this->account->is_webhook_enabled() );

		unset( $stripe_settings['test_webhook_data'] );
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		$this->clear_webhook_status_cache();
		$this->assertFalse( $this->account->is_webhook_enabled() );

		$stripe_settings['testmode']          = 'yes';
		$stripe_settings['webhook_data']      = [
			'id'     => 'wh_123',
			'secret' => 'wh_secret_123',
		];
		$stripe_settings['test_webhook_data'] = [
			'id'     => 'wh_123_test',
			'secret' => 'wh_secret_123_test',
		];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Helper_Stripe_Api::$expected_request_call_params = [
			[ [], 'webhook_endpoints/wh_123_test', 'GET' ],
			[ [], 'webhook_endpoints/wh_123_test', 'GET' ],
		];

		// Assert that it correctly reads the webhook status field.
		WC_Helper_Stripe_Api::$request_response = (object) [
			'id'     => 'wh_123_test',
			'status' => 'disabled',
		];
		$this->clear_webhook_status_cache();
		$this->assertFalse( $this->account->is_webhook_enabled() );

		WC_Helper_Stripe_Api::$request_response = (object) [
			'id'     => 'wh_123_test',
			'status' => 'enabled',
		];
		$this->clear_webhook_status_cache();
		$this->assertTrue( $this->account->is_webhook_enabled() );

		// Assert that it queries the correct webhook (live).
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		WC_Helper_Stripe_Api::$expected_request_call_params = [
			[ [], 'webhook_endpoints/wh_123', 'GET' ],
		];
		WC_Helper_Stripe_Api::$request_response = (object) [
			'id'     => 'wh_123_test',
			'status' => 'enabled',
		];
		$this->clear_webhook_status_cache();
		$this->assertTrue( $this->account->is_webhook_enabled() );

		// Assert that it uses the cached status.
		$this->assertTrue( $this->account->is_webhook_enabled() );
	}

	private function clear_webhook_status_cache() {
		delete_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION );
		delete_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION );
	}
}
