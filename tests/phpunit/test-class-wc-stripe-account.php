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

		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

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
		delete_option( 'woocommerce_stripe_settings' );

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
	 * Tests for delete_previously_configured_manual_webhooks() with an excluded webhook.
	 */
	public function test_delete_previously_configured_manual_webhooks_with_exclusion() {
		$webhook_url = WC_Stripe_Helper::get_webhook_url();

		// Mock the API retrieve.
		WC_Helper_Stripe_Api::$retrieve_response = [
			'data' => [
				[
					'id' => 'wh_000', // Invalid data - no URL.
				],
				[
					'id'  => 'wh_123',
					'url' => $webhook_url, // Should be deleted.
				],
				[
					'id'  => 'wh_456',
					'url' => $webhook_url, // Should not be deleted - excluded.
				],
				[
					'id'  => 'wh_789',
					'url' => 'https://some-other-site.com', // Should not be deleted - different URL.
				],
				[
					'id'  => 'wh_101112',
					'url' => $webhook_url, // Should be deleted.
				],
				[
					'url' => $webhook_url, // Invalid data - no ID.
				],
			],
		];

		// Assert that the webhooks are deleted.
		WC_Helper_Stripe_Api::$expected_request_call_params = [
			[ [], 'webhook_endpoints/wh_123', 'DELETE' ],
			[ [], 'webhook_endpoints/wh_101112', 'DELETE' ],
		];

		$this->account->delete_previously_configured_manual_webhooks( 'wh_456' );
	}

	/**
	 * Tests for delete_previously_configured_manual_webhooks()
	 */
	public function test_delete_previously_configured_manual_webhooks_without_exclusion() {
		$webhook_url = WC_Stripe_Helper::get_webhook_url();

		// Mock the API retrieve.
		WC_Helper_Stripe_Api::$retrieve_response = [
			'data' => [
				[
					'id' => 'wh_000', // Invalid data - no URL.
				],
				[
					'id'  => 'wh_123',
					'url' => $webhook_url, // Should be deleted.
				],
				[
					'id'  => 'wh_456',
					'url' => $webhook_url, // Should be deleted.
				],
				[
					'id'  => 'wh_789',
					'url' => 'https://some-other-site.com', // Should not be deleted - different URL.
				],
				[
					'id'  => 'wh_101112',
					'url' => $webhook_url, // Should be deleted.
				],
				[
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

		$this->account->delete_previously_configured_manual_webhooks();
	}
}
