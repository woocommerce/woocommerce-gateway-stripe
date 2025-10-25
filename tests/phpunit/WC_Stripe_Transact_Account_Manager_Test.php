<?php
/**
 * Class WC_Stripe_Transact_Account_Manager_Test file.
 *
 * @package WooCommerce_Stripe/Tests
 */

declare(strict_types=1);

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Connect;
use WC_Stripe_Database_Cache;
use WC_Stripe_Helper;
use WC_Stripe_UPE_Payment_Gateway;
use WC_Stripe_Transact_Account_Manager;
use WP_UnitTestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Transact_Account_Manager_Test class.
 *
 * @package WooCommerce_Stripe/Tests
 */
class WC_Stripe_Transact_Account_Manager_Test extends WP_UnitTestCase {
	/**
	 * Mock Stripe gateway.
	 *
	 * @var \PHPUnit\Framework\MockObject\MockObject|WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Account manager instance.
	 *
	 * @var WC_Stripe_Transact_Account_Manager
	 */
	private $account_manager;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock Stripe gateway.
		$this->gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_jetpack_connection_manager', 'set_transact_onboarding_complete' ] )
			->getMock();

		// Set default properties.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Create account manager instance.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		/** @var WC_Stripe_UPE_Payment_Gateway $gateway */
		$gateway               = $this->gateway;
		$this->account_manager = new WC_Stripe_Transact_Account_Manager( $gateway );
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Test constructor sets gateway.
	 */
	public function test_constructor_sets_gateway() {
		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );

		// Use reflection to access the private gateway property.
		$reflection       = new \ReflectionClass( $account_manager );
		$gateway_property = $reflection->getProperty( 'gateway' );
		$gateway_property->setAccessible( true );

		$this->assertSame( $this->gateway, $gateway_property->getValue( $account_manager ) );
	}

	/**
	 * Test do_onboarding when Jetpack registration fails.
	 */
	public function test_do_onboarding_when_jetpack_registration_fails() {
		// Mock the gateway to return a mock Jetpack connection manager.
		$jetpack_manager = $this->getMockBuilder( \Automattic\Jetpack\Connection\Manager::class )
			->onlyMethods( [ 'is_connected', 'try_registration' ] )
			->getMock();

		$jetpack_manager->method( 'is_connected' )
			->willReturn( false );

		$jetpack_manager->method( 'try_registration' )
			->willReturn( new \WP_Error( 'registration_failed', 'Registration failed' ) );

		$this->gateway->method( 'get_jetpack_connection_manager' )
			->willReturn( $jetpack_manager );

		// Should not throw any errors and should return early.
		$this->account_manager->do_onboarding();

		$this->assertTrue( true );
	}

	/**
	 * Test do_onboarding when merchant account creation fails.
	 */
	public function test_do_onboarding_when_merchant_account_creation_fails() {
		// Mock the gateway to return a mock Jetpack connection manager.
		$jetpack_manager = $this->getMockBuilder( \Automattic\Jetpack\Connection\Manager::class )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$jetpack_manager->method( 'is_connected' )
			->willReturn( true );

		$this->gateway->method( 'get_jetpack_connection_manager' )
			->willReturn( $jetpack_manager );

		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return an error.
		add_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		// Should do nothing. Should not throw any errors.
		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$account_manager->do_onboarding();

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$this->assertTrue( true );
	}

	/**
	 * Test do_onboarding when provider account creation fails.
	 */
	public function test_do_onboarding_when_provider_account_creation_fails() {
		// Mock the gateway to return a mock Jetpack connection manager.
		$jetpack_manager = $this->getMockBuilder( \Automattic\Jetpack\Connection\Manager::class )
			->onlyMethods( [ 'is_connected' ] )
			->getMock();

		$jetpack_manager->method( 'is_connected' )
			->willReturn( true );

		$this->gateway->method( 'get_jetpack_connection_manager' )
			->willReturn( $jetpack_manager );

		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return an error.
		add_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		// Should do nothing. Should not throw any errors.
		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$account_manager->do_onboarding();

		// Check that it returns true.
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$this->assertTrue( true );
	}

	/**
	 * Test maybe_create_merchant_account returns cached data when available.
	 */
	public function test_maybe_create_merchant_account_returns_cached_data() {
		// Set valid cache data.
		WC_Stripe_Database_Cache::set( 'transact_merchant_account', [ 'public_id' => 'test_public_id' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'maybe_create_merchant_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the cache.
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account' );

		$expected_merchant_account = [ 'public_id' => 'test_public_id' ];
		$this->assertEquals( $expected_merchant_account, $result );
	}

	/**
	 * Test maybe_create_merchant_account fetches and caches when cache is empty.
	 */
	public function test_maybe_create_merchant_account_fetches_when_cache_empty() {
		// Don't set any cache, so it will fetch from API.

		// Return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Return a successful response, with the merchant account data.
		add_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'maybe_create_merchant_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters and cache.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account' );

		// Check that it returns the data and caches it.
		$expected_merchant_account = [ 'public_id' => 'test_public_id' ];
		$this->assertEquals( $expected_merchant_account, $result );
	}

	/**
	 * Test maybe_create_merchant_account creates account when fetch fails.
	 */
	public function test_maybe_create_merchant_account_creates_when_fetch_fails() {
		// Don't set any cache, so it will try to fetch from API.

		// Return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to first return 404 (not found), then return success on create.
		$this->http_request_count = 0;
		add_filter( 'pre_http_request', [ $this, 'return_fetch_fail_then_create_success_merchant' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'maybe_create_merchant_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters and cache.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_fetch_fail_then_create_success_merchant' ] );
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account' );

		// Check that it returns the created account data.
		$expected_merchant_account = [ 'public_id' => 'test_public_id' ];
		$this->assertEquals( $expected_merchant_account, $result );
	}


	/**
	 * Test maybe_create_provider_account returns cached data when available.
	 */
	public function test_maybe_create_provider_account_returns_cached_data() {
		// Set valid cache data.
		WC_Stripe_Database_Cache::set( 'transact_provider_account', true );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'maybe_create_provider_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the cache.
		WC_Stripe_Database_Cache::delete( 'transact_provider_account' );

		$this->assertTrue( $result );
	}

	/**
	 * Test maybe_create_provider_account fetches and caches when cache is empty.
	 */
	public function test_maybe_create_provider_account_fetches_when_cache_empty() {
		// Don't set any cache, so it will fetch from API.

		// Return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Return a successful response for the provider account.
		add_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'maybe_create_provider_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters and cache.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );
		WC_Stripe_Database_Cache::delete( 'transact_provider_account' );

		// Check that it returns true.
		$this->assertTrue( $result );
	}

	/**
	 * Test maybe_create_provider_account creates account when fetch fails.
	 */
	public function test_maybe_create_provider_account_creates_when_fetch_fails() {
		// Don't set any cache, so it will try to fetch from API.

		// Return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to first return 404 (not found), then return success on create.
		$this->http_request_count = 0;
		add_filter( 'pre_http_request', [ $this, 'return_fetch_fail_then_create_success_provider' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'maybe_create_provider_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters and cache.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_fetch_fail_then_create_success_provider' ] );
		WC_Stripe_Database_Cache::delete( 'transact_provider_account' );

		// Check that it returns true.
		$this->assertTrue( $result );
	}

	/**
	 * Test fetch_merchant_account when API request fails.
	 */
	public function test_fetch_merchant_account_when_api_request_fails() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return an error.
		add_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'fetch_merchant_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$this->assertNull( $result );
	}

	/**
	 * Test fetch_merchant_account when API response is successful.
	 */
	public function test_fetch_merchant_account_when_api_response_successful() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return a successful response.
		add_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'fetch_merchant_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		$this->assertEquals( [ 'public_id' => 'test_public_id' ], $result );
	}

	/**
	 * Test fetch_provider_account when API request fails.
	 */
	public function test_fetch_provider_account_when_api_request_fails() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return an error.
		add_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		// Create a real account manager instance.
		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );

		// Use reflection to access the private fetch_provider_account method.
		$reflection = new \ReflectionClass( $account_manager );
		$method     = $reflection->getMethod( 'fetch_provider_account' );
		$method->setAccessible( true );
		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$this->assertFalse( $result );
	}

	/**
	 * Test fetch_provider_account when API response is successful.
	 */
	public function test_fetch_provider_account_when_api_response_successful() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return a successful response.
		add_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'fetch_provider_account' );
		$method->setAccessible( true );
		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		// Check that it returns true.
		$this->assertTrue( $result );
	}

	/**
	 * Test create_merchant_account when API request fails.
	 */
	public function test_create_merchant_account_when_api_request_fails() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return an error.
		add_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		// Create a real account manager instance.
		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );

		// Use reflection to access the private create_merchant_account method.
		$reflection    = new \ReflectionClass( $account_manager );
		$create_method = $reflection->getMethod( 'create_merchant_account' );
		$create_method->setAccessible( true );

		$result = $create_method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		// The method should return null when API fails.
		$this->assertNull( $result );
	}

	/**
	 * Test create_merchant_account when API response is successful.
	 */
	public function test_create_merchant_account_when_api_response_successful() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return a successful response.
		add_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		// Create a real account manager instance.
		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );

		// Use reflection to access the private create_merchant_account method.
		$reflection = new \ReflectionClass( $account_manager );
		$method     = $reflection->getMethod( 'create_merchant_account' );
		$method->setAccessible( true );

		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		// Check that it returns the data.
		$this->assertEquals( [ 'public_id' => 'test_public_id' ], $result );
	}

	/**
	 * Test create_provider_account when API request fails.
	 */
	public function test_create_provider_account_when_api_request_fails() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return an error.
		add_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'create_provider_account' );
		$method->setAccessible( true );
		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Check that it returns false.
		$this->assertFalse( $result );
	}

	/**
	 * Test create_provider_account when API response is successful.
	 */
	public function test_create_provider_account_when_api_response_successful() {
		// Mock Jetpack options to return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Mock the HTTP request to return a successful response.
		add_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		$account_manager = new WC_Stripe_Transact_Account_Manager( $this->gateway );
		$reflection      = new \ReflectionClass( $account_manager );
		$method          = $reflection->getMethod( 'create_provider_account' );
		$method->setAccessible( true );
		$result = $method->invoke( $account_manager );

		// Clean up the filters.
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		// Check that it returns true.
		$this->assertTrue( $result );
	}

	/**
	 * Helper method to return API error response.
	 *
	 * @return \WP_Error Error response.
	 */
	public function return_api_error() {
		return new \WP_Error( 'api_error', 'API request failed' );
	}

	/**
	 * Helper method to return successful merchant account API response.
	 *
	 * @return array Success response.
	 */
	public function return_merchant_account_api_success() {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode(
				[
					'public_id' => 'test_public_id',
				]
			),
		];
	}

	/**
	 * Helper method to return successful provider account API response.
	 *
	 * @return array Success response.
	 */
	public function return_provider_account_api_success() {
		return [ 'response' => [ 'code' => 200 ] ];
	}

	/**
	 * Helper method to return null site ID for Jetpack options.
	 *
	 * @param mixed $value The option value.
	 *
	 * @return null
	 */
	public function return_null_site_id( $value ) {
		return [ 'id' => null ];
	}

	/**
	 * Helper method to return valid site ID for Jetpack options.
	 *
	 * @param mixed $value The option value.
	 *
	 * @return int
	 */
	public function return_valid_site_id( $value ) {
		return [ 'id' => 12345 ];
	}

	/**
	 * Helper property to track HTTP request count for testing.
	 *
	 * @var int
	 */
	private $http_request_count = 0;

	/**
	 * Helper method to simulate fetch failure then create success for merchant account.
	 * First request (GET - fetch) returns 404, second request (POST - create) returns success.
	 *
	 * @return array|\WP_Error
	 */
	public function return_fetch_fail_then_create_success_merchant() {
		$this->http_request_count++;

		// First call is the fetch (GET), return 404.
		if ( 1 === $this->http_request_count ) {
			return [
				'response' => [ 'code' => 404 ],
				'body'     => wp_json_encode( [ 'error' => 'not_found' ] ),
			];
		}

		// Second call is the create (POST), return success.
		return [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( [ 'public_id' => 'test_public_id' ] ),
		];
	}

	/**
	 * Helper method to simulate fetch failure then create success for provider account.
	 * First request (GET - fetch) returns 404, second request (POST - create) returns success.
	 *
	 * @return array|\WP_Error
	 */
	public function return_fetch_fail_then_create_success_provider() {
		$this->http_request_count++;

		// First call is the fetch (GET), return 404.
		if ( 1 === $this->http_request_count ) {
			return [
				'response' => [ 'code' => 404 ],
				'body'     => wp_json_encode( [ 'error' => 'not_found' ] ),
			];
		}

		// Second call is the create (POST), return success.
		return [
			'response' => [ 'code' => 200 ],
			'body'     => '',
		];
	}

	/**
	 * Helper method to return valid blog token for Jetpack options.
	 *
	 * @param mixed $value The option value.
	 *
	 * @return array
	 */
	public function return_blog_token( $value ) {
		return [ 'blog_token' => 'IAM.AJETPACKBLOGTOKEN' ];
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up any options we created.
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account' );
		WC_Stripe_Database_Cache::delete( 'transact_provider_account' );

		parent::tearDown();
	}
}
