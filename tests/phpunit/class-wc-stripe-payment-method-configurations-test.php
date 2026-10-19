<?php

/**
 * Class WC_Stripe_Payment_Method_Configurations tests.
 */
class WC_Stripe_Payment_Method_Configurations_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	private const MOCK_CHILD_LIVE_PMC = [
		'id'       => 'pmc_id_live_child',
		'parent'   => WC_Stripe_Payment_Method_Configurations::LIVE_MODE_CONFIGURATION_PARENT_ID,
		'name'     => 'Live Child PMC',
		'livemode' => true,
		'active'   => true,
		'default'  => true,
	];

	private const MOCK_CHILD_TEST_PMC = [
		'id'       => 'pmc_id_test_child',
		'parent'   => WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
		'name'     => 'Test Child PMC',
		'livemode' => false,
		'active'   => true,
		'default'  => true,
	];

	private const MOCK_CHILD_OTHER_LIVE_PMC = [
		'id'       => 'pmc_id_other_live_child',
		'parent'   => 'pmc_id_other_live_parent',
		'name'     => 'Other Live Child PMC',
		'livemode' => true,
		'active'   => true,
		'default'  => false,
	];

	private const MOCK_CHILD_OTHER_TEST_PMC = [
		'id'       => 'pmc_id_other_test_child',
		'parent'   => 'pmc_id_other_test_parent',
		'name'     => 'Other Test Child PMC',
		'livemode' => false,
		'active'   => true,
		'default'  => false,
	];

	private const MOCK_LIVE_NON_CHILD_DEFAULT_PMC = [
		'id'       => 'pmc_id_live_non_child_default',
		'parent'   => null,
		'name'     => 'Live Non Child Default PMC',
		'livemode' => true,
		'active'   => true,
		'default'  => true,
	];

	private const MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC = [
		'id'       => 'pmc_id_live_non_child_non_default',
		'parent'   => null,
		'name'     => 'Live Non Child Non Default PMC',
		'livemode' => true,
		'active'   => true,
		'default'  => false,
	];

	private const MOCK_TEST_NON_CHILD_DEFAULT_PMC = [
		'id'       => 'pmc_id_test_non_child_default',
		'parent'   => null,
		'name'     => 'Test Non Child Default PMC',
		'livemode' => false,
		'active'   => true,
		'default'  => true,
	];

	private const MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC = [
		'id'       => 'pmc_id_test_non_child_non_default',
		'parent'   => null,
		'name'     => 'Test Non Child Non Default PMC',
		'livemode' => false,
		'active'   => true,
		'default'  => false,
	];

	private const MOCK_LIVE_INACTIVE_PMC = [
		'id'       => 'pmc_id_live_inactive',
		'parent'   => null,
		'name'     => 'Live Inactive PMC',
		'livemode' => true,
		'active'   => false,
	];

	/**
	 * Reset the fetch cooldown between tests. The parent already clears the PMC cache, but not this
	 * option, which `refresh_pmc_availability()` writes — leaving it set would leak into later tests.
	 */
	public function tear_down() {
		parent::tear_down();
		delete_option( WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
	}

	/**
	 * Tests for `get_parent_configuration_id`.
	 *
	 * @return void
	 */
	public function test_get_parent_configuration_id() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		WC_Stripe_Helper::update_main_stripe_settings(
			array_merge(
				$initial_settings,
				[ 'testmode' => 'yes' ]
			)
		);

		$this->assertEquals(
			WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
			WC_Stripe_Payment_Method_Configurations::get_parent_configuration_id()
		);

		WC_Stripe_Helper::update_main_stripe_settings(
			array_merge(
				$initial_settings,
				[ 'testmode' => 'no' ]
			)
		);

		$this->assertEquals(
			WC_Stripe_Payment_Method_Configurations::LIVE_MODE_CONFIGURATION_PARENT_ID,
			WC_Stripe_Payment_Method_Configurations::get_parent_configuration_id()
		);

		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
	}

	/**
	 * clear_in_memory_payment_method_configuration_cache() must only clear the in-memory cache, and should keep the database cache in place.
	 */
	public function test_clear_in_memory_payment_method_configuration_cache_preserves_database_cache(): void {
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		WC_Stripe_Database_Cache::set( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, (object) [ 'id' => 'pmc_first' ] );
		$this->assertSame( 'pmc_first', WC_Stripe_Payment_Method_Configurations::get_configuration_id() );

		// Change the database entry behind the in-memory copy to show which layer each read uses.
		WC_Stripe_Database_Cache::set( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, (object) [ 'id' => 'pmc_second' ] );
		$this->assertSame( 'pmc_first', WC_Stripe_Payment_Method_Configurations::get_configuration_id() );

		WC_Stripe_Payment_Method_Configurations::clear_in_memory_payment_method_configuration_cache();

		$this->assertSame( 'pmc_second', WC_Stripe_Payment_Method_Configurations::get_configuration_id() );
		$this->assertSame( 'pmc_second', WC_Stripe_Database_Cache::get( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY )->id );
	}

	/**
	 * clear_payment_method_configuration_cache() must clear both the in-memory and database caches.
	 */
	public function test_clear_payment_method_configuration_cache_clears_memory_and_database(): void {
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		WC_Stripe_Database_Cache::set( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, (object) [ 'id' => 'pmc_first' ] );
		$this->assertSame( 'pmc_first', WC_Stripe_Payment_Method_Configurations::get_configuration_id() );

		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		$primary_configuration = new ReflectionProperty( WC_Stripe_Payment_Method_Configurations::class, 'primary_configuration' );
		$primary_configuration->setAccessible( true );
		$this->assertNull( $primary_configuration->getValue() );
		$this->assertNull( WC_Stripe_Database_Cache::get( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY ) );
	}

	/**
	 * Tests the disable_payment_method_configuration_sync method.
	 *
	 * @return void
	 */
	public function test_disable_payment_method_configuration_sync() {
		// Get initial settings
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		// Use reflection to access the private method
		$reflection = new ReflectionClass( WC_Stripe_Payment_Method_Configurations::class );
		$method     = $reflection->getMethod( 'disable_payment_method_configuration_sync' );
		$method->setAccessible( true );
		// Call the method
		$method->invoke( null );

		// Get updated settings
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		// Verify pmc_enabled is set to 'no'
		$this->assertEquals( 'no', $updated_settings['pmc_enabled'] );

		// Restore original settings
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
	}

	/**
	 * Tests that disable_payment_method_configuration_sync is called when no PMC is found.
	 *
	 * @return void
	 */
	public function test_disable_payment_method_configuration_sync_on_no_pmc() {
		// Mock the Stripe API response to return no configurations
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_api->expects( $this->once() )
			->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => [] ] );

		// Set the mock API instance
		$reflection = new ReflectionClass( WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		// Get initial settings
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		// Call get_primary_configuration which should trigger disable_payment_method_configuration_sync
		// Use reflection to access the private method
		$reflection = new ReflectionClass( WC_Stripe_Payment_Method_Configurations::class );
		$method     = $reflection->getMethod( 'get_primary_configuration' );
		$method->setAccessible( true );

		// Call the method
		$method->invoke( null );

		// Get updated settings
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		// Verify pmc_enabled is set to 'no'
		$this->assertEquals( 'no', $updated_settings['pmc_enabled'] );

		// Restore original settings and API instance
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		$property->setValue( null, null );
	}

	/**
	 * Tests that pmc_enabled is not set to 'no' when valid PMC data exists.
	 *
	 * @return void
	 */
	public function test_pmc_enabled_not_disabled_with_valid_data() {
		// Get initial settings
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		// Mock the Stripe API response to return a valid configuration
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_configuration = (object) [
			'id'     => 'test_config_id',
			'parent' => WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
		];

		$mock_api->expects( $this->once() )
			->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => [ $mock_configuration ] ] );

		// Set the mock API instance
		$reflection = new ReflectionClass( WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		// Call get_primary_configuration which should NOT trigger disable_payment_method_configuration_sync
		// Use reflection to access the private method
		$reflection = new ReflectionClass( WC_Stripe_Payment_Method_Configurations::class );
		$method     = $reflection->getMethod( 'get_primary_configuration' );
		$method->setAccessible( true );

		try {
			// Call the method
			$method->invoke( null );

			// Get the updated settings
			$updated_settings = WC_Stripe_Helper::get_stripe_settings();

			// Verify pmc_enabled is not set to 'no'
			$this->assertNotEquals( 'no', $updated_settings['pmc_enabled'] ?? null );
		} finally {
			// Restore original settings and API instance
			WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
			$property->setValue( null, null );
		}
	}

	/**
	 * Tests that disable_payment_method_configuration_sync is called when there is a valid PMC in the responmse but is not valid.
	 *
	 * @return void
	 */
	public function test_disable_payment_method_configuration_sync_on_not_valid_pmc() {
		// Mock the Stripe API response to return no configurations
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_configuration = (object) [
			'id'     => 'test_config_id',
			'parent' => 'pmc_from_another_platform_id',
		];

		$mock_api->expects( $this->once() )
			->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => [ $mock_configuration ] ] );

		// Set the mock API instance
		$reflection = new ReflectionClass( WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		// Get initial settings
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		// Call get_payment_method_configuration_from_stripe which should trigger disable_payment_method_configuration_sync
		// We could use get_primary_configuration, but it has a cooldown cache; we can remove the option for the test, but
		// we want to test the function get_payment_method_configuration_from_stripe that is the one processing the response
		// from the Stripe API call.
		// Use reflection to access the private method
		$reflection = new ReflectionClass( WC_Stripe_Payment_Method_Configurations::class );
		$method     = $reflection->getMethod( 'get_payment_method_configuration_from_stripe' );
		$method->setAccessible( true );

		// Call the method
		$method->invoke( null );

		// Get updated settings
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		// Verify pmc_enabled is set to 'no'
		$this->assertEquals( 'no', $updated_settings['pmc_enabled'] );

		// Restore original settings and API instance
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		$property->setValue( null, null );
	}

	/**
	 * Provide test cases for {@see test_get_payment_method_configuration_from_stripe()}.
	 *
	 * @return array
	 */
	public function provide_get_payment_method_configuration_from_stripe_tests(): array {
		return [
			'no_pmcs_returned'                                                                   => [
				'mock_pmc_response'        => [],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_child_pmc_returned'                                                       => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_child_pmc_returned_after_fallback_pmc_id_is_set'                          => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'only_live_other_platform_child_no_pmc_returned'                                     => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_LIVE_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_other_platform_child_no_pmc_returned_after_fallback_pmc_id_is_set'        => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_LIVE_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'only_test_child_pmc_returned'                                                       => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_child_pmc_returned_after_fallback_pmc_id_is_set'                          => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => true,
			],
			'only_test_other_platform_child_no_pmc_returned'                                     => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_TEST_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_other_platform_child_no_pmc_returned_after_fallback_pmc_id_is_set'        => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_TEST_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => true,
			],
			'only_live_non_child_default_pmc_returned'                                           => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_non_child_non_default_pmc_returned'                                       => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_non_child_default_pmc_returned_after_fallback_pmc_id_is_set_to_other_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'only_live_non_child_non_default_pmc_returned_after_fallback_pmc_id_is_set_to_other_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'only_live_non_child_default_pmc_returned_after_fallback_pmc_id_is_set_to_same_pmc'  => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'only_live_non_child_non_default_pmc_returned_after_fallback_pmc_id_is_set_to_same_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'only_test_non_child_default_pmc_returned'                                           => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_non_child_non_default_pmc_returned'                                       => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_non_child_default_pmc_returned_after_fallback_pmc_id_is_set_to_other_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => true,
			],
			'only_test_non_child_non_default_pmc_returned_after_fallback_pmc_id_is_set_to_other_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => true,
			],
			'only_test_non_child_default_pmc_returned_after_fallback_pmc_id_is_set_to_same_pmc'  => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'is_test_mode'             => true,
			],
			'only_test_non_child_non_default_pmc_returned_after_fallback_pmc_id_is_set_to_same_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => true,
			],
			'live_non_child_default_active_pmc_returned_with_inactive_and_other_child_pmc'       => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_INACTIVE_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'live_non_child_non_default_active_pmc_returned_with_inactive_pmc_and_other_child_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_INACTIVE_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'live_non_child_default_active_pmc_returned_with_inactive_and_other_child_pmc_after_fallback_pmc_id_is_set_to_other_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_INACTIVE_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'live_non_child_non_default_active_pmc_returned_with_inactive_and_other_child_pmc_after_fallback_pmc_id_is_set_to_other_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_INACTIVE_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'live_non_child_default_active_pmc_returned_with_inactive_and_other_child_pmc_after_fallback_pmc_id_is_set_to_same_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_INACTIVE_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'live_non_child_non_default_active_pmc_returned_with_inactive_and_other_child_pmc_after_fallback_pmc_id_is_set_to_same_pmc' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_INACTIVE_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'fallback_pmc_ignored_when_live_child_pmc_is_returned'                               => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'default_pmc_ignored_when_live_child_pmc_is_returned'                                => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC, (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'fallback_pmc_ignored_when_test_child_pmc_is_returned'                               => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => true,
			],
			'default_pmc_ignored_when_test_child_pmc_is_returned'                                => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC, (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => true,
			],
			'existing_fallback_pmc_returned_when_multiple_non_child_non_default_pmcs_and_other_platform_pmc_returned' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_LIVE_PMC, (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'existing_fallback_pmc_returned_when_multiple_non_child_default_pmcs_returned'       => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'fallback_pmc_returned_when_multiple_non_child_pmcs_with_one_default_pmc_returned_with_fallback_pmc_id_set' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'default_pmc_returned_when_multiple_non_child_pmcs_with_one_default_pmc_returned_without_fallback_pmc_id_set' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'no_pmc_returned_when_multiple_non_child_non_default_pmcs_returned_with_non_matching_fallback_pmc_id' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_CHILD_OTHER_LIVE_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'no_pmc_returned_when_multiple_non_child_non_default_pmcs_returned_with_no_existing_fallback_pmc_id' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'no_pmc_returned_when_multiple_non_child_default_pmcs_returned_with_no_existing_fallback_pmc_id' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC, (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
		];
	}

	/**
	 * Tests get_payment_method_configuration_from_stripe().
	 *
	 * @param array $mock_pmc_response              The mock PMC response.
	 * @param string|null $expected_pmc_id          The expected PMC ID.
	 * @param string|null $expected_fallback_pmc_id The expected fallback PMC ID to be stored in the fallback option.
	 * @param string|null $initial_fallback_pmc_id  The initial fallback PMC ID to be stored in the fallback option.
	 * @param bool $is_test_mode                    Whether the test is running in test mode.
	 * @return void
	 * @dataProvider provide_get_payment_method_configuration_from_stripe_tests()
	 */
	public function test_get_payment_method_configuration_from_stripe( array $mock_pmc_response, ?string $expected_pmc_id, ?string $expected_fallback_pmc_id, ?string $initial_fallback_pmc_id = null, bool $is_test_mode = false ) {
		$settings             = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode'] = $is_test_mode ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$mock_api = $this->getMockBuilder( \WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_api->expects( $this->once() )
			->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => $mock_pmc_response ] );

		// Set the mock API instance
		$reflection                   = new ReflectionClass( \WC_Stripe_API::class );
		$stripe_api_instance_property = $reflection->getProperty( 'instance' );
		$stripe_api_instance_property->setAccessible( true );
		$stripe_api_instance_property->setValue( null, $mock_api );

		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		$fallback_pmc_key = $is_test_mode ? 'woocommerce_stripe_pmc_fallback_id_test' : 'woocommerce_stripe_pmc_fallback_id_live';

		if ( null === $initial_fallback_pmc_id ) {
			delete_option( $fallback_pmc_key );
		} else {
			update_option( $fallback_pmc_key, $initial_fallback_pmc_id );
		}

		$reflection = new ReflectionClass( \WC_Stripe_Payment_Method_Configurations::class );
		$method     = $reflection->getMethod( 'get_payment_method_configuration_from_stripe' );
		$method->setAccessible( true );

		$primary_pmc = $method->invoke( null );

		// Reset API instance before any assertions.
		$stripe_api_instance_property->setValue( null, null );

		if ( null === $expected_pmc_id ) {
			$this->assertNull( $primary_pmc );
		} else {
			$this->assertIsObject( $primary_pmc );
			$this->assertEquals( $expected_pmc_id, $primary_pmc->id );
		}

		$fallback_pmc_id = get_option( $fallback_pmc_key );
		if ( null === $expected_fallback_pmc_id ) {
			$this->assertFalse( $fallback_pmc_id );
		} else {
			$this->assertEquals( $expected_fallback_pmc_id, $fallback_pmc_id );
		}

		if ( null !== $expected_pmc_id && null !== $expected_fallback_pmc_id ) {
			$this->assertEquals( $expected_fallback_pmc_id, $primary_pmc->id );
		}
	}

	/**
	 * Provide test cases for {@see test_get_configuration_id()}.
	 *
	 * @return array
	 */
	public function provide_get_configuration_id_tests(): array {
		return [
			'PMC is disabled - live'                               => [
				'is_test_mode'              => false,
				'pmc_enabled'               => false,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'PMC is disabled - test'                               => [
				'is_test_mode'              => true,
				'pmc_enabled'               => false,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Account is not connected - live'                      => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => false,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Account is not connected - test'                      => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => false,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Does not have a primary configuration - live'         => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Does not have a primary configuration - test'         => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Has a primary configuration with no id - live'        => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Has a primary configuration with no id - test'        => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Has a cached primary configuration with no id - live' => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => null,
				'from_cache'                => true,
				'expected_configuration_id' => null,
			],
			'Has a cached primary configuration with no id - test' => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => null,
				'from_cache'                => true,
				'expected_configuration_id' => null,
			],
			'Has a primary configuration with an id - live'        => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => 'pmc_12345',
				'from_cache'                => false,
				'expected_configuration_id' => 'pmc_12345',
			],
			'Has a primary configuration with an id - test'        => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => 'pmc_12345',
				'from_cache'                => false,
				'expected_configuration_id' => 'pmc_12345',
			],
			'Has a cached primary configuration with an id - live' => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => 'pmc_12345',
				'from_cache'                => true,
				'expected_configuration_id' => 'pmc_12345',
			],
			'Has a cached primary configuration with an id - test' => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => 'pmc_12345',
				'from_cache'                => true,
				'expected_configuration_id' => 'pmc_12345',
			],
		];
	}
	/**
	 * Tests for `get_configuration_id`.
	 *
	 * @dataProvider provide_get_configuration_id_tests()
	 * @param bool        $is_test_mode              Whether test mode should be enabled.
	 * @param bool        $pmc_enabled               Whether the PMC is enabled.
	 * @param bool        $account_connected         Whether the account is connected.
	 * @param bool        $has_primary_configuration Whether a primary configuration should be returned.
	 * @param string|null $primary_configuration_id  The ID of the returned primary configuration.
	 * @param bool        $from_cache                Should the mock configuration be set in the cache.
	 * @param string|null $expected_configuration_id The expected configuration ID.
	 * @return void
	 */
	public function test_get_configuration_id(
		bool $is_test_mode,
		bool $pmc_enabled,
		bool $account_connected,
		bool $has_primary_configuration,
		?string $primary_configuration_id,
		bool $from_cache,
		?string $expected_configuration_id
	): void {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		$settings         = $initial_settings;

		$settings['testmode']    = $is_test_mode ? 'yes' : 'no';
		$settings['pmc_enabled'] = $pmc_enabled ? 'yes' : 'no';
		if ( $account_connected ) {
			if ( $is_test_mode ) {
				$settings['test_publishable_key'] = 'pk_test_1234567890';
				$settings['test_secret_key']      = 'sk_test_1234567890';
				$settings['test_connection_type'] = 'connect';
			} else {
				$settings['publishable_key'] = 'pk_live_1234567890';
				$settings['secret_key']      = 'sk_live_1234567890';
				$settings['connection_type'] = 'connect';
			}
		} elseif ( $is_test_mode ) {
				unset( $settings['test_publishable_key'] );
				unset( $settings['test_secret_key'] );
				unset( $settings['test_connection_type'] );
		} else {
			unset( $settings['publishable_key'] );
			unset( $settings['secret_key'] );
			unset( $settings['connection_type'] );
		}
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		delete_option( \WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		$mock_configurations = [];
		if ( $has_primary_configuration ) {
			$mock_configurations[] = (object) [
				'id'     => $primary_configuration_id,
				'parent' => null,
				'active' => true,
			];
		}

		// Mock API to return the mock configurations from above.
		$mock_api = $this->getMockBuilder( \WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		// Don't test for a specific count of calls, as the method may or may not be called, and that
		// is not what we are trying to test here.
		$mock_api->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => $mock_configurations ] );

		$reflection = new ReflectionClass( \WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		if ( $from_cache && $has_primary_configuration ) {
			\WC_Stripe_Database_Cache::set( \WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, $mock_configurations[0] );
		}

		$configuration_id = \WC_Stripe_Payment_Method_Configurations::get_configuration_id();

		// Reset settings and API instance before running assertions.
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		$property->setValue( null, null );
		delete_option( \WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		if ( null === $expected_configuration_id ) {
			$this->assertNull( $configuration_id );
		} else {
			$this->assertEquals( $expected_configuration_id, $configuration_id );
		}
	}

	/**
	 * Test `get_unsupported_enabled_payment_method_ids_in_pmc` with various scenarios.
	 *
	 * @param array  $settings               Settings to configure.
	 * @param object|null $mock_api_response Mock API response object.
	 * @param array  $expected_contains      Payment methods that should be in the result.
	 * @param array  $expected_not_contains  Payment methods that should NOT be in the result.
	 * @param bool   $expect_empty           Whether to expect an empty result.
	 * @return void
	 * @dataProvider provide_test_get_unsupported_enabled_payment_method_ids_in_pmc
	 */
	public function test_get_unsupported_enabled_payment_method_ids_in_pmc( array $settings, $mock_api_response, array $expected_contains, array $expected_not_contains, bool $expect_empty ) {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		// Set up mock API first, before clearing cache, to ensure it's ready when needed.
		$property = null;
		if ( null !== $mock_api_response ) {
			$mock_api = $this->getMockBuilder( \WC_Stripe_API::class )
				->disableOriginalConstructor()
				->getMock();

			$mock_api->method( 'get_payment_method_configurations' )
				->willReturn( $mock_api_response );

			$reflection = new ReflectionClass( \WC_Stripe_API::class );
			$property   = $reflection->getProperty( 'instance' );
			$property->setAccessible( true );
			$property->setValue( null, $mock_api );
		}

		// Update settings and clear all caches/cooldowns after mock is set up.
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		delete_option( WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		$result = WC_Stripe_Payment_Method_Configurations::get_unsupported_enabled_payment_method_ids_in_pmc();

		// Restore original settings and API instance.
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		if ( null !== $property ) {
			$property->setValue( null, null );
		}
		// Clear cache and cooldown.
		delete_option( WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		// Assert the expected output.
		$this->assertIsArray( $result );

		if ( $expect_empty ) {
			$this->assertEmpty( $result );
		} else {
			foreach ( $expected_contains as $method ) {
				$this->assertContains( $method, $result, "Expected method '{$method}' to be in result" );
			}
			foreach ( $expected_not_contains as $method ) {
				$this->assertNotContains( $method, $result, "Expected method '{$method}' NOT to be in result" );
			}
		}
	}

	/**
	 * Data provider for `test_get_unsupported_enabled_payment_method_ids_in_pmc`
	 *
	 * @return array
	 */
	public function provide_test_get_unsupported_enabled_payment_method_ids_in_pmc(): array {
		$settings_base = WC_Stripe_Helper::get_stripe_settings();

		return [
			'PMC disabled'             => [
				'settings'              => array_merge( $settings_base, [ 'pmc_enabled' => 'no' ] ),
				'mock_api_response'     => null,
				'expected_contains'     => [],
				'expected_not_contains' => [],
				'expect_empty'          => true,
			],
			'No configuration'         => [
				'settings'              => array_merge( $settings_base, [ 'pmc_enabled' => 'yes' ] ),
				'mock_api_response'     => (object) [ 'data' => [] ],
				'expected_contains'     => [],
				'expected_not_contains' => [],
				'expect_empty'          => true,
			],
			'With unsupported methods' => [
				'settings'              => array_merge(
					$settings_base,
					[
						'pmc_enabled'          => 'yes',
						'testmode'             => 'yes',
						'test_publishable_key' => 'pk_test_1234567890',
						'test_secret_key'      => 'sk_test_1234567890',
						'test_connection_type' => 'connect',
					]
				),
				'mock_api_response'     => (object) [
					'data' => [
						(object) [
							'id'        => 'pmc_test',
							'parent'    => WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
							'active'    => true,
							'livemode'  => false,
							// Supported method (card) - should be skipped
							'card'      => (object) [
								'display_preference' => (object) [ 'value' => 'on' ],
							],
							// Unsupported enabled methods - should be returned
							'fpx'       => (object) [
								'display_preference' => (object) [ 'value' => 'on' ],
							],
							'naver_pay' => (object) [
								'display_preference' => (object) [ 'value' => 'on' ],
							],
							// Unsupported but disabled method - should not be returned
							'paypal'    => (object) [
								'display_preference' => (object) [ 'value' => 'off' ],
							],
						],
					],
				],
				'expected_contains'     => [ 'fpx', 'naver_pay' ],
				'expected_not_contains' => [ 'card', 'paypal' ],
				'expect_empty'          => false,
			],
			'Only supported methods'   => [
				'settings'              => array_merge( $settings_base, [ 'pmc_enabled' => 'yes' ] ),
				'mock_api_response'     => (object) [
					'data' => [
						(object) [
							'id'       => 'pmc_test',
							'parent'   => WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
							'active'   => true,
							'livemode' => false,
							'card'     => (object) [
								'display_preference' => (object) [ 'value' => 'on' ],
							],
							'ideal'    => (object) [
								'display_preference' => (object) [ 'value' => 'on' ],
							],
						],
					],
				],
				'expected_contains'     => [],
				'expected_not_contains' => [ 'ideal', 'card' ],
				'expect_empty'          => true,
			],
		];
	}

	/**
	 * Sets up the API instance mock and returns the reflection property used to reset it.
	 *
	 * @param mixed $configurations_response Value to return from get_payment_method_configurations().
	 * @return ReflectionProperty The instance property so the caller can reset it after assertions.
	 */
	private function mock_get_payment_method_configurations_response( $configurations_response ): ReflectionProperty {
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_api->expects( $this->once() )
			->method( 'get_payment_method_configurations' )
			->willReturn( $configurations_response );

		$reflection        = new ReflectionClass( WC_Stripe_API::class );
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, $mock_api );

		return $instance_property;
	}

	/**
	 * Builds a settings array that satisfies WC_Stripe_Helper::is_connected() in test mode.
	 */
	private function build_connected_test_mode_settings( $pmc_enabled = null ): array {
		$settings = array_merge(
			WC_Stripe_Helper::get_stripe_settings(),
			[
				'testmode'             => 'yes',
				'test_publishable_key' => 'pk_test_1234567890',
				'test_secret_key'      => 'sk_test_1234567890',
				'test_connection_type' => 'connect',
			]
		);

		if ( null === $pmc_enabled ) {
			unset( $settings['pmc_enabled'] );
		} else {
			$settings['pmc_enabled'] = $pmc_enabled;
		}

		return $settings;
	}

	/**
	 * Provider for {@see test_refresh_pmc_availability_leaves_pmc_enabled_untouched_on_api_failure()}.
	 */
	public function provide_refresh_pmc_availability_api_failures(): array {
		return [
			'wp_error_response' => [ new WP_Error( 'stripe_error', 'boom' ) ],
			'null_response'     => [ null ],
			'stripe_error_body' => [ (object) [ 'error' => (object) [ 'message' => 'something failed' ] ] ],
		];
	}

	/**
	 * @dataProvider provide_refresh_pmc_availability_api_failures
	 */
	public function test_refresh_pmc_availability_leaves_pmc_enabled_untouched_on_api_failure( $api_response ) {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		$instance_property = $this->mock_get_payment_method_configurations_response( $api_response );

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * No usable PMC returned: pmc_enabled is set to 'no'.
	 */
	public function test_refresh_pmc_availability_disables_when_no_usable_pmc() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		$instance_property = $this->mock_get_payment_method_configurations_response( (object) [ 'data' => [] ] );

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'no', $updated_settings['pmc_enabled'] );
	}

	/**
	 * Usable PMC returned with pmc_enabled empty: migration runs and ends 'yes'.
	 */
	public function test_refresh_pmc_availability_runs_migration_when_flag_empty() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( null ) );

		$instance_property = $this->mock_get_payment_method_configurations_response(
			(object) [ 'data' => [ $this->sync_configuration( [] ) ] ]
		);

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * Usable PMC returned with pmc_enabled='no': flag flips back to 'yes' (recovery path).
	 */
	public function test_refresh_pmc_availability_recovers_from_no_state() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'no' ) );

		$instance_property = $this->mock_get_payment_method_configurations_response(
			(object) [ 'data' => [ $this->sync_configuration( [] ) ] ]
		);

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * Usable PMC returned with pmc_enabled='yes': no-op (still 'yes').
	 */
	public function test_refresh_pmc_availability_noop_when_already_yes() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		$instance_property = $this->mock_get_payment_method_configurations_response(
			(object) [ 'data' => [ $this->sync_configuration( [] ) ] ]
		);

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * When `wc_stripe_preselect_payment_method_configuration` resolves to a usable PMC,
	 * `refresh_pmc_availability()` uses it directly: it bypasses the bulk
	 * `get_payment_method_configurations()` call and ends with `pmc_enabled` set to 'yes'.
	 */
	public function test_refresh_pmc_availability_honors_preselect_filter() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( null ) );

		// Assert the bulk listing is never queried when the preselect filter resolves a usable PMC.
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_api->expects( $this->never() )->method( 'get_payment_method_configurations' );

		$reflection        = new ReflectionClass( WC_Stripe_API::class );
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, $mock_api );

		$preselected_pmc_id = 'pmc_preselected_for_test';
		$preselected_pmc    = (object) array_merge( self::MOCK_CHILD_TEST_PMC, [ 'id' => $preselected_pmc_id ] );

		$preselect_filter = function () use ( $preselected_pmc_id ) {
			return $preselected_pmc_id;
		};
		add_filter( 'wc_stripe_preselect_payment_method_configuration', $preselect_filter );

		$http_filter = function ( $preempt, $args, $url ) use ( $preselected_pmc_id, $preselected_pmc ) {
			if ( false !== strpos( $url, 'payment_method_configurations/' . $preselected_pmc_id ) ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( $preselected_pmc ),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_filter, 10, 3 );

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		remove_filter( 'wc_stripe_preselect_payment_method_configuration', $preselect_filter );
		remove_filter( 'pre_http_request', $http_filter );
		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * When `wc_stripe_preselect_payment_method_configuration` is set but the preselected PMC
	 * lookup fails (transient Stripe/transport error), `refresh_pmc_availability()` leaves
	 * `pmc_enabled` untouched and does not fall through to the bulk listing / disable path.
	 */
	public function test_refresh_pmc_availability_leaves_pmc_enabled_untouched_on_preselect_error() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		// The preselected PMC is authoritative, so a failed lookup must not trigger the bulk listing.
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_api->expects( $this->never() )->method( 'get_payment_method_configurations' );

		$reflection        = new ReflectionClass( WC_Stripe_API::class );
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, $mock_api );

		$preselected_pmc_id = 'pmc_preselected_for_test';

		$preselect_filter = function () use ( $preselected_pmc_id ) {
			return $preselected_pmc_id;
		};
		add_filter( 'wc_stripe_preselect_payment_method_configuration', $preselect_filter );

		// Return a Stripe error body for the preselected PMC lookup.
		$http_filter = function ( $preempt, $args, $url ) use ( $preselected_pmc_id ) {
			if ( false !== strpos( $url, 'payment_method_configurations/' . $preselected_pmc_id ) ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'error' => [ 'message' => 'No such payment_method_configuration' ] ] ),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_filter, 10, 3 );

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		remove_filter( 'wc_stripe_preselect_payment_method_configuration', $preselect_filter );
		remove_filter( 'pre_http_request', $http_filter );
		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * A transient Stripe failure during refresh must not flip pmc_enabled to 'no' through the forced
	 * settings re-fetch that follows it. refresh_pmc_availability() arms the fetch cooldown, so the
	 * subsequent get_upe_enabled_payment_method_ids( true ) reuses the last-known-good cached PMC
	 * instead of re-hitting Stripe — a repeat call that would fail again and disable sync.
	 *
	 * The mock asserts get_payment_method_configurations() runs exactly once (the refresh itself);
	 * a second call would mean the forced re-fetch bypassed the cache.
	 */
	public function test_refresh_pmc_availability_survives_transient_failure_through_forced_settings_refetch() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		// Seed a last-known-good PMC in the cache: the merchant was working before the outage.
		WC_Stripe_Database_Cache::set(
			WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY,
			$this->sync_configuration(),
			WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_EXPIRATION
		);

		$instance_property = $this->mock_get_payment_method_configurations_response( new WP_Error( 'stripe_error', 'boom' ) );

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		// Simulate the frontend's forced settings re-fetch triggered by the account refresh.
		WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( true );

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	private function sync_configuration( array $enabled = [ 'card', 'klarna' ] ): object {
		$configuration = (object) self::MOCK_CHILD_TEST_PMC;
		foreach ( [ 'card', 'klarna', 'link', 'apple_pay', 'google_pay' ] as $id ) {
			$configuration->$id = (object) [ 'display_preference' => (object) [ 'value' => in_array( $id, $enabled, true ) ? 'on' : 'off' ] ];
		}
		return $configuration;
	}

	private function configure_sync_settings( string $flag = 'no', array $local = [ 'card' ], string $wallets = 'no' ): void {
		WC_Stripe_Helper::update_main_stripe_settings(
			array_merge(
				$this->build_connected_test_mode_settings( $flag ),
				[
					'upe_checkout_experience_accepted_payments' => $local,
					'express_checkout'                          => $wallets,
					'express_checkout_button_locations'         => [ 'checkout' ],
				]
			)
		);
	}

	/** @dataProvider mirrored_selection_provider */
	public function test_active_sync_mirrors_effective_selections( array $remote, array $expected, string $wallets, bool $refresh ): void {
		$this->configure_sync_settings( 'yes' );
		$this->mock_get_payment_method_configurations_response( (object) [ 'data' => [ $this->sync_configuration( $remote ) ] ] );
		$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		if ( $refresh ) {
			WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();
		} else {
			WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
		}
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $expected, $settings['upe_checkout_experience_accepted_payments'] );
		$this->assertSame( $wallets, $settings['express_checkout'] );
		$this->assertSame( [ 'checkout' ], $settings['express_checkout_button_locations'] );
		PMC_Test_Helper::disable_pmc();
		$this->assertSame( $expected, WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );

		$this->assertSame( 'yes' === $wallets, $gateway->is_express_checkout_enabled() );
		if ( [] === $expected ) {
			$this->assertFalse( $gateway->is_available() );
		}
	}

	public function mirrored_selection_provider(): array {
		$selections = [
			'ordinary methods'  => [ [ 'card', 'klarna' ], [ 'card', 'klarna' ], 'no' ],
			'Apple only'        => [ [ 'card', 'apple_pay' ], [ 'card' ], 'yes' ],
			'Google only'       => [ [ 'card', 'google_pay' ], [ 'card' ], 'yes' ],
			'both wallets'      => [ [ 'card', 'apple_pay', 'google_pay' ], [ 'card' ], 'yes' ],
			'empty stays empty' => [ [], [], 'no' ],
		];
		$cases      = [];
		foreach ( $selections as $name => $selection ) {
			$cases[ $name . ' fetch' ]   = array_merge( $selection, [ false ] );
			$cases[ $name . ' refresh' ] = array_merge( $selection, [ true ] );
		}
		return $cases;
	}

	public function test_disable_mirrors_preexisting_cache_and_falls_back_in_same_request(): void {
		$this->configure_sync_settings( 'yes' );
		WC_Stripe_Database_Cache::set( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, $this->sync_configuration( [ 'card', 'klarna', 'google_pay' ] ) );
		$this->mock_get_payment_method_configurations_response( (object) [ 'data' => [] ] );
		$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		$this->assertSame( [ 'card', 'klarna' ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( true ) );
		$this->assertTrue( $gateway->is_express_checkout_enabled() );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $settings['pmc_enabled'] );
		$this->assertSame( 'yes', $settings['express_checkout'] );
	}

	public function test_read_started_during_sync_cannot_overwrite_later_local_edit(): void {
		$this->configure_sync_settings( 'yes' );
		$api = $this->createMock( WC_Stripe_API::class );
		$api->method( 'get_payment_method_configurations' )->willReturnCallback(
			function () {
				$this->configure_sync_settings( 'no', [ 'link' ] );
				return (object) [ 'data' => [ $this->sync_configuration() ] ];
			}
		);
		WC_Stripe_API::set_instance( $api );
		$this->assertSame( [ 'link' ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
		$this->assertSame( [ 'link' ], WC_Stripe_Helper::get_stripe_settings()['upe_checkout_experience_accepted_payments'] );
	}

	/** @dataProvider invalid_observation_provider */
	public function test_invalid_observation_keeps_local_fallback( $response ): void {
		$this->configure_sync_settings( 'yes', [ 'card', 'klarna' ], 'yes' );
		$this->mock_get_payment_method_configurations_response( $response );
		$this->assertSame( [ 'card', 'klarna' ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $settings['express_checkout'] );
		$this->assertSame( 'no', $settings['pmc_enabled'] );
	}

	public function invalid_observation_provider(): array {
		return [
			'error'      => [ new WP_Error( 'unavailable', 'Unavailable' ) ],
			'incomplete' => [ (object) [ 'data' => [ (object) self::MOCK_CHILD_TEST_PMC ] ] ],
			'null'       => [ null ],
		];
	}

	public function test_update_mirrors_effective_response_before_later_gateway_save(): void {
		$this->configure_sync_settings( 'yes' );
		$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		$this->mock_payment_method_configurations( [ 'card' ], [ 'klarna', 'google_pay' ] );
		$this->expect_payment_method_configurations_update( [ 'card', 'klarna', 'google_pay' ] );
		$this->assertTrue( $gateway->update_enabled_payment_methods( [ 'card', 'klarna', 'google_pay' ] ) );
		$gateway->update_option( 'saved_cards', 'yes' );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [ 'card', 'klarna' ], $settings['upe_checkout_experience_accepted_payments'] );
		$this->assertSame( 'yes', $settings['express_checkout'] );
		$this->assertSame( 'yes', $settings['saved_cards'] );
	}

	public function test_late_response_from_previous_connection_does_not_update_local_settings(): void {
		$this->configure_sync_settings( 'yes' );
		$api = $this->createMock( WC_Stripe_API::class );
		$api->method( 'get_payment_method_configurations' )->willReturnCallback(
			function () {
				$settings                    = WC_Stripe_Helper::get_stripe_settings();
				$settings['test_secret_key'] = 'sk_test_other';
				// Model another request's commit without running its hooks in this request.
				global $wpdb;
				$wpdb->update( $wpdb->options, [ 'option_value' => maybe_serialize( $settings ) ], [ 'option_name' => WC_Stripe::SETTINGS_OPTION_NAME ] );
				return (object) [ 'data' => [ $this->sync_configuration() ] ];
			}
		);
		WC_Stripe_API::set_instance( $api );
		WC_Stripe_Payment_Method_Configurations::get_configuration_id();
		$this->assertSame( [ 'card' ], WC_Stripe_Helper::get_stripe_settings()['upe_checkout_experience_accepted_payments'] );
	}

	public function test_cached_reads_do_not_rewrite_local_selections(): void {
		$this->configure_sync_settings( 'yes' );
		$this->mock_get_payment_method_configurations_response( (object) [ 'data' => [ $this->sync_configuration() ] ] );
		$writes      = 0;
		$count_write = function () use ( &$writes ) {
			++$writes;
		};
		add_action( 'update_option_woocommerce_stripe_settings', $count_write );
		try {
			WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
			WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
			$this->assertSame( 1, $writes );
		} finally {
			remove_action( 'update_option_woocommerce_stripe_settings', $count_write );
		}
	}

	public function test_busy_mirror_does_not_overwrite_local_settings(): void {
		$this->configure_sync_settings( 'yes' );
		$this->mock_get_payment_method_configurations_response( (object) [ 'data' => [ $this->sync_configuration() ] ] );
		$owner = WC_Stripe_Option_Lock::acquire( 'wc_stripe_pmc_settings_lock', 300 );
		try {
			WC_Stripe_Payment_Method_Configurations::get_configuration_id();
			$this->assertSame( [ 'card' ], WC_Stripe_Helper::get_stripe_settings()['upe_checkout_experience_accepted_payments'] );
		} finally {
			WC_Stripe_Option_Lock::release( 'wc_stripe_pmc_settings_lock', $owner );
		}
	}
	/** @dataProvider migration_mirror_provider */
	public function test_migration_keeps_verified_selections_in_local_settings( string $flag, bool $force ): void {
		$this->configure_sync_settings( $flag );
		$this->mock_payment_method_configurations( [ 'card', 'klarna', 'google_pay' ], [] );
		$this->expect_payment_method_configurations_update( [ 'card', 'klarna', 'google_pay' ] );
		WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc( $force );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $settings['pmc_enabled'] );
		$this->assertSame( [ 'card', 'klarna' ], $settings['upe_checkout_experience_accepted_payments'] );
		$this->assertSame( 'yes', $settings['express_checkout'] );
	}

	public function migration_mirror_provider(): array {
		return [
			'initial activation' => [ '', false ],
			'forced migration'   => [ 'yes', true ],
		];
	}
}
