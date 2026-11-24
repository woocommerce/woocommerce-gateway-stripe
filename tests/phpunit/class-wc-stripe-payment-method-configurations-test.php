<?php

/**
 * Class WC_Stripe_Payment_Method_Configurations tests.
 */
class WC_Stripe_Payment_Method_Configurations_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	use WC_Stripe_Tracks_Capture_Trait;

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
		WC_Stripe_Database_Cache::set( WC_Stripe_Payment_Method_Configurations::FETCH_FAILURE_COOLDOWN_CACHE_KEY, true, MINUTE_IN_SECONDS );
		$this->assertSame( 'pmc_first', WC_Stripe_Payment_Method_Configurations::get_configuration_id() );

		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		$primary_configuration = new ReflectionProperty( WC_Stripe_Payment_Method_Configurations::class, 'primary_configuration' );
		$primary_configuration->setAccessible( true );
		$this->assertNull( $primary_configuration->getValue() );
		$this->assertNull( WC_Stripe_Database_Cache::get( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY ) );
		$this->assertNull( WC_Stripe_Database_Cache::get( WC_Stripe_Payment_Method_Configurations::FETCH_FAILURE_COOLDOWN_CACHE_KEY ) );
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
	 * Provider for {@see test_get_payment_method_configuration_from_stripe_preserves_sync_and_fallback_on_failure()}.
	 */
	public function provide_get_payment_method_configuration_from_stripe_failure_responses(): array {
		return [
			'wp_error_response'            => [ new WP_Error( 'stripe_api_outage', 'Synthetic failure' ) ],
			'null_response'                => [ null ],
			'stripe_error_body'            => [ (object) [ 'error' => (object) [ 'type' => 'api_error' ] ] ],
			'missing_data'                 => [ (object) [] ],
			'non_array_data'               => [ (object) [ 'data' => 'not an array' ] ],
			'non_object_null_member'       => [ (object) [ 'data' => [ null ] ] ],
			'non_object_array_member'      => [ (object) [ 'data' => [ [] ] ] ],
			'mixed_object_and_null_member' => [ (object) [ 'data' => [ (object) self::MOCK_CHILD_TEST_PMC, null ] ] ],
			'object_missing_id'            => [ (object) [ 'data' => [ (object) [] ] ] ],
			'object_empty_id'              => [ (object) [ 'data' => [ (object) [ 'id' => '' ] ] ] ],
			'object_non_string_id'         => [ (object) [ 'data' => [ (object) [ 'id' => 123 ] ] ] ],
		];
	}

	/**
	 * Invokes get_payment_method_configuration_from_stripe() with seeded sync/fallback state and a mocked response.
	 *
	 * @param mixed $api_response Response returned by get_payment_method_configurations().
	 * @return array
	 */
	private function run_get_payment_method_configuration_from_stripe_with_state( $api_response ): array {
		$initial_settings  = WC_Stripe_Helper::get_stripe_settings();
		$fallback_pmc_key  = 'woocommerce_stripe_pmc_fallback_id_test';
		$instance_property = null;

		try {
			WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );
			update_option( $fallback_pmc_key, 'pmc_synthetic' );
			WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

			$instance_property = $this->mock_get_payment_method_configurations_response( $api_response );

			$reflection = new ReflectionClass( WC_Stripe_Payment_Method_Configurations::class );
			$method     = $reflection->getMethod( 'get_payment_method_configuration_from_stripe' );
			$method->setAccessible( true );

			$primary_pmc      = $method->invoke( null );
			$updated_settings = WC_Stripe_Helper::get_stripe_settings();

			return [
				'primary_pmc' => $primary_pmc,
				'pmc_enabled' => $updated_settings['pmc_enabled'] ?? null,
				'fallback_id' => get_option( $fallback_pmc_key ),
			];
		} finally {
			if ( null !== $instance_property ) {
				$instance_property->setValue( null, null );
			}
			delete_option( $fallback_pmc_key );
			WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();
			WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		}
	}

	/**
	 * Failed or malformed list responses must preserve pmc_enabled and the stored fallback ID.
	 *
	 * @dataProvider provide_get_payment_method_configuration_from_stripe_failure_responses
	 *
	 * @param mixed $api_response Response returned by get_payment_method_configurations().
	 */
	public function test_get_payment_method_configuration_from_stripe_preserves_sync_and_fallback_on_failure( $api_response ): void {
		$result = $this->run_get_payment_method_configuration_from_stripe_with_state( $api_response );

		$this->assertNull( $result['primary_pmc'] );
		$this->assertSame( 'yes', $result['pmc_enabled'] );
		$this->assertSame( 'pmc_synthetic', $result['fallback_id'] );
	}

	/**
	 * A valid empty list response still disables sync and clears the fallback ID.
	 */
	public function test_get_payment_method_configuration_from_stripe_disables_sync_and_clears_fallback_on_empty_list(): void {
		$result = $this->run_get_payment_method_configuration_from_stripe_with_state( (object) [ 'data' => [] ] );

		$this->assertNull( $result['primary_pmc'] );
		$this->assertSame( 'no', $result['pmc_enabled'] );
		$this->assertFalse( $result['fallback_id'] );
	}

	/**
	 * Runs a callback with connected test-mode PMC settings, seeded saved selections, and clean cache/cooldown state.
	 *
	 * @param array    $saved_methods   Saved UPE payment methods in local settings.
	 * @param string   $fallback_pmc_id Stored test fallback PMC ID.
	 * @param callable $callback        Test callback receiving the fallback option key.
	 */
	private function run_with_connected_test_pmc_state( array $saved_methods, string $fallback_pmc_id, callable $callback ): void {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		$fallback_pmc_key = 'woocommerce_stripe_pmc_fallback_id_test';

		try {
			$settings = array_merge(
				$this->build_connected_test_mode_settings( 'yes' ),
				[
					'upe_checkout_experience_accepted_payments' => $saved_methods,
					'express_checkout'                          => 'no',
				]
			);
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
			update_option( $fallback_pmc_key, $fallback_pmc_id );
			$this->reset_payment_method_configuration_state();

			$callback( $fallback_pmc_key );
		} finally {
			WC_Stripe_API::set_instance( null );
			delete_option( $fallback_pmc_key );
			$this->reset_payment_method_configuration_state();
			WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		}
	}

	/**
	 * Public reader falls back to saved selections and suppresses repeated Stripe fetches during failure cooldown.
	 *
	 * @dataProvider provide_get_payment_method_configuration_from_stripe_failure_responses
	 *
	 * @param mixed $api_response Response returned by get_payment_method_configurations().
	 */
	public function test_get_upe_enabled_payment_method_ids_falls_back_to_saved_selections_and_suppresses_repeat_calls_on_failure( $api_response ): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'pmc_synthetic',
			function ( string $fallback_pmc_key ) use ( $api_response ): void {
				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->once() )
					->method( 'get_payment_method_configurations' )
					->willReturn( $api_response );
				WC_Stripe_API::set_instance( $api );

				$first_read  = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
				$second_read = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
				$forced_read = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( true );

				$updated_settings = WC_Stripe_Helper::get_stripe_settings();

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], $first_read );
				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], $second_read );
				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], $forced_read );
				$this->assertSame( 'yes', $updated_settings['pmc_enabled'] ?? null );
				$this->assertSame( 'pmc_synthetic', get_option( $fallback_pmc_key ) );
			}
		);
	}

	/**
	 * Public reader defaults to card when saved selections are empty and Stripe PMC fetch fails.
	 */
	public function test_get_upe_enabled_payment_method_ids_defaults_to_card_when_saved_selections_empty_on_failure(): void {
		$this->run_with_connected_test_pmc_state(
			[],
			'pmc_synthetic',
			function ( string $fallback_pmc_key ): void {
				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->once() )
					->method( 'get_payment_method_configurations' )
					->willReturn( new WP_Error( 'stripe_api_outage', 'Synthetic failure' ) );
				WC_Stripe_API::set_instance( $api );

				$enabled_methods  = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
				$updated_settings = WC_Stripe_Helper::get_stripe_settings();

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], $enabled_methods );
				$this->assertSame( 'yes', $updated_settings['pmc_enabled'] ?? null );
				$this->assertSame( 'pmc_synthetic', get_option( $fallback_pmc_key ) );
			}
		);
	}

	/**
	 * Public reader preserves valid empty-list behavior by disabling sync, clearing fallback, and returning saved selections.
	 */
	public function test_get_upe_enabled_payment_method_ids_disables_sync_and_clears_fallback_on_empty_list(): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'pmc_synthetic',
			function ( string $fallback_pmc_key ): void {
				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->once() )
					->method( 'get_payment_method_configurations' )
					->willReturn( (object) [ 'data' => [] ] );
				WC_Stripe_API::set_instance( $api );

				$enabled_methods  = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
				$updated_settings = WC_Stripe_Helper::get_stripe_settings();

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], $enabled_methods );
				$this->assertSame( 'no', $updated_settings['pmc_enabled'] ?? null );
				$this->assertFalse( get_option( $fallback_pmc_key ) );
			}
		);
	}

	/**
	 * Expires a WC_Stripe_Database_Cache entry in both in-memory cache and wp_options to simulate TTL expiry.
	 *
	 * @param string $key Unprefixed cache key.
	 * @param int    $ttl Cache TTL in seconds.
	 */
	private function expire_database_cache_entry( string $key, int $ttl ): void {
		$prefixed_key = WC_Stripe_Database_Cache::CACHE_KEY_PREFIX . ( WC_Stripe_Mode::is_test() ? 'test_' : 'live_' ) . $key;
		$reflection   = new ReflectionProperty( WC_Stripe_Database_Cache::class, 'in_memory_cache' );
		$reflection->setAccessible( true );
		$in_memory_cache = $reflection->getValue();
		if ( isset( $in_memory_cache[ $prefixed_key ] ) && is_array( $in_memory_cache[ $prefixed_key ] ) ) {
			$in_memory_cache[ $prefixed_key ]['updated'] -= $ttl + 1;
			$reflection->setValue( null, $in_memory_cache );
		}

		$cache_contents = get_option( $prefixed_key );
		if ( is_array( $cache_contents ) ) {
			$cache_contents['updated'] -= $ttl + 1;
			update_option( $prefixed_key, $cache_contents );
		}
	}

	/**
	 * First read after the failure cooldown expires retries the Stripe PMC fetch.
	 */
	public function test_get_upe_enabled_payment_method_ids_retries_after_failure_cooldown_expires(): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD ],
			'pmc_synthetic',
			function (): void {
				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->exactly( 2 ) )
					->method( 'get_payment_method_configurations' )
					->willReturnOnConsecutiveCalls(
						new WP_Error( 'stripe_api_outage', 'Synthetic failure' ),
						(object) [ 'data' => [ $this->mirrored_configuration( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ] ) ] ]
					);
				WC_Stripe_API::set_instance( $api );

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
				$this->assertTrue( WC_Stripe_Database_Cache::get( WC_Stripe_Payment_Method_Configurations::FETCH_FAILURE_COOLDOWN_CACHE_KEY ) );

				$this->expire_database_cache_entry( WC_Stripe_Payment_Method_Configurations::FETCH_FAILURE_COOLDOWN_CACHE_KEY, MINUTE_IN_SECONDS );
				$this->assertNull( WC_Stripe_Database_Cache::get( WC_Stripe_Payment_Method_Configurations::FETCH_FAILURE_COOLDOWN_CACHE_KEY ) );

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
			}
		);
	}

	/**
	 * An empty cache after a successful fetch must still fetch even while the cooldown is active.
	 */
	public function test_get_upe_enabled_payment_method_ids_fetches_when_cache_cleared_after_success(): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD ],
			'pmc_synthetic',
			function (): void {
				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->exactly( 2 ) )
					->method( 'get_payment_method_configurations' )
					->willReturnOnConsecutiveCalls(
						(object) [ 'data' => [ $this->mirrored_configuration( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ] ) ] ],
						(object) [ 'data' => [ $this->mirrored_configuration( [ WC_Stripe_Payment_Methods::CARD ] ) ] ]
					);
				WC_Stripe_API::set_instance( $api );

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
				$this->assertGreaterThan( time(), get_option( WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY, 0 ) );

				WC_Stripe_Payment_Method_Configurations::clear_in_memory_payment_method_configuration_cache();
				WC_Stripe_Database_Cache::delete( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY );

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
			}
		);
	}

	/**
	 * A failed refresh_pmc_availability() with an empty cache suppresses an immediate forced re-fetch during cooldown.
	 */
	public function test_refresh_pmc_availability_failure_with_empty_cache_suppresses_forced_refetch_during_cooldown(): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'pmc_synthetic',
			function ( string $fallback_pmc_key ): void {
				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->once() )
					->method( 'get_payment_method_configurations' )
					->willReturn( new WP_Error( 'stripe_api_outage', 'Synthetic failure' ) );
				WC_Stripe_API::set_instance( $api );

				WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();
				$enabled_methods  = WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( true );
				$updated_settings = WC_Stripe_Helper::get_stripe_settings();

				$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], $enabled_methods );
				$this->assertSame( 'yes', $updated_settings['pmc_enabled'] ?? null );
				$this->assertSame( 'pmc_synthetic', get_option( $fallback_pmc_key ) );
			}
		);
	}

	/**
	 * A failure cooldown recorded in test mode stays active across a live-mode fetch that overwrites the shared cooldown timestamp.
	 */
	public function test_get_upe_enabled_payment_method_ids_preserves_test_failure_cooldown_across_live_fetch(): void {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();

		try {
			$settings = array_merge(
				$this->build_connected_test_mode_settings( 'yes' ),
				[
					'publishable_key'                           => 'pk_live_1234567890',
					'secret_key'                                => 'sk_live_1234567890',
					'connection_type'                           => 'connect',
					'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
					'express_checkout'                          => 'no',
				]
			);
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
			$this->reset_payment_method_configuration_state();

			$live_pmc         = (object) self::MOCK_CHILD_LIVE_PMC;
			$live_pmc->card   = (object) [ 'display_preference' => (object) [ 'value' => 'on' ] ];
			$live_pmc->klarna = (object) [ 'display_preference' => (object) [ 'value' => 'on' ] ];

			$api = $this->createMock( WC_Stripe_API::class );
			$api->expects( $this->exactly( 2 ) )
				->method( 'get_payment_method_configurations' )
				->willReturnOnConsecutiveCalls(
					new WP_Error( 'stripe_api_outage', 'Test mode failure' ),
					(object) [ 'data' => [ $live_pmc ] ]
				);
			WC_Stripe_API::set_instance( $api );

			$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );

			// Advance past the initial second so live mode writes a newer shared cooldown timestamp.
			$failed_at = time();
			while ( time() <= $failed_at ) {
				usleep( 50000 );
			}

			$settings['testmode'] = 'no';
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
			WC_Stripe_Payment_Method_Configurations::clear_in_memory_payment_method_configuration_cache();

			$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );

			$settings['testmode'] = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
			WC_Stripe_Payment_Method_Configurations::clear_in_memory_payment_method_configuration_cache();

			$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
			$this->assertSame( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( true ) );
		} finally {
			WC_Stripe_API::set_instance( null );
			$cleanup_settings             = WC_Stripe_Helper::get_stripe_settings();
			$cleanup_settings['testmode'] = 'no';
			WC_Stripe_Helper::update_main_stripe_settings( $cleanup_settings );
			WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();
			$cleanup_settings['testmode'] = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $cleanup_settings );
			$this->reset_payment_method_configuration_state();
			WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		}
	}

	/**
	 * Express checkout wallet availability falls back to stored settings on PMC failure and obeys valid PMCs when present.
	 *
	 * @dataProvider provide_express_checkout_fallback_cases
	 *
	 * @param string|null $express_checkout   Stored `express_checkout` value, or null to test the unset default.
	 * @param string      $response_kind      Scenario selector for the Stripe PMC response.
	 * @param bool        $expected_ece       Expected result of `is_express_checkout_enabled()`.
	 * @param array       $expected_methods   Expected result of `get_upe_enabled_payment_method_ids()`.
	 * @param string      $expected_pmc_state Expected `pmc_enabled` setting after the read.
	 */
	public function test_express_checkout_availability_on_pmc_failure_and_valid_pmc( ?string $express_checkout, string $response_kind, bool $expected_ece, array $expected_methods, string $expected_pmc_state ): void {
		$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'pmc_synthetic',
			function () use ( $gateway, $express_checkout, $response_kind, $expected_ece, $expected_methods, $expected_pmc_state ): void {
				$settings = WC_Stripe_Helper::get_stripe_settings();
				if ( null === $express_checkout ) {
					unset( $settings['express_checkout'] );
				} else {
					$settings['express_checkout'] = $express_checkout;
				}
				WC_Stripe_Helper::update_main_stripe_settings( $settings );

				if ( 'failure' === $response_kind ) {
					$api_response = new WP_Error( 'stripe_api_outage', 'Synthetic failure' );
				} elseif ( 'empty' === $response_kind ) {
					$api_response = (object) [ 'data' => [] ];
				} else {
					$pmc_without_wallets             = (object) self::MOCK_CHILD_TEST_PMC;
					$pmc_without_wallets->klarna     = (object) [ 'display_preference' => (object) [ 'value' => 'on' ] ];
					$pmc_without_wallets->apple_pay  = (object) [ 'display_preference' => (object) [ 'value' => 'off' ] ];
					$pmc_without_wallets->google_pay = (object) [ 'display_preference' => (object) [ 'value' => 'off' ] ];
					$api_response                    = (object) [ 'data' => [ $pmc_without_wallets ] ];
				}

				$api = $this->createMock( WC_Stripe_API::class );
				$api->expects( $this->once() )
					->method( 'get_payment_method_configurations' )
					->willReturn( $api_response );
				WC_Stripe_API::set_instance( $api );

				$this->assertSame( $expected_ece, $gateway->is_express_checkout_enabled() );
				$this->assertSame( $expected_methods, WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
				$this->assertSame( $expected_pmc_state, WC_Stripe_Helper::get_stripe_settings()['pmc_enabled'] ?? null );
			}
		);
	}

	/**
	 * Data provider for {@see test_express_checkout_availability_on_pmc_failure_and_valid_pmc()}.
	 *
	 * @return array
	 */
	public function provide_express_checkout_fallback_cases(): array {
		return [
			'pmc failure with express_checkout yes'                 => [
				'yes',
				'failure',
				true,
				[
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::KLARNA,
					WC_Stripe_Payment_Methods::APPLE_PAY,
					WC_Stripe_Payment_Methods::GOOGLE_PAY,
				],
				'yes',
			],
			'pmc failure with express_checkout no'                  => [
				'no',
				'failure',
				false,
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
				'yes',
			],
			'pmc failure with express_checkout unset'               => [
				null,
				'failure',
				true,
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
				'yes',
			],
			'valid pmc with wallets off overrides express_checkout' => [
				'yes',
				'wallets_off',
				false,
				[ WC_Stripe_Payment_Methods::KLARNA ],
				'yes',
			],
			'valid empty pmc disables sync and preserves wallets'   => [
				'yes',
				'empty',
				true,
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
				'no',
			],
		];
	}

	/**
	 * Optimized Checkout client parameters and method config fall back to saved UPE methods when PMC fetch fails.
	 *
	 * @dataProvider provide_ocs_checkout_pmc_fallback_cases
	 *
	 * @param string $response_kind             Scenario selector ('failure' or 'valid').
	 * @param bool   $expected_oc_shown         Expected `isOCEnabled` and `shouldShowOptimizedCheckout` values.
	 * @param array  $expected_config_keys      Expected keys in `paymentMethodsConfig`.
	 * @param string $expected_pmc_id           Expected `paymentMethodConfigurationId`, or empty when omitted.
	 */
	public function test_ocs_checkout_falls_back_from_oc_when_pmc_fetch_fails( string $response_kind, bool $expected_oc_shown, array $expected_config_keys, string $expected_pmc_id ): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'pmc_synthetic',
			function () use ( $response_kind, $expected_oc_shown, $expected_config_keys, $expected_pmc_id ): void {
				$settings                               = WC_Stripe_Helper::get_stripe_settings();
				$settings['enabled']                    = 'yes';
				$settings['express_checkout']           = 'no';
				$settings['optimized_checkout_element'] = 'yes';
				WC_Stripe_Helper::update_main_stripe_settings( $settings );

				$this->set_stripe_account_data(
					[
						'country'          => 'US',
						'default_currency' => 'usd',
					]
				);

				$api_response = 'failure' === $response_kind
					? new WP_Error( 'stripe_api_outage', 'Synthetic failure' )
					: (object) [ 'data' => [ $this->mirrored_configuration( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ] ) ] ];

				$api = $this->createMock( WC_Stripe_API::class );
				$api->method( 'get_payment_method_configurations' )->willReturn( $api_response );
				WC_Stripe_API::set_instance( $api );

				$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
					->setConstructorArgs( [] )
					->onlyMethods(
						[
							'get_return_url',
							'get_stripe_return_url',
							'is_changing_payment_method_for_subscription',
							'is_subscription_item_in_cart',
							'is_valid_optimized_checkout_page',
							'is_adaptive_pricing_supported',
							'get_excluded_payment_method_types',
						]
					)
					->getMock();
				$gateway->method( 'get_return_url' )->willReturn( '' );
				$gateway->method( 'get_stripe_return_url' )->willReturn( '' );
				$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( false );
				$gateway->method( 'is_subscription_item_in_cart' )->willReturn( false );
				$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( true );
				$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( true );
				$gateway->method( 'get_excluded_payment_method_types' )->willReturn( [] );

				$original_total = WC()->cart->get_total( '' );
				WC()->cart->set_total( '20.00' );

				try {
					$params = $gateway->javascript_params();

					$this->assertSame( $expected_oc_shown, $gateway->should_render_optimized_checkout() );
					$this->assertSame( $expected_oc_shown, $params['isOCEnabled'] );
					$this->assertSame( $expected_oc_shown, $params['shouldShowOptimizedCheckout'] );
					$this->assertSame( $expected_oc_shown, $params['isAdaptivePricingEnabled'] );
					$this->assertSame( $expected_config_keys, array_keys( $params['paymentMethodsConfig'] ) );

					if ( '' === $expected_pmc_id ) {
						$this->assertArrayNotHasKey( 'paymentMethodConfigurationId', $params );
					} else {
						$this->assertSame( $expected_pmc_id, $params['paymentMethodConfigurationId'] );
					}
				} finally {
					WC()->cart->set_total( $original_total );
				}
			}
		);
	}

	/**
	 * Data provider for {@see test_ocs_checkout_falls_back_from_oc_when_pmc_fetch_fails()}.
	 *
	 * @return array
	 */
	public function provide_ocs_checkout_pmc_fallback_cases(): array {
		return [
			'pmc failure disables OC client params and falls back to saved methods' => [
				'failure',
				false,
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
				'',
			],
			'valid pmc enables OC client params with configuration ID'              => [
				'valid',
				true,
				[ WC_Stripe_UPE_Payment_Method_OC::STRIPE_ID ],
				self::MOCK_CHILD_TEST_PMC['id'],
			],
		];
	}

	/**
	 * Deferred-intent sub-methods remain available at checkout when OC is enabled but PMC fetch fails.
	 */
	public function test_deferred_intent_method_stays_available_when_pmc_fetch_fails(): void {
		$this->run_with_connected_test_pmc_state(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'pmc_synthetic',
			function (): void {
				$settings                               = WC_Stripe_Helper::get_stripe_settings();
				$settings['enabled']                    = 'yes';
				$settings['express_checkout']           = 'no';
				$settings['optimized_checkout_element'] = 'yes';
				WC_Stripe_Helper::update_main_stripe_settings( $settings );

				$this->set_stripe_account_data(
					[
						'country'          => 'US',
						'default_currency' => 'usd',
					]
				);

				$api = $this->createMock( WC_Stripe_API::class );
				$api->method( 'get_payment_method_configurations' )->willReturn( new WP_Error( 'stripe_api_outage', 'Synthetic failure' ) );
				WC_Stripe_API::set_instance( $api );

				$stripe             = WC_Stripe::get_instance();
				$gateway_reflection = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
				$gateway_reflection->setAccessible( true );
				$previous_gateway = $gateway_reflection->getValue( $stripe );
				$gateway_reflection->setValue( $stripe, null );

				$original_total = WC()->cart->get_total( '' );
				WC()->cart->set_total( '20.00' );

				try {
					$main_gateway = $stripe->get_main_stripe_gateway();
					$klarna       = $main_gateway->payment_methods[ WC_Stripe_Payment_Methods::KLARNA ];

					$this->assertInstanceOf( WC_Stripe_OCS_Payment_Gateway::class, $main_gateway );
					$this->assertTrue( $klarna->is_available() );
				} finally {
					WC()->cart->set_total( $original_total );
					$gateway_reflection->setValue( $stripe, $previous_gateway );
				}
			}
		);
	}

	/**
	 * OCS PaymentIntents avoid unrestricted automatic_payment_methods when PMC fetch fails and send explicit saved types.
	 *
	 * @dataProvider provide_ocs_payment_intent_pmc_fallback_cases
	 *
	 * @param string      $response_kind          Scenario selector ('failure' or 'valid').
	 * @param array       $saved_methods          Saved UPE methods in local settings.
	 * @param string      $payment_method_id      Submitted payment method ID, or empty for confirmation token.
	 * @param string      $confirmation_token     Submitted confirmation token, or empty for payment method.
	 * @param bool        $expect_automatic       Whether `automatic_payment_methods` should be sent.
	 * @param array|null  $expected_method_types  Expected explicit `payment_method_types`, or null when automatic.
	 * @param string|null $expected_pmc_id        Expected `payment_method_configuration`, or null when omitted.
	 */
	public function test_ocs_payment_intent_disables_automatic_payment_methods_when_pmc_fetch_fails(
		string $response_kind,
		array $saved_methods,
		string $payment_method_id,
		string $confirmation_token,
		bool $expect_automatic,
		?array $expected_method_types,
		?string $expected_pmc_id
	): void {
		$this->run_with_connected_test_pmc_state(
			$saved_methods,
			'pmc_synthetic',
			function () use ( $response_kind, $saved_methods, $payment_method_id, $confirmation_token, $expect_automatic, $expected_method_types, $expected_pmc_id ): void {
				$settings                               = WC_Stripe_Helper::get_stripe_settings();
				$settings['enabled']                    = 'yes';
				$settings['express_checkout']           = 'no';
				$settings['optimized_checkout_element'] = 'yes';
				WC_Stripe_Helper::update_main_stripe_settings( $settings );

				$api_response = 'failure' === $response_kind
					? new WP_Error( 'stripe_api_outage', 'Synthetic failure' )
					: (object) [ 'data' => [ $this->mirrored_configuration( $saved_methods ) ] ];

				$api = $this->createMock( WC_Stripe_API::class );
				$api->method( 'get_payment_method_configurations' )->willReturn( $api_response );
				WC_Stripe_API::set_instance( $api );

				$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
					->setConstructorArgs( [] )
					->onlyMethods( [ 'get_stripe_customer_id', 'has_subscription', 'get_excluded_payment_method_types' ] )
					->getMock();
				$gateway->method( 'get_stripe_customer_id' )->willReturn( 'cus_mock' );
				$gateway->method( 'has_subscription' )->willReturn( false );
				$gateway->method( 'get_excluded_payment_method_types' )->willReturn( [] );

				$controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
					->onlyMethods( [ 'get_gateway' ] )
					->getMock();
				$controller->method( 'get_gateway' )->willReturn( $gateway );

				$order = WC_Helper_Order::create_order();
				$_POST = [
					'payment_method'               => WC_Stripe_UPE_Payment_Gateway::ID,
					'wc-stripe-payment-method'     => $payment_method_id,
					'wc-stripe-confirmation-token' => $confirmation_token,
				];

				$requests    = [];
				$http_filter = static function ( $preempt, $args, $url ) use ( &$requests, $payment_method_id ) {
					if ( '' !== $payment_method_id && false !== strpos( $url, 'payment_methods/' . $payment_method_id ) ) {
						return [
							'response' => [ 'code' => 200 ],
							'body'     => wp_json_encode(
								[
									'id'     => $payment_method_id,
									'object' => 'payment_method',
									'type'   => WC_Stripe_Payment_Methods::CARD,
								]
							),
						];
					}
					if ( WC_Stripe_API::ENDPOINT . 'payment_intents' === $url ) {
						$requests[] = $args['body'];
						return [
							'response' => [ 'code' => 200 ],
							'body'     => wp_json_encode( [ 'id' => 'pi_mock' ] ),
						];
					}
					return $preempt;
				};
				add_filter( 'pre_http_request', $http_filter, 10, 3 );

				try {
					$prepare = new ReflectionMethod( WC_Stripe_UPE_Payment_Gateway::class, 'prepare_payment_information_from_request' );
					$prepare->setAccessible( true );
					$controller->create_and_confirm_payment_intent( $prepare->invoke( $gateway, $order ) );
				} finally {
					remove_filter( 'pre_http_request', $http_filter, 10 );
					$_POST = [];
				}

				$this->assertCount( 1, $requests );
				$request = $requests[0];

				if ( $expect_automatic ) {
					$this->assertArrayHasKey( 'automatic_payment_methods', $request );
					$this->assertArrayNotHasKey( 'payment_method_types', $request );
					$this->assertSame( $expected_pmc_id, $request['payment_method_configuration'] ?? null );
				} else {
					$this->assertArrayNotHasKey( 'automatic_payment_methods', $request );
					$this->assertArrayNotHasKey( 'payment_method_configuration', $request );
					$this->assertSame( $expected_method_types, $request['payment_method_types'] );
				}
			}
		);
	}

	/**
	 * Data provider for {@see test_ocs_payment_intent_disables_automatic_payment_methods_when_pmc_fetch_fails()}.
	 *
	 * @return array
	 */
	public function provide_ocs_payment_intent_pmc_fallback_cases(): array {
		return [
			'pmc failure with payment method omits automatic_payment_methods'            => [
				'failure',
				[ WC_Stripe_Payment_Methods::CARD ],
				'pm_test_card',
				'',
				false,
				[ WC_Stripe_Payment_Methods::CARD ],
				null,
			],
			'pmc failure with confirmation token keeps card and link from saved methods' => [
				'failure',
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ],
				'',
				'ctoken_mock',
				false,
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ],
				null,
			],
			'valid pmc sends automatic_payment_methods with configuration ID'            => [
				'valid',
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
				'pm_test_card',
				'',
				true,
				null,
				self::MOCK_CHILD_TEST_PMC['id'],
			],
		];
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
			(object) [ 'data' => [ (object) self::MOCK_CHILD_TEST_PMC ] ]
		);

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		$instance_property->setValue( null, null );
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );

		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	/**
	 * Recovery saves the PMC selections used by checkout, including after a rejected update.
	 *
	 * @dataProvider recovery_selections_provider
	 */
	public function test_refresh_pmc_availability_recovers_from_no_state( array $local_methods, array $expected_methods, bool $update_fails ): void {
		$settings = $this->build_connected_test_mode_settings( 'no' );
		$settings['upe_checkout_experience_accepted_payments'] = $local_methods;
		$settings['express_checkout']                          = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$this->reset_payment_method_configuration_state();

		$api = $this->createMock( WC_Stripe_API::class );
		$api->expects( $this->once() )
			->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => [ $this->mirrored_configuration( [ 'klarna', 'apple_pay' ] ) ] ] );
		$api->expects( $local_methods ? $this->once() : $this->never() )
			->method( 'update_payment_method_configurations' )
			->willReturn(
				$update_fails
					? (object) [ 'error' => (object) [ 'message' => 'Update rejected' ] ]
					: $this->mirrored_configuration( [ 'card', 'klarna', 'apple_pay' ] )
			);
		WC_Stripe_API::set_instance( $api );

		WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();

		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $updated_settings['pmc_enabled'] );
		$this->assertSame( $expected_methods, $updated_settings['upe_checkout_experience_accepted_payments'] );
		$this->assertSame( 'yes', $updated_settings['express_checkout'] );
		$this->assertSame(
			$local_methods && ! $update_fails ? [ 'card', 'klarna', 'apple_pay' ] : [ 'klarna', 'apple_pay' ],
			WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids()
		);
		$this->assertSame( $updated_settings, WC_Stripe::get_instance()->get_settings() );
	}

	public function recovery_selections_provider(): array {
		return [
			'updated PMC'      => [ [ 'card' ], [ 'card', 'klarna' ], false ],
			'no local methods' => [ [], [ 'klarna' ], false ],
			'rejected update'  => [ [ 'card' ], [ 'klarna' ], true ],
		];
	}

	/**
	 * Usable PMC returned with pmc_enabled='yes': no-op (still 'yes').
	 */
	public function test_refresh_pmc_availability_noop_when_already_yes() {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );

		$instance_property = $this->mock_get_payment_method_configurations_response(
			(object) [ 'data' => [ (object) self::MOCK_CHILD_TEST_PMC ] ]
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
			(object) self::MOCK_CHILD_TEST_PMC,
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

	private function mirrored_configuration( array $enabled ): object {
		$configuration = (object) self::MOCK_CHILD_TEST_PMC;
		foreach ( [ 'card', 'klarna', 'apple_pay', 'google_pay', 'unsupported_method' ] as $id ) {
			$configuration->$id = (object) [ 'display_preference' => (object) [ 'value' => in_array( $id, $enabled, true ) ? 'on' : 'off' ] ];
		}
		return $configuration;
	}

	/**
	 * Reading or saving a PMC updates the fallback only when the effective selections differ.
	 *
	 * @dataProvider mirrored_configuration_provider
	 */
	public function test_pmc_mirrors_changed_selections( string $path, array $remote, array $expected, string $wallets, bool $active = true ): void {
		$gateway  = WC_Stripe::get_instance()->get_main_stripe_gateway();
		$settings = array_merge(
			$this->build_connected_test_mode_settings( 'yes' ),
			[
				'upe_checkout_experience_accepted_payments' => [ 'card' ],
				'express_checkout'                          => 'no',
				'saved_cards'                               => 'yes',
				'express_checkout_button_locations'         => [ 'checkout' ],
			]
		);
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$this->reset_payment_method_configuration_state();
		$configuration         = $this->mirrored_configuration( $remote );
		$configuration->active = $active;
		$api                   = $this->createMock( WC_Stripe_API::class );
		$api->expects( in_array( $path, [ 'fetch', 'refresh' ], true ) ? $this->once() : $this->never() )
			->method( 'get_payment_method_configurations' )
			->willReturn( (object) [ 'data' => [ $configuration ] ] );
		$api->expects( 'update' === $path ? $this->once() : $this->never() )
			->method( 'update_payment_method_configurations' )
			->willReturn( $configuration );
		WC_Stripe_API::set_instance( $api );
		if ( in_array( $path, [ 'cache', 'update' ], true ) ) {
			WC_Stripe_Database_Cache::set(
				WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY,
				'cache' === $path ? $configuration : $this->mirrored_configuration( [ 'card' ] )
			);
		}

		$writes      = 0;
		$count_write = function () use ( &$writes ) {
			++$writes;
		};
		add_action( 'update_option_woocommerce_stripe_settings', $count_write );
		try {
			if ( 'update' === $path ) {
				$this->assertTrue( WC_Stripe_Payment_Method_Configurations::update_payment_method_configuration( $remote, [ 'card', 'klarna', 'apple_pay', 'google_pay' ] ) );
			} else {
				if ( 'refresh' === $path ) {
					WC_Stripe_Payment_Method_Configurations::refresh_pmc_availability();
				}
				WC_Stripe_Payment_Method_Configurations::get_configuration_id();
				WC_Stripe_Payment_Method_Configurations::get_configuration_id();
			}
			$this->assertSame( [ 'card' ] === $expected && 'no' === $wallets ? 0 : 1, $writes );
		} finally {
			remove_action( 'update_option_woocommerce_stripe_settings', $count_write );
		}

		$gateway->update_option( 'saved_cards', 'no' );
		$updated = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $expected, $updated['upe_checkout_experience_accepted_payments'] );
		$this->assertSame( $wallets, $updated['express_checkout'] );
		$this->assertSame( [ 'checkout' ], $updated['express_checkout_button_locations'] );
		$this->assertSame( 'yes', $updated['pmc_enabled'] );

		$updated['pmc_enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $updated );
		$this->assertSame( $expected ? $expected : [ 'card' ], WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids() );
	}

	public function mirrored_configuration_provider(): array {
		$selections = [
			'unchanged'   => [ [ 'card' ], [ 'card' ], 'no' ],
			'methods'     => [ [ 'card', 'klarna' ], [ 'card', 'klarna' ], 'no' ],
			'Apple only'  => [ [ 'card', 'apple_pay' ], [ 'card' ], 'yes' ],
			'Google only' => [ [ 'card', 'google_pay' ], [ 'card' ], 'yes' ],
			'both'        => [ [ 'card', 'apple_pay', 'google_pay' ], [ 'card' ], 'yes' ],
			'empty'       => [ [], [], 'no' ],
			'unsupported' => [ [ 'card', 'unsupported_method' ], [ 'card' ], 'no' ],
			'inactive'    => [ [ 'card', 'klarna' ], [ 'card', 'klarna' ], 'no', false ],
		];
		$cases      = [];
		foreach ( [ 'fetch', 'cache', 'refresh', 'update' ] as $path ) {
			foreach ( $selections as $name => $selection ) {
				$cases[ $path . ' ' . $name ] = array_merge( [ $path ], $selection );
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider unmirrored_configuration_provider
	 */
	public function test_pmc_keeps_local_selections_when_sync_is_not_ready( string $pmc_enabled, array $overrides ): void {
		$settings = array_merge(
			$this->build_connected_test_mode_settings( $pmc_enabled ),
			[
				'upe_checkout_experience_accepted_payments' => [ 'card', 'klarna' ],
				'express_checkout'                          => 'yes',
			]
		);
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Database_Cache::set(
			WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY,
			(object) array_merge( (array) $this->mirrored_configuration( [] ), $overrides )
		);

		WC_Stripe_Payment_Method_Configurations::get_configuration_id();

		$this->assertSame( $settings, WC_Stripe_Helper::get_stripe_settings() );
	}

	public function unmirrored_configuration_provider(): array {
		return [
			'sync disabled'     => [ 'no', [] ],
			'migration pending' => [ '', [] ],
			'wrong mode'        => [ 'yes', [ 'livemode' => true ] ],
			'incomplete'        => [ 'yes', [ 'card' => null ] ],
			'API error'         => [ 'yes', [ 'error' => (object) [ 'message' => 'Unavailable' ] ] ],
		];
	}

	public function test_pmc_persists_a_missing_wallet_setting(): void {
		WC_Stripe_Helper::update_main_stripe_settings( $this->build_connected_test_mode_settings( 'yes' ) );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings['upe_checkout_experience_accepted_payments'] = [ 'card' ];
		unset( $settings['express_checkout'] );
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$this->assertArrayNotHasKey( 'express_checkout', WC_Stripe_Helper::get_stripe_settings() );
		WC_Stripe_Database_Cache::set(
			WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY,
			$this->mirrored_configuration( [ 'card', 'apple_pay' ] )
		);

		WC_Stripe_Payment_Method_Configurations::get_configuration_id();

		$this->assertSame( 'yes', WC_Stripe_Helper::get_stripe_settings()['express_checkout'] );
	}

	/**
	 * @dataProvider provide_record_payment_method_settings_event_scenarios
	 *
	 * @param string $testmode             Value of the `testmode` setting.
	 * @param int    $expected_is_test_mode Expected `is_test_mode` event property.
	 */
	public function test_record_payment_method_settings_event_records_properties( string $testmode, int $expected_is_test_mode ): void {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		WC_Stripe_Helper::update_main_stripe_settings( array_merge( $initial_settings, [ 'testmode' => $testmode ] ) );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->start_capturing_tracks_events();

		try {
			WC_Stripe_Payment_Method_Configurations::record_payment_method_settings_event(
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
				[ WC_Stripe_Payment_Methods::SEPA_DEBIT ]
			);
		} finally {
			$this->stop_capturing_tracks_events();
			WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		}

		$expected_events = [
			'wcadmin_wcstripe_payment_method_settings_enabled'  => [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::KLARNA ],
			'wcadmin_wcstripe_payment_method_settings_disabled' => [ WC_Stripe_Payment_Methods::SEPA_DEBIT ],
		];

		foreach ( $expected_events as $event_name => $expected_payment_methods ) {
			$events = $this->get_captured_tracks_event_properties( $event_name );

			$this->assertSame( $expected_payment_methods, array_column( $events, 'payment_method' ), $event_name );
			foreach ( $events as $event ) {
				$this->assertSame( $expected_is_test_mode, $event['is_test_mode'], $event_name );
				$this->assertSame( WC_STRIPE_VERSION, $event['stripe_version'], $event_name );
			}
		}
	}

	/**
	 * Data provider for {@see test_record_payment_method_settings_event_records_properties()}.
	 *
	 * @return array
	 */
	public function provide_record_payment_method_settings_event_scenarios(): array {
		return [
			'test mode' => [ 'yes', 1 ],
			'live mode' => [ 'no', 0 ],
		];
	}
}
