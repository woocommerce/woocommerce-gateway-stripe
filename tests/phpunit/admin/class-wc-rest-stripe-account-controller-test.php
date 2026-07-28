<?php

/**
 * Class WC_REST_Stripe_Account_Controller_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_REST_Stripe_Account_Controller
 *
 * WC_REST_Stripe_Account_Controller unit tests.
 */
class WC_REST_Stripe_Account_Controller_Test extends WP_UnitTestCase {

	/**
	 * The controller under test.
	 *
	 * @var WC_REST_Stripe_Account_Controller
	 */
	private $controller;

	/**
	 * The Stripe account instance backing the controller.
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
								->onlyMethods( [ 'is_connected' ] )
								->getMock();
		$this->mock_connect->method( 'is_connected' )->willReturn( true );

		$this->account = new WC_Stripe_Account( $this->mock_connect, WC_Helper_Stripe_Api::class );

		$mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
							->disableOriginalConstructor()
							->getMock();

		$this->controller = new WC_REST_Stripe_Account_Controller( $mock_gateway, $this->account );
	}

	public function tear_down() {
		WC_Stripe_Database_Cache::delete( WC_Stripe_Account::ACCOUNT_CACHE_KEY );
		WC_Stripe_Helper::delete_main_stripe_settings();

		delete_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION );
		delete_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION );

		WC_Helper_Stripe_Api::reset();

		parent::tear_down();
	}

	public function test_refresh_account_clears_the_cached_webhook_status() {
		set_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION, 'enabled', HOUR_IN_SECONDS );
		set_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION, 'enabled', HOUR_IN_SECONDS );

		$this->controller->refresh_account();

		$this->assertFalse( get_transient( WC_Stripe_Account::LIVE_WEBHOOK_STATUS_OPTION ) );
		$this->assertFalse( get_transient( WC_Stripe_Account::TEST_WEBHOOK_STATUS_OPTION ) );
	}

	public function test_refresh_account_stores_the_freshly_fetched_account_data() {
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, [ 'id' => 'acct_stale' ] );

		$fresh_account                           = [
			'id'    => 'acct_fresh',
			'email' => 'fresh@example.com',
		];
		WC_Helper_Stripe_Api::$retrieve_response = $fresh_account;

		$data = $this->controller->refresh_account()->get_data();

		$this->assertSame( $fresh_account, $data['account'] );
		$this->assertSame( $fresh_account, WC_Stripe_Database_Cache::get( WC_Stripe_Account::ACCOUNT_CACHE_KEY ) );
	}

	public function test_refresh_account_preserves_the_cached_account_data_when_the_fetch_fails() {
		$cached_account = [
			'id'    => 'acct_cached',
			'email' => 'cached@example.com',
		];
		WC_Stripe_Database_Cache::set( WC_Stripe_Account::ACCOUNT_CACHE_KEY, $cached_account );

		// A network error or a Stripe outage surfaces as a WP_Error from retrieve().
		WC_Helper_Stripe_Api::$retrieve_response = new WP_Error( 'stripe_api_outage', 'temporarily unavailable' );

		$data = $this->controller->refresh_account()->get_data();

		$this->assertSame( $cached_account, $data['account'] );
		$this->assertSame( $cached_account, WC_Stripe_Database_Cache::get( WC_Stripe_Account::ACCOUNT_CACHE_KEY ) );
	}

	public function test_refresh_account_discards_the_cached_account_data_when_the_api_key_is_invalid() {
		WC_Stripe_Database_Cache::set(
			WC_Stripe_Account::ACCOUNT_CACHE_KEY,
			[
				'id'    => 'acct_cached',
				'email' => 'cached@example.com',
			]
		);

		// retrieve() returns null on a 401 so the UI can prompt the merchant to reconnect.
		WC_Helper_Stripe_Api::$retrieve_response = null;

		$data = $this->controller->refresh_account()->get_data();

		$this->assertEmpty( $data['account'] );
		$this->assertEmpty( WC_Stripe_Database_Cache::get( WC_Stripe_Account::ACCOUNT_CACHE_KEY ) );
	}
}
