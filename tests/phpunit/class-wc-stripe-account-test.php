<?php

/**
 * Class WC_Stripe_Account_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Account
 *
 * Class WC_Stripe_Account tests.
 */
class WC_Stripe_Account_Test extends WP_UnitTestCase {
	/**
	 * The Stripe account instance.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * @var WC_Stripe_Connect
	 */
	private $mock_connect;

	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		$stripe_settings['publishable_key']      = '';
		$stripe_settings['secret_key']           = '';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_connect = $this->getMockBuilder( WC_Stripe_Connect::class )
								->disableOriginalConstructor()
								->onlyMethods(
									[
										'is_connected',
									]
								)
								->getMock();

		$this->account = new WC_Stripe_Account( $this->mock_connect, WC_Helper_Stripe_Api::class );
	}

	public function tear_down() {
		WC_Stripe_Database_Cache::delete( WC_Stripe_Account::ACCOUNT_CACHE_KEY );
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
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account );

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
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account );

		$this->account->clear_cache();
		$this->assertEquals( [], $this->account->get_cached_account_data() );
	}

	/**
	 * Test for `has_pending_requirements` and `has_overdue_requirements`.
	 *
	 * @param array $account        The account data to set.
	 * @param bool  $pending        Whether pending requirements are expected.
	 * @param bool  $overdue        Whether overdue requirements are expected.
	 * @return void
	 * @dataProvider provide_test_requirements
	 */
	public function test_requirements( array $account, bool $pending, bool $overdue ) {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account );
		$this->assertSame( $pending, $this->account->has_pending_requirements() );
		$this->assertSame( $overdue, $this->account->has_overdue_requirements() );
	}

	/**
	 * Data provider for `test_requirements`.
	 *
	 * @return array
	 */
	public function provide_test_requirements(): array {
		return [
			'no requirements'       => [
				'account' => [
					'id' => '1234',
					'email' => 'test@example.com',
				],
				'pending' => false,
				'overdue' => false,
			],
			'pending requirements'  => [
				'account' => [
					'id'           => '1234',
					'email'        => 'test@example.com',
					'requirements' => [ 'currently_due' => [ 'example' ] ],
				],
				'pending' => true,
				'overdue' => false,
			],
			'overdue requirements'  => [
				'account' => [
					'id'           => '1234',
					'email'        => 'test@example.com',
					'requirements' => [ 'past_due' => [ 'example' ] ],
				],
				'pending' => true,
				'overdue' => true,
			],
		];
	}

	/**
	 * Test for `get_account_status`.
	 *
	 * @param array  $account         The account data to set.
	 * @param string $expected_status The expected status.
	 * @return void
	 * @dataProvider provide_test_account_status
	 */
	public function test_account_status( array $account, string $expected_status ) {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account );
		$this->assertEquals( $expected_status, $this->account->get_account_status() );
	}

	/**
	 * Data provider for `test_account_status`.
	 *
	 * @return array
	 */
	public function provide_test_account_status(): array {
		return [
			'complete'         => [
				'account'         => [
					'id' => '1234',
					'email' => 'test@example.com',
				],
				'expected_status' => 'complete',
			],
			'restricted'       => [
				'account'         => [
					'id'           => '1234',
					'email'        => 'test@example.com',
					'requirements' => [ 'disabled_reason' => 'other' ],
				],
				'expected_status' => 'restricted',
			],
			'restricted_soon'  => [
				'account'         => [
					'id'           => '1234',
					'email'        => 'test@example.com',
					'requirements' => [ 'eventually_due' => [ 'example' ] ],
				],
				'expected_status' => 'restricted_soon',
			],
		];
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
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account );
		$this->assertEquals( 'US', $this->account->get_account_country() );
	}

	/**
	 * Provide test cases for {@see test_get_cached_account_data()}.
	 *
	 * @return array Array of test cases.
	 */
	public function provide_get_cached_account_data_test_cases(): array {
		return [
			'test mode with force_refresh enabled'       => [ 'test', true ],
			'test mode with force_refresh disabled'      => [ 'test', false ],
			'test mode with force_refresh not specified' => [ 'test', null ],
			'live mode with force_refresh enabled'       => [ 'live', true ],
			'live mode with force_refresh disabled'      => [ 'live', false ],
			'live mode with force_refresh not specified' => [ 'live', null ],
		];
	}

	/**
	 * Test for get_cached_account_data() with force refresh parameter.
	 *
	 * @param string $mode             The mode to get the account data for.
	 * @param bool|null $force_refresh Whether to force refresh the account data. Null will use the default behavior.
	 *
	 * @dataProvider provide_get_cached_account_data_test_cases
	 */
	public function test_get_cached_account_data( string $mode, ?bool $force_refresh = null ) {
		$this->mock_connect->method( 'is_connected' )
			->with( $mode )
			->willReturn( true );

		$email_prefix = 'test' === $mode ? 'test' : 'live';

		WC_Stripe_Database_Cache::delete( WC_Stripe_Account::ACCOUNT_CACHE_KEY );

		if ( true === $force_refresh ) {
			$account_data = [
				'id'      => '4321',
				'email'   => "$email_prefix-fetched@example.com",
				'country' => 'US',
			];

			WC_Helper_Stripe_Api::$retrieve_response = $account_data;
		} else {
			$account_data = [
				'id'      => '1234',
				'email'   => "$email_prefix-cached@example.com",
				'country' => 'US',
			];

			WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account_data );
		}

		if ( null === $force_refresh ) {
			$result = $this->account->get_cached_account_data( $mode );
		} else {
			$result = $this->account->get_cached_account_data( $mode, $force_refresh );
		}

		// Assert that the account data is as expected.
		$this->assertSame( $account_data, $result );
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
					'url' => $webhook_url . '&foo=bar', // Should be deleted.
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
					'url' => explode( '?', $webhook_url )[0], // Should be deleted - matching host with empty path.
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
		WC_Helper_Stripe_Api::$request_response             = (object) [
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

	/**
	 * Test webhook reconfiguration on update with no existing webhooks.
	 */
	public function test_reconfigure_webhooks_on_update_no_existing_webhooks() {
		// Mock that no existing webhook is found
		$this->account = $this->getMockBuilder( WC_Stripe_Account::class )
			->setConstructorArgs( [ $this->mock_connect, WC_Helper_Stripe_Api::class ] )
			->onlyMethods( [ 'get_existing_webhook' ] )
			->getMock();
		$this->account->method( 'get_existing_webhook' )->willReturn( false );

		// Set up expectations that no webhook configuration will be attempted
		WC_Helper_Stripe_Api::$expected_request_call_params = [];

		// Run the update
		$this->account->maybe_reconfigure_webhooks_on_update();

		// Verify no webhook configuration was attempted
		$this->assertEmpty(
			WC_Helper_Stripe_Api::$expected_request_call_params,
			'Should not configure webhooks when no existing webhooks found'
		);
	}

	/**
	 * Test webhook reconfiguration on update with existing webhooks that need updating.
	 */
	public function test_reconfigure_webhooks_on_update_with_outdated_webhooks() {
		// Mock an existing webhook with different events
		$outdated_webhook = (object) [
			'id'             => 'we_123',
			'url'            => WC_Stripe_Helper::get_webhook_url(),
			'enabled_events' => [ 'charge.succeeded', 'charge.failed' ],
			'api_version'    => \WC_Stripe_API::STRIPE_API_VERSION,
			'status'         => 'enabled',
		];

		// Setup the account mock
		$this->account = $this->getMockBuilder( WC_Stripe_Account::class )
			->setConstructorArgs( [ $this->mock_connect, WC_Helper_Stripe_Api::class ] )
			->onlyMethods( [ 'get_existing_webhook', 'configure_webhooks' ] )
			->getMock();

		$this->account->method( 'get_existing_webhook' )->willReturn( $outdated_webhook );
		$this->account->expects( $this->once() )
			->method( 'configure_webhooks' )
			->with(
				$this->equalTo( 'test' )
			);

		// Run the update
		$this->account->maybe_reconfigure_webhooks_on_update();
	}

	/**
	 * Test webhook reconfiguration on update with existing webhooks that are up to date.
	 */
	public function test_reconfigure_webhooks_on_update_with_outdated_api_version() {
		// Mock an existing webhook with current events but outdated API version
		$outdated_webhook = (object) [
			'id'             => 'we_123',
			'url'            => WC_Stripe_Helper::get_webhook_url(),
			'enabled_events' => WC_Stripe_Account::WEBHOOK_EVENTS,
			'api_version'    => '2020-01-01',
			'status'         => 'enabled',
		];

		// Setup the account mock
		$this->account = $this->getMockBuilder( WC_Stripe_Account::class )
			->setConstructorArgs( [ $this->mock_connect, WC_Helper_Stripe_Api::class ] )
			->onlyMethods( [ 'get_existing_webhook', 'configure_webhooks' ] )
			->getMock();

		$this->account->method( 'get_existing_webhook' )->willReturn( $outdated_webhook );
		$this->account->expects( $this->once() )
			->method( 'configure_webhooks' )
			->with(
				$this->equalTo( 'test' )
			);

		// Run the update
		$this->account->maybe_reconfigure_webhooks_on_update();
	}

	/**
	 * Tests that webhook reconfiguration is triggered when the agentic flag
	 * causes the desired API version to differ from the existing webhook.
	 */
	public function test_reconfigure_webhooks_on_update_with_agentic_flag_enabled() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		try {
			// Mock a webhook that has current events but the non-agentic API version.
			$outdated_webhook = (object) [
				'id'             => 'we_123',
				'url'            => WC_Stripe_Helper::get_webhook_url(),
				'enabled_events' => WC_Stripe_Account::WEBHOOK_EVENTS,
				'api_version'    => \WC_Stripe_API::STRIPE_API_VERSION,
				'status'         => 'enabled',
			];

			$this->account = $this->getMockBuilder( WC_Stripe_Account::class )
				->setConstructorArgs( [ $this->mock_connect, WC_Helper_Stripe_Api::class ] )
				->onlyMethods( [ 'get_existing_webhook', 'configure_webhooks' ] )
				->getMock();

			$this->account->method( 'get_existing_webhook' )->willReturn( $outdated_webhook );
			$this->account->expects( $this->once() )
				->method( 'configure_webhooks' )
				->with( $this->equalTo( 'test' ) );

			$this->account->maybe_reconfigure_webhooks_on_update();
		} finally {
			remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		}
	}

	/**
	 * Test webhook reconfiguration on update with existing webhooks that are up to date.
	 */
	public function test_reconfigure_webhooks_on_update_with_current_webhooks() {
		// Mock an existing webhook with current events and API version
		$current_webhook = (object) [
			'id'             => 'we_123',
			'url'            => WC_Stripe_Helper::get_webhook_url(),
			'enabled_events' => WC_Stripe_Account::WEBHOOK_EVENTS,
			'api_version'    => \WC_Stripe_API::STRIPE_API_VERSION,
			'status'         => 'enabled',
		];

		// Setup the account mock
		$this->account = $this->getMockBuilder( WC_Stripe_Account::class )
			->setConstructorArgs( [ $this->mock_connect, WC_Helper_Stripe_Api::class ] )
			->onlyMethods( [ 'get_existing_webhook', 'configure_webhooks' ] )
			->getMock();

		$this->account->method( 'get_existing_webhook' )->willReturn( $current_webhook );
		$this->account->expects( $this->never() )
			->method( 'configure_webhooks' );

		// Run the update
		$this->account->maybe_reconfigure_webhooks_on_update();
	}
}
