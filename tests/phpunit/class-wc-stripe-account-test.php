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
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'test' );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'live' );
		$this->clear_webhook_status_cache();
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

	public function test_get_cached_account_data_ignores_non_array_cache_data() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'invalid' );
		WC_Helper_Stripe_Api::$retrieve_response = new WP_Error( 'stripe_api_outage', 'temporarily unavailable' );

		$this->assertSame( [], $this->account->get_cached_account_data() );
	}

	public function test_clear_cache() {
		$live_account = [
			'id'    => '1234',
			'email' => 'live@example.com',
		];
		$test_account = [
			'id'    => '5678',
			'email' => 'test@example.com',
		];
		WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $live_account, HOUR_IN_SECONDS, 'live' );
		WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $test_account, HOUR_IN_SECONDS, 'test' );

		$this->account->clear_cache();
		$this->assertNull( WC_Stripe_Database_Cache::get_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'live' ) );
		$this->assertNull( WC_Stripe_Database_Cache::get_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, 'test' ) );
	}

	public function test_get_cached_account_data_preserves_cache_on_transient_failure() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account );

		// A transient failure (network error / Stripe outage) makes retrieve() return a WP_Error.
		WC_Helper_Stripe_Api::$retrieve_response = new WP_Error( 'stripe_api_outage', 'temporarily unavailable' );

		// The failed forced fetch returns empty without overwriting the cache, so the next read
		// still serves the previously cached account data.
		$this->assertSame( [], $this->account->get_cached_account_data( null, true ) );
		$this->assertSame( $account, $this->account->get_cached_account_data() );
	}

	public function test_get_cached_account_data_clears_cache_on_invalid_key() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		WC_Stripe_Database_Cache::set(
			WC_Stripe_Account::ACCOUNT_CACHE_KEY,
			[
				'id'    => '1234',
				'email' => 'test@example.com',
			]
		);

		// An invalid API key makes retrieve() return null (Stripe responds with a 401); the stale
		// data must not survive so the UI can surface the "reconnect" prompt.
		WC_Helper_Stripe_Api::$retrieve_response = null;

		$this->assertEmpty( $this->account->get_cached_account_data( null, true ) );
		$this->assertEmpty( $this->account->get_cached_account_data() );
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
			'no requirements'      => [
				'account' => [
					'id'    => '1234',
					'email' => 'test@example.com',
				],
				'pending' => false,
				'overdue' => false,
			],
			'pending requirements' => [
				'account' => [
					'id'           => '1234',
					'email'        => 'test@example.com',
					'requirements' => [ 'currently_due' => [ 'example' ] ],
				],
				'pending' => true,
				'overdue' => false,
			],
			'overdue requirements' => [
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
			'complete'        => [
				'account'         => [
					'id'    => '1234',
					'email' => 'test@example.com',
				],
				'expected_status' => 'complete',
			],
			'restricted'      => [
				'account'         => [
					'id'           => '1234',
					'email'        => 'test@example.com',
					'requirements' => [ 'disabled_reason' => 'other' ],
				],
				'expected_status' => 'restricted',
			],
			'restricted_soon' => [
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
	 * Provide invalid modes and the active mode they should resolve to.
	 *
	 * @return array
	 */
	public function provide_invalid_account_mode_test_cases(): array {
		return [
			'invalid mode in test mode' => [ 'Live', 'yes', 'test' ],
			'invalid mode in live mode' => [ 'invalid', 'no', 'live' ],
		];
	}

	/**
	 * Invalid modes must consistently use the active mode for connection and cache access.
	 *
	 * @param string $mode          The requested mode.
	 * @param string $testmode      The active test mode setting.
	 * @param string $expected_mode The expected resolved mode.
	 *
	 * @dataProvider provide_invalid_account_mode_test_cases
	 */
	public function test_get_cached_account_data_normalizes_invalid_mode( string $mode, string $testmode, string $expected_mode ) {
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = $testmode;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$account_data = [
			'id'    => '1234',
			'email' => "$expected_mode@example.com",
		];
		WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account_data, HOUR_IN_SECONDS, $expected_mode );

		$this->mock_connect->expects( $this->once() )
			->method( 'is_connected' )
			->with( $expected_mode )
			->willReturn( true );

		$this->assertSame( $account_data, $this->account->get_cached_account_data( $mode ) );
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

		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $mode );

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

			WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $account_data, HOUR_IN_SECONDS, $mode );
		}

		if ( null === $force_refresh ) {
			$result = $this->account->get_cached_account_data( $mode );
		} else {
			$result = $this->account->get_cached_account_data( $mode, $force_refresh );
		}

		// Assert that the account data is as expected.
		$this->assertSame( $account_data, $result );
		$this->assertSame( $account_data, WC_Stripe_Database_Cache::get_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $mode ) );
	}

	private function clear_webhook_status_cache() {
		$webhook_status_cache_key = WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY;
		WC_Stripe_Database_Cache::delete_with_mode( $webhook_status_cache_key, 'test' );
		WC_Stripe_Database_Cache::delete_with_mode( $webhook_status_cache_key, 'live' );
	}
}
