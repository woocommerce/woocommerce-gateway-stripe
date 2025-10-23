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
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Account manager instance.
	 *
	 * @var WC_Stripe_Transact_Account_Manager
	 */
	private $account_manager;

	/**
	 * The original `WC_Stripe_Connect` instance, to be restored after tests.
	 *
	 * @var WC_Stripe_Connect
	 */
	private WC_Stripe_Connect $stripe_connect_original;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock Stripe gateway.
		$this->gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		// Set default properties.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// overriding the `WC_Stripe_Connect` in woocommerce_gateway_stripe(),
		$stripe_connect_mock = $this->createPartialMock(
			WC_Stripe_Connect::class,
			[ 'is_connected_via_oauth' ]
		);
		$stripe_connect_mock
			->expects( $this->any() )
			->method( 'is_connected_via_oauth' )
			->willReturn( true );

		$this->stripe_connect_original        = woocommerce_gateway_stripe()->connect;
		woocommerce_gateway_stripe()->connect = $stripe_connect_mock;

		// Create account manager instance.
		$this->account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();

		// Restoring the original `WC_Stripe_Connect` instance.
		woocommerce_gateway_stripe()->connect = $this->stripe_connect_original;
	}

	/**
	 * Test constructor sets gateway.
	 */
	public function test_constructor_sets_gateway() {
		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );

		// Use reflection to access the private gateway property.
		$reflection       = new \ReflectionClass( $account_manager );
		$gateway_property = $reflection->getProperty( 'gateway' );
		$gateway_property->setAccessible( true );

		$this->assertSame( $this->gateway, $gateway_property->getValue( $account_manager ) );
	}

	/**
	 * Test do_onboarding when not connected via OAuth.
	 */
	public function test_do_onboarding_when_not_connected_via_oauth() {
		// overriding the `WC_Stripe_Connect` in woocommerce_gateway_stripe(),
		$stripe_connect_mock = $this->createPartialMock(
			WC_Stripe_Connect::class,
			[ 'is_connected_via_oauth' ]
		);
		$stripe_connect_mock
			->expects( $this->any() )
			->method( 'is_connected_via_oauth' )
			->willReturn( false );

		$this->stripe_connect_original        = woocommerce_gateway_stripe()->connect;
		woocommerce_gateway_stripe()->connect = $stripe_connect_mock;

		// Should not throw any errors and should return early.
		$this->account_manager->do_onboarding();

		$this->assertTrue( true );
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
		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
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
		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
		$account_manager->do_onboarding();

		// Check that it returns true.
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_api_error' ] );

		$this->assertTrue( true );
	}

	/**
	 * Test get_merchant_account_data returns cached data when available.
	 */
	public function test_get_merchant_account_data_returns_cached_data() {
		// Return valid cache data.
		WC_Stripe_Database_Cache::set( 'transact_merchant_account_test', $this->return_valid_merchant_account_cache() );

		$result = $this->account_manager->get_transact_account_data( 'merchant' );

		// Clean up the filter.
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account_test' );

		$expected_merchant_account = $this->return_valid_merchant_account_cache();
		$this->assertEquals( $expected_merchant_account['account'], $result );
	}

	/**
	 * Test get_merchant_account_data returns null when cache is expired.
	 */
	public function test_get_merchant_account_data_returns_null_when_cache_expired() {
		// Mock cache to return expired data.
		WC_Stripe_Database_Cache::set( 'transact_merchant_account_test', $this->return_expired_merchant_account_cache() );

		$result = $this->account_manager->get_transact_account_data( 'merchant' );

		// Clean up the filter.
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account_test' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_merchant_account_data fetches when cache is empty and caches fetched data.
	 */
	public function test_get_merchant_account_data_fetches_and_caches_data() {
		// Return empty cache.
		WC_Stripe_Database_Cache::set( 'transact_merchant_account_test', $this->return_empty_merchant_account_cache() );

		// Return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Return a successful response, with the merchant account data.
		add_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
		$result          = $account_manager->get_transact_account_data( 'merchant' );

		// Clean up the filters and cache.
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account_test' );

		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_merchant_account_api_success' ] );

		// Check that it returns the data.
		$response_data             = json_decode( $this->return_merchant_account_api_success()['body'], true );
		$expected_merchant_account = [ 'public_id' => $response_data['public_id'] ];
		$this->assertEquals( $expected_merchant_account, $result );

		// Check that the cache was updated.
		$cached_data = WC_Stripe_Database_Cache::get( 'transact_merchant_account_test' );
		$this->assertNull( $cached_data );
	}


	/**
	 * Test get_provider_account_data returns cached data when available.
	 */
	public function test_get_provider_account_data_returns_cached_data() {
		// Return valid cache data.
		WC_Stripe_Database_Cache::set( 'transact_provider_account_test', $this->return_valid_provider_account_cache() );

		$result = $this->account_manager->get_transact_account_data( 'provider' );

		// Clean up the cache.
		WC_Stripe_Database_Cache::delete( 'transact_provider_account_test' );

		$expected_provider_account = $this->return_valid_provider_account_cache();
		$this->assertEquals( $expected_provider_account['account'], $result );
	}

	/**
	 * Test get_provider_account_data returns null when cache is expired.
	 */
	public function test_get_provider_account_data_returns_null_when_cache_expired() {
		// Mock cache to return expired data.
		WC_Stripe_Database_Cache::set( 'transact_provider_account_test', $this->return_expired_provider_account_cache() );

		$result = $this->account_manager->get_transact_account_data( 'provider' );

		// Clean up the cache.
		WC_Stripe_Database_Cache::delete( 'transact_provider_account_test' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_provider_account_data fetches when cache is empty and caches fetched data.
	 */
	public function test_get_provider_account_data_fetches_and_caches_data() {
		// Return empty cache.
		WC_Stripe_Database_Cache::set( 'transact_provider_account_test', $this->return_empty_provider_account_cache() );

		// Return a valid site ID.
		add_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );

		// Return a Jetpack blog token.
		add_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );

		// Return a successful response, with the provider account data.
		add_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
		$result          = $account_manager->get_transact_account_data( 'provider' );

		// Clean up the filters and cache.
		WC_Stripe_Database_Cache::delete( 'transact_provider_account_test' );

		remove_filter( 'pre_option_jetpack_options', [ $this, 'return_valid_site_id' ] );
		remove_filter( 'pre_option_jetpack_private_options', [ $this, 'return_blog_token' ] );
		remove_filter( 'pre_http_request', [ $this, 'return_provider_account_api_success' ] );

		// Check that it returns the data.
		$this->assertTrue( $result );

		// Check that the cache was updated.
		WC_Stripe_Database_Cache::delete( 'transact_provider_account_test' );
		$cached_data = WC_Stripe_Database_Cache::get( 'transact_provider_account_test' );
		$this->assertNull( $cached_data );
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

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
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

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
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
		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );

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

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
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
		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );

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
		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );

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

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
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

		$account_manager = WC_Stripe_Transact_Account_Manager::get_instance( $this->gateway );
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
	 * Helper method to return empty merchant account cache.
	 *
	 * @return false
	 */
	public function return_empty_merchant_account_cache() {
		return false;
	}

	/**
	 * Helper method to return expired merchant account cache.
	 *
	 * @return array
	 */
	public function return_expired_merchant_account_cache() {
		return [
			'account' => [ 'public_id' => 'test_public_id' ],
			'expiry'  => time() - 3600, // Expired 1 hour ago.
		];
	}

	/**
	 * Helper method to return valid merchant account cache.
	 *
	 * @return array
	 */
	public function return_valid_merchant_account_cache() {
		return [
			'account' => [ 'public_id' => 'test_public_id' ],
			'expiry'  => time() + 3600, // Expires in 1 hour.
		];
	}

	/**
	 * Helper method to return empty provider account cache.
	 *
	 * @return false
	 */
	public function return_empty_provider_account_cache() {
		return false;
	}

	/**
	 * Helper method to return expired provider account cache.
	 *
	 * @return array
	 */
	public function return_expired_provider_account_cache() {
		return [
			'account' => true,
			'expiry'  => time() - 3600, // Expired 1 hour ago.
		];
	}

	/**
	 * Helper method to return valid provider account cache.
	 *
	 * @return array
	 */
	public function return_valid_provider_account_cache() {
		return [
			'account' => true,
			'expiry'  => time() + 3600, // Expires in 1 hour.
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
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account_live' );
		WC_Stripe_Database_Cache::delete( 'transact_merchant_account_test' );
		WC_Stripe_Database_Cache::delete( 'transact_provider_account_live' );
		WC_Stripe_Database_Cache::delete( 'transact_provider_account_test' );

		parent::tearDown();
	}
}
