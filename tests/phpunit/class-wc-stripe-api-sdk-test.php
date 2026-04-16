<?php
/**
 * Tests for the Stripe SDK integration in WC_Stripe_API.
 *
 * @package WooCommerce\Tests
 */
class WC_Stripe_API_SDK_Test extends WP_UnitTestCase {

	/**
	 * Original SDK instance to restore after each test.
	 *
	 * @var \Stripe\StripeClient|null
	 */
	private $original_sdk = null;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Set a test secret key so SDK methods don't fail.
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'         => 'yes',
				'testmode'        => 'yes',
				'test_secret_key' => 'sk_test_mock_key',
			]
		);
		WC_Stripe_API::set_secret_key( 'sk_test_mock_key' );

		// Ensure backoff counter from other test suites doesn't leak into these tests.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Clear any injected mock SDK and reset backoff state.
		WC_Stripe_API::set_sdk_for_testing( null );
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		parent::tear_down();
	}

	/**
	 * Tests that get_sdk() returns a StripeClient instance.
	 */
	public function test_get_sdk_returns_stripe_client(): void {
		$sdk = WC_Stripe_API::get_sdk();
		$this->assertInstanceOf( \Stripe\StripeClient::class, $sdk );
	}

	/**
	 * Tests that get_sdk() returns the same instance on repeated calls.
	 */
	public function test_get_sdk_returns_cached_instance(): void {
		$sdk1 = WC_Stripe_API::get_sdk();
		$sdk2 = WC_Stripe_API::get_sdk();
		$this->assertSame( $sdk1, $sdk2 );
	}

	/**
	 * Tests that get_sdk() creates a new instance when the secret key changes.
	 */
	public function test_get_sdk_invalidates_on_key_change(): void {
		$sdk1 = WC_Stripe_API::get_sdk();

		WC_Stripe_API::set_secret_key( 'sk_test_different_key' );
		$sdk2 = WC_Stripe_API::get_sdk();

		$this->assertNotSame( $sdk1, $sdk2 );
	}

	/**
	 * Tests that set_sdk_for_testing() injects a mock client.
	 */
	public function test_set_sdk_for_testing_injects_client(): void {
		$mock = $this->createMock( \Stripe\StripeClient::class );
		WC_Stripe_API::set_sdk_for_testing( $mock );

		$this->assertSame( $mock, WC_Stripe_API::get_sdk() );
	}

	/**
	 * Tests that create_checkout_session returns a Session DTO.
	 */
	public function test_create_checkout_session_success(): void {
		$expected = WC_Stripe_SDK_Test_Helper::create_checkout_session_object(
			[
				'id'            => 'cs_test_create_success',
				'client_secret' => 'cs_secret_test_create_success',
			]
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'create_response' => $expected ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$result = WC_Stripe_API::create_checkout_session(
			[
				'mode'       => 'payment',
				'ui_mode'    => 'custom',
				'line_items' => [
					[
						'price_data' => [
							'currency'     => 'usd',
							'product_data' => [ 'name' => 'Test' ],
							'unit_amount'  => 1000,
						],
						'quantity'   => 1,
					],
				],
			]
		);

		$this->assertInstanceOf( \Stripe\Checkout\Session::class, $result );
		$this->assertSame( 'cs_test_create_success', $result->id );
		$this->assertSame( 'cs_secret_test_create_success', $result->client_secret );
	}

	/**
	 * Tests that create_checkout_session throws WC_Stripe_Exception on API error.
	 */
	public function test_create_checkout_session_api_error(): void {
		$api_error = \Stripe\Exception\InvalidRequestException::factory(
			'Invalid params',
			400,
			null,
			null,
			null,
			'invalid_request_error'
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'create_exception' => $api_error ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Invalid params' );

		WC_Stripe_API::create_checkout_session( [ 'mode' => 'payment' ] );
	}

	/**
	 * Tests that retrieve_checkout_session returns a Session DTO.
	 */
	public function test_retrieve_checkout_session_success(): void {
		$expected = WC_Stripe_SDK_Test_Helper::create_checkout_session_object(
			[
				'id'     => 'cs_test_retrieve_success',
				'status' => 'complete',
			]
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'retrieve_response' => $expected ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$result = WC_Stripe_API::retrieve_checkout_session( 'cs_test_retrieve_success' );

		$this->assertInstanceOf( \Stripe\Checkout\Session::class, $result );
		$this->assertSame( 'cs_test_retrieve_success', $result->id );
		$this->assertSame( 'complete', $result->status );
	}

	/**
	 * Tests that retrieve_checkout_session throws WC_Stripe_Exception on API error.
	 */
	public function test_retrieve_checkout_session_api_error(): void {
		$api_error = \Stripe\Exception\InvalidRequestException::factory(
			'No such checkout session',
			404,
			null,
			null,
			null,
			'resource_missing'
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'retrieve_exception' => $api_error ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'No such checkout session' );

		WC_Stripe_API::retrieve_checkout_session( 'cs_test_nonexistent' );
	}

	/**
	 * Tests that update_checkout_session returns a Session DTO.
	 */
	public function test_update_checkout_session_success(): void {
		$expected = WC_Stripe_SDK_Test_Helper::create_checkout_session_object(
			[ 'id' => 'cs_test_update_success' ]
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'update_response' => $expected ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$result = WC_Stripe_API::update_checkout_session(
			'cs_test_update_success',
			[ 'metadata' => [ 'order_id' => '123' ] ]
		);

		$this->assertInstanceOf( \Stripe\Checkout\Session::class, $result );
		$this->assertSame( 'cs_test_update_success', $result->id );
	}

	/**
	 * Tests that update_checkout_session throws WC_Stripe_Exception on API error.
	 */
	public function test_update_checkout_session_api_error(): void {
		$api_error = \Stripe\Exception\InvalidRequestException::factory(
			'Session already expired',
			400,
			null,
			null,
			null,
			'invalid_request_error'
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'update_exception' => $api_error ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Session already expired' );

		WC_Stripe_API::update_checkout_session(
			'cs_test_expired',
			[ 'metadata' => [ 'order_id' => '456' ] ]
		);
	}

	/**
	 * The wc_stripe_request_body filter runs on create_checkout_session.
	 */
	public function test_create_checkout_session_applies_request_body_filter(): void {
		$captured_params = null;
		$session_service = $this->getMockBuilder( \Stripe\Service\Checkout\SessionService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create' ] )
			->getMock();
		$session_service->method( 'create' )->willReturnCallback(
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			function ( $params, $opts ) use ( &$captured_params ) {
				$captured_params = $params;
				return WC_Stripe_SDK_Test_Helper::create_checkout_session_object( [ 'id' => 'cs_test_filter' ] );
			}
		);
		$checkout           = new stdClass();
		$checkout->sessions = $session_service;
		$mock_sdk           = $this->getMockBuilder( \Stripe\StripeClient::class )->disableOriginalConstructor()->getMock();
		$mock_sdk->checkout = $checkout;
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$callback = function ( $body, $api ) {
			$body['metadata']['injected_by_filter'] = 'yes';
			return $body;
		};
		add_filter( 'wc_stripe_request_body', $callback, 10, 2 );
		try {
			WC_Stripe_API::create_checkout_session(
				[
					'mode'     => 'payment',
					'metadata' => [],
				]
			);
		} finally {
			remove_filter( 'wc_stripe_request_body', $callback, 10 );
		}

		$this->assertSame( 'yes', $captured_params['metadata']['injected_by_filter'] ?? null );
	}

	/**
	 * retrieve_checkout_session throws immediately when the backoff threshold is reached.
	 */
	public function test_retrieve_checkout_session_throws_when_backoff_active(): void {
		WC_Stripe_Database_Cache::set(
			WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY,
			10,
			HOUR_IN_SECONDS
		);

		$this->expectException( WC_Stripe_Exception::class );
		try {
			WC_Stripe_API::retrieve_checkout_session( 'cs_should_not_be_called' );
		} finally {
			WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		}
	}

	/**
	 * A successful retrieve clears any lingering invalid-API-key counter.
	 */
	public function test_retrieve_checkout_session_clears_backoff_on_success(): void {
		WC_Stripe_Database_Cache::set(
			WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY,
			2,
			HOUR_IN_SECONDS
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'retrieve_response' => WC_Stripe_SDK_Test_Helper::create_checkout_session_object( [ 'id' => 'cs_ok' ] ) ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		WC_Stripe_API::retrieve_checkout_session( 'cs_ok' );

		$this->assertNull(
			WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY )
		);
	}

	/**
	 * A 401 from retrieve increments the invalid-API-key counter.
	 */
	public function test_retrieve_checkout_session_increments_counter_on_auth_error(): void {
		$auth_error = \Stripe\Exception\AuthenticationException::factory(
			'Invalid API Key',
			401,
			null,
			null,
			null,
			'invalid_api_key'
		);
		$mock_sdk   = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'retrieve_exception' => $auth_error ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		try {
			WC_Stripe_API::retrieve_checkout_session( 'cs_test_bad_key' );
			$this->fail( 'Expected WC_Stripe_Exception' );
		} catch ( WC_Stripe_Exception $e ) {
			$this->assertSame(
				1,
				(int) WC_Stripe_Database_Cache::get( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY )
			);
		}
	}
}
