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

		// Store original and set a test secret key so SDK methods don't fail.
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'         => 'yes',
				'testmode'        => 'yes',
				'test_secret_key' => 'sk_test_mock_key',
			]
		);
		WC_Stripe_API::set_secret_key( 'sk_test_mock_key' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Clear any injected mock SDK.
		WC_Stripe_API::set_sdk_for_testing( null );
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

	// =========================================================================
	// Charge SDK methods
	// =========================================================================

	/**
	 * Tests that retrieve_charge returns a Charge DTO.
	 */
	public function test_retrieve_charge_success(): void {
		$expected = WC_Stripe_SDK_Test_Helper::create_charge_object(
			[
				'id'       => 'ch_test_retrieve',
				'captured' => false,
			]
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'charge_retrieve_response' => $expected ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$result = WC_Stripe_API::retrieve_charge( 'ch_test_retrieve' );

		$this->assertInstanceOf( \Stripe\Charge::class, $result );
		$this->assertSame( 'ch_test_retrieve', $result->id );
		$this->assertFalse( $result->captured );
	}

	/**
	 * Tests that retrieve_charge throws WC_Stripe_Exception on API error.
	 */
	public function test_retrieve_charge_api_error(): void {
		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[
				'charge_retrieve_exception' => \Stripe\Exception\InvalidRequestException::factory(
					'No such charge',
					404,
					null,
					null,
					null,
					'resource_missing'
				),
			]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'No such charge' );

		WC_Stripe_API::retrieve_charge( 'ch_nonexistent' );
	}

	/**
	 * Tests that capture_charge returns a Charge DTO.
	 */
	public function test_capture_charge_success(): void {
		$expected = WC_Stripe_SDK_Test_Helper::create_charge_object(
			[
				'id'       => 'ch_test_capture',
				'captured' => true,
			]
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'charge_capture_response' => $expected ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$result = WC_Stripe_API::capture_charge( 'ch_test_capture', [ 'amount' => 1000 ] );

		$this->assertInstanceOf( \Stripe\Charge::class, $result );
		$this->assertSame( 'ch_test_capture', $result->id );
		$this->assertTrue( $result->captured );
	}

	/**
	 * Tests that capture_charge throws WC_Stripe_Exception on API error.
	 */
	public function test_capture_charge_api_error(): void {
		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[
				'charge_capture_exception' => \Stripe\Exception\InvalidRequestException::factory(
					'Charge already captured',
					400
				),
			]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Charge already captured' );

		WC_Stripe_API::capture_charge( 'ch_test_already_captured', [ 'amount' => 1000 ] );
	}

	/**
	 * Tests that create_charge returns a Charge DTO.
	 */
	public function test_create_charge_success(): void {
		$expected = WC_Stripe_SDK_Test_Helper::create_charge_object(
			[ 'id' => 'ch_test_create' ]
		);

		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[ 'charge_create_response' => $expected ]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$result = WC_Stripe_API::create_charge(
			[
				'amount'   => 1000,
				'currency' => 'usd',
				'source'   => 'src_test',
			]
		);

		$this->assertInstanceOf( \Stripe\Charge::class, $result );
		$this->assertSame( 'ch_test_create', $result->id );
	}

	/**
	 * Tests that create_charge throws WC_Stripe_Exception on API error.
	 */
	public function test_create_charge_api_error(): void {
		$mock_sdk = WC_Stripe_SDK_Test_Helper::create_mock_sdk(
			$this,
			[
				'charge_create_exception' => \Stripe\Exception\ApiConnectionException::factory(
					'Connection failed'
				),
			]
		);
		WC_Stripe_API::set_sdk_for_testing( $mock_sdk );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'Connection failed' );

		WC_Stripe_API::create_charge(
			[
				'amount'   => 1000,
				'currency' => 'usd',
			]
		);
	}
}
