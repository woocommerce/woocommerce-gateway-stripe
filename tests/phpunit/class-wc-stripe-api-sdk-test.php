<?php

/**
 * Class WC_Stripe_API_SDK_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_API_SDK
 *
 * Tests for WC_Stripe_API methods that use the Stripe PHP SDK client.
 */
class WC_Stripe_API_SDK_Test extends WP_UnitTestCase {

	/**
	 * Reflection property for the static SDK instance.
	 *
	 * @var ReflectionProperty
	 */
	private $sdk_prop;

	/**
	 * Reflection property for the static SDK secret key tracker.
	 *
	 * @var ReflectionProperty
	 */
	private $sdk_secret_prop;

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		$stripe_settings                    = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']         = 'yes';
		$stripe_settings['testmode']        = 'yes';
		$stripe_settings['test_secret_key'] = 'sk_test_key_sdk_123';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$reflection     = new ReflectionClass( WC_Stripe_API::class );
		$this->sdk_prop = $reflection->getProperty( 'sdk' );
		$this->sdk_prop->setAccessible( true );
		$this->sdk_secret_prop = $reflection->getProperty( 'sdk_secret' );
		$this->sdk_secret_prop->setAccessible( true );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		// Reset the static SDK instance so it does not bleed into other tests.
		$this->sdk_prop->setValue( null, null );
		$this->sdk_secret_prop->setValue( null, '' );
		WC_Stripe_API::set_secret_key( null );
		WC_Stripe_Helper::delete_main_stripe_settings();
		parent::tear_down();
	}

	/**
	 * Inject a mock Stripe client into the static property.
	 *
	 * @param \Stripe\StripeClient $mock_client
	 */
	private function inject_sdk( $mock_client ) {
		$this->sdk_prop->setValue( null, $mock_client );
		// Set a matching secret so the SDK is not rebuilt.
		$this->sdk_secret_prop->setValue( null, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Create a mock StripeObject that supports property access like the real SDK.
	 *
	 * @param array $data Key-value pairs to expose as object properties.
	 * @return \Stripe\StripeObject
	 */
	private function make_stripe_response( array $data ): \Stripe\StripeObject {
		$obj = \Stripe\Util\Util::convertToStripeObject( $data, [] );
		return $obj;
	}

	/**
	 * Create a mock StripeClient that returns the given service for the given service name.
	 *
	 * @param string $service_name
	 * @param object $service
	 * @return \Stripe\StripeClient|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function make_sdk_with_service( string $service_name, $service ) {
		$mock_client = $this->createMock( \Stripe\StripeClient::class );
		$mock_client->method( '__get' )
			->with( $service_name )
			->willReturn( $service );
		return $mock_client;
	}

	// -------------------------------------------------------------------------
	// get_payment_method()
	// -------------------------------------------------------------------------

	/**
	 * Test that get_payment_method() uses sources->retrieve() for source IDs.
	 */
	public function test_get_payment_method_retrieves_source() {
		$source_id = 'src_test123abc';
		$response  = $this->make_stripe_response(
			[
				'id'     => $source_id,
				'object' => 'source',
			]
		);

		$mock_sources = $this->createMock( \Stripe\Service\SourceService::class );
		$mock_sources->expects( $this->once() )
			->method( 'retrieve' )
			->with( $source_id )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'sources', $mock_sources ) );

		$result = WC_Stripe_API::get_payment_method( $source_id );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $source_id, $result->id );
		$this->assertEquals( 'source', $result->object );
	}

	/**
	 * Test that get_payment_method() uses paymentMethods->retrieve() for non-source IDs.
	 */
	public function test_get_payment_method_retrieves_payment_method() {
		$pm_id    = 'pm_test456def';
		$response = $this->make_stripe_response(
			[
				'id'     => $pm_id,
				'object' => 'payment_method',
			]
		);

		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->expects( $this->once() )
			->method( 'retrieve' )
			->with( $pm_id )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$result = WC_Stripe_API::get_payment_method( $pm_id );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $pm_id, $result->id );
		$this->assertEquals( 'payment_method', $result->object );
	}

	/**
	 * Test that get_payment_method() throws WC_Stripe_Exception on API error.
	 */
	public function test_get_payment_method_throws_on_api_error() {
		$pm_id = 'pm_invalid';

		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->method( 'retrieve' )
			->willThrowException(
				\Stripe\Exception\InvalidRequestException::factory(
					'No such payment method: ' . $pm_id,
					404
				)
			);

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$this->expectException( WC_Stripe_Exception::class );
		$this->expectExceptionMessage( 'No such payment method: pm_invalid' );

		WC_Stripe_API::get_payment_method( $pm_id );
	}

	// -------------------------------------------------------------------------
	// update_payment_method()
	// -------------------------------------------------------------------------

	/**
	 * Test that update_payment_method() uses paymentMethods->update() with correct args.
	 */
	public function test_update_payment_method_calls_sdk() {
		$pm_id    = 'pm_test789ghi';
		$pm_data  = [ 'metadata' => [ 'order_id' => '42' ] ];
		$response = $this->make_stripe_response(
			[
				'id'       => $pm_id,
				'object'   => 'payment_method',
				'metadata' => [ 'order_id' => '42' ],
			]
		);

		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->expects( $this->once() )
			->method( 'update' )
			->with( $pm_id, $pm_data )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$result = WC_Stripe_API::update_payment_method( $pm_id, $pm_data );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $pm_id, $result->id );
	}

	/**
	 * Test that update_payment_method() throws WC_Stripe_Exception on API error.
	 */
	public function test_update_payment_method_throws_on_api_error() {
		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->method( 'update' )
			->willThrowException(
				\Stripe\Exception\InvalidRequestException::factory(
					'No such payment method: pm_bad',
					404
				)
			);

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$this->expectException( WC_Stripe_Exception::class );

		WC_Stripe_API::update_payment_method( 'pm_bad', [] );
	}

	// -------------------------------------------------------------------------
	// attach_payment_method_to_customer()
	// -------------------------------------------------------------------------

	/**
	 * Test that attach_payment_method_to_customer() uses customers->createSource() for source IDs.
	 */
	public function test_attach_payment_method_to_customer_attaches_source() {
		$customer_id = 'cus_test123';
		$source_id   = 'src_testABC';
		$response    = $this->make_stripe_response(
			[
				'id'       => $source_id,
				'object'   => 'card',
				'customer' => $customer_id,
			]
		);

		$mock_customers = $this->createMock( \Stripe\Service\CustomerService::class );
		$mock_customers->expects( $this->once() )
			->method( 'createSource' )
			->with( $customer_id, [ 'source' => $source_id ] )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'customers', $mock_customers ) );

		$result = WC_Stripe_API::attach_payment_method_to_customer( $customer_id, $source_id );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $source_id, $result->id );
		$this->assertEquals( $customer_id, $result->customer );
	}

	/**
	 * Test that attach_payment_method_to_customer() uses paymentMethods->attach() for non-source IDs.
	 */
	public function test_attach_payment_method_to_customer_attaches_payment_method() {
		$customer_id = 'cus_test456';
		$pm_id       = 'pm_testDEF';
		$response    = $this->make_stripe_response(
			[
				'id'       => $pm_id,
				'object'   => 'payment_method',
				'customer' => $customer_id,
			]
		);

		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->expects( $this->once() )
			->method( 'attach' )
			->with( $pm_id, [ 'customer' => $customer_id ] )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$result = WC_Stripe_API::attach_payment_method_to_customer( $customer_id, $pm_id );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $pm_id, $result->id );
		$this->assertEquals( $customer_id, $result->customer );
	}

	/**
	 * Test that attach_payment_method_to_customer() throws WC_Stripe_Exception on API error.
	 */
	public function test_attach_payment_method_to_customer_throws_on_api_error() {
		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->method( 'attach' )
			->willThrowException(
				\Stripe\Exception\InvalidRequestException::factory(
					'No such customer: cus_bad',
					404
				)
			);

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$this->expectException( WC_Stripe_Exception::class );

		WC_Stripe_API::attach_payment_method_to_customer( 'cus_bad', 'pm_test' );
	}

	// -------------------------------------------------------------------------
	// detach_payment_method_from_customer()
	// -------------------------------------------------------------------------

	/**
	 * Test that detach_payment_method_from_customer() uses customers->deleteSource() for source IDs.
	 */
	public function test_detach_payment_method_from_customer_detaches_source() {
		$customer_id = 'cus_testXYZ';
		$source_id   = 'src_testXYZ';
		$response    = $this->make_stripe_response(
			[
				'id'      => $source_id,
				'object'  => 'source',
				'deleted' => true,
			]
		);

		$mock_customers = $this->createMock( \Stripe\Service\CustomerService::class );
		$mock_customers->expects( $this->once() )
			->method( 'deleteSource' )
			->with( $customer_id, $source_id )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'customers', $mock_customers ) );

		$result = WC_Stripe_API::detach_payment_method_from_customer( $customer_id, $source_id );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $source_id, $result->id );
	}

	/**
	 * Test that detach_payment_method_from_customer() uses paymentMethods->detach() for non-source IDs.
	 */
	public function test_detach_payment_method_from_customer_detaches_payment_method() {
		$customer_id = 'cus_testMNO';
		$pm_id       = 'pm_testMNO';
		$response    = $this->make_stripe_response(
			[
				'id'       => $pm_id,
				'object'   => 'payment_method',
				'customer' => null,
			]
		);

		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->expects( $this->once() )
			->method( 'detach' )
			->with( $pm_id )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$result = WC_Stripe_API::detach_payment_method_from_customer( $customer_id, $pm_id );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $pm_id, $result->id );
	}

	/**
	 * Test that detach_payment_method_from_customer() returns false when the customer
	 * should not have payment methods detached (e.g. staging site in live mode).
	 */
	public function test_detach_payment_method_from_customer_returns_false_when_not_allowed() {
		// Enable live mode.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Simulate a WooCommerce Subscriptions staging site.
		require_once __DIR__ . '/helpers/class-wcs-staging.php';
		\WCS_Staging::set_is_duplicate_site( true );

		add_filter( 'wp_doing_cron', '__return_true' );

		$result = WC_Stripe_API::detach_payment_method_from_customer( 'cus_test', 'pm_test' );

		remove_filter( 'wp_doing_cron', '__return_true' );
		\WCS_Staging::set_is_duplicate_site( false );

		$this->assertEmpty( $result );
	}

	/**
	 * Test that detach_payment_method_from_customer() throws WC_Stripe_Exception on API error.
	 */
	public function test_detach_payment_method_from_customer_throws_on_api_error() {
		$mock_payment_methods = $this->createMock( \Stripe\Service\PaymentMethodService::class );
		$mock_payment_methods->method( 'detach' )
			->willThrowException(
				\Stripe\Exception\InvalidRequestException::factory(
					'No such payment method: pm_bad',
					404
				)
			);

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethods', $mock_payment_methods ) );

		$this->expectException( WC_Stripe_Exception::class );

		WC_Stripe_API::detach_payment_method_from_customer( 'cus_test', 'pm_bad' );
	}

	// -------------------------------------------------------------------------
	// get_payment_method_configurations()
	// -------------------------------------------------------------------------

	/**
	 * Test that get_payment_method_configurations() uses paymentMethodConfigurations->all() with limit=100.
	 */
	public function test_get_payment_method_configurations_calls_sdk() {
		$response = $this->make_stripe_response(
			[
				'object' => 'list',
				'data'   => [
					[
						'id'     => 'pmc_test123',
						'object' => 'payment_method_configuration',
					],
				],
			]
		);

		$mock_pmc_service = $this->createMock( \Stripe\Service\PaymentMethodConfigurationService::class );
		$mock_pmc_service->expects( $this->once() )
			->method( 'all' )
			->with( [ 'limit' => 100 ] )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethodConfigurations', $mock_pmc_service ) );

		$api    = new WC_Stripe_API();
		$result = $api->get_payment_method_configurations();

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertNotEmpty( $result->data );
	}

	// -------------------------------------------------------------------------
	// update_payment_method_configurations()
	// -------------------------------------------------------------------------

	/**
	 * Test that update_payment_method_configurations() uses paymentMethodConfigurations->update() with correct args.
	 */
	public function test_update_payment_method_configurations_calls_sdk() {
		$pmc_id   = 'pmc_test456';
		$pmc_data = [ 'card' => [ 'display_preference' => [ 'preference' => 'on' ] ] ];
		$response = $this->make_stripe_response(
			array_merge(
				[
					'id'     => $pmc_id,
					'object' => 'payment_method_configuration',
				],
				$pmc_data
			)
		);

		$mock_pmc_service = $this->createMock( \Stripe\Service\PaymentMethodConfigurationService::class );
		$mock_pmc_service->expects( $this->once() )
			->method( 'update' )
			->with( $pmc_id, $pmc_data )
			->willReturn( $response );

		$this->inject_sdk( $this->make_sdk_with_service( 'paymentMethodConfigurations', $mock_pmc_service ) );

		$api    = new WC_Stripe_API();
		$result = $api->update_payment_method_configurations( $pmc_id, $pmc_data );

		$this->assertInstanceOf( \Stripe\StripeObject::class, $result );
		$this->assertEquals( $pmc_id, $result->id );
	}

	// -------------------------------------------------------------------------
	// SDK cache invalidation
	// -------------------------------------------------------------------------

	/**
	 * Test that the SDK client is rebuilt when the secret key changes.
	 */
	public function test_sdk_is_rebuilt_when_secret_key_changes() {
		// First call creates an SDK instance.
		$reflection = new ReflectionClass( WC_Stripe_API::class );
		$get_sdk    = $reflection->getMethod( 'get_sdk' );
		$get_sdk->setAccessible( true );

		// Reset SDK state.
		$this->sdk_prop->setValue( null, null );
		$this->sdk_secret_prop->setValue( null, '' );

		$sdk1 = $get_sdk->invoke( null );
		$this->assertInstanceOf( \Stripe\StripeClient::class, $sdk1 );

		// Change the secret key.
		$stripe_settings                    = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_secret_key'] = 'sk_test_different_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$sdk2 = $get_sdk->invoke( null );

		// Should be a new instance because the key changed.
		$this->assertNotSame( $sdk1, $sdk2 );
	}

	/**
	 * Test that the SDK client is reused when the secret key stays the same.
	 */
	public function test_sdk_is_reused_when_secret_key_unchanged() {
		$reflection = new ReflectionClass( WC_Stripe_API::class );
		$get_sdk    = $reflection->getMethod( 'get_sdk' );
		$get_sdk->setAccessible( true );

		// Reset SDK state.
		$this->sdk_prop->setValue( null, null );
		$this->sdk_secret_prop->setValue( null, '' );

		$sdk1 = $get_sdk->invoke( null );
		$sdk2 = $get_sdk->invoke( null );

		$this->assertSame( $sdk1, $sdk2 );
	}
}
