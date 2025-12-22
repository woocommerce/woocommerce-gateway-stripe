<?php

namespace WooCommerce\Stripe\Tests;

use ReflectionClass;
use WC_Stripe_Helper;
use WC_Stripe_API;
use WC_Stripe_Payment_Method_Configurations;

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

		// Call the method
		$method->invoke( null );

		// Get the updated settings
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();

		// Verify pmc_enabled is not set to 'no'
		$this->assertNotEquals( 'no', $updated_settings['pmc_enabled'] ?? null );

		// Restore original settings and API instance
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		$property->setValue( null, null );
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
			'no_pmcs_returned'                             => [
				'mock_pmc_response'        => [],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_child_pmc_returned'                 => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_child_pmc_returned_after_fallback_pmc_id_is_set' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'only_live_other_platform_child_no_pmc_returned' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_LIVE_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_other_platform_child_no_pmc_returned_after_fallback_pmc_id_is_set' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_LIVE_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => false,
			],
			'only_test_child_pmc_returned'                 => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_child_pmc_returned_after_fallback_pmc_id_is_set' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => true,
			],
			'only_test_other_platform_child_no_pmc_returned' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_TEST_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_other_platform_child_no_pmc_returned_after_fallback_pmc_id_is_set' => [
				'mock_pmc_response'        => [ (object) self::MOCK_CHILD_OTHER_TEST_PMC ],
				'expected_pmc_id'          => null,
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => 'pmc_id_test',
				'is_test_mode'             => true,
			],
			'only_live_non_child_default_pmc_returned'     => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => false,
			],
			'only_live_non_child_non_default_pmc_returned' => [
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
			'only_live_non_child_default_pmc_returned_after_fallback_pmc_id_is_set_to_same_pmc' => [
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
			'only_test_non_child_default_pmc_returned'     => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_DEFAULT_PMC ],
				'expected_pmc_id'          => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'expected_fallback_pmc_id' => self::MOCK_TEST_NON_CHILD_DEFAULT_PMC['id'],
				'initial_fallback_pmc_id'  => null,
				'is_test_mode'             => true,
			],
			'only_test_non_child_non_default_pmc_returned' => [
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
			'only_test_non_child_default_pmc_returned_after_fallback_pmc_id_is_set_to_same_pmc' => [
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
			'live_non_child_default_active_pmc_returned_with_inactive_and_other_child_pmc' => [
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
			'fallback_pmc_ignored_when_live_child_pmc_is_returned' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'default_pmc_ignored_when_live_child_pmc_is_returned' => [
				'mock_pmc_response'        => [ (object) self::MOCK_LIVE_NON_CHILD_DEFAULT_PMC, (object) self::MOCK_CHILD_LIVE_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_LIVE_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_LIVE_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => false,
			],
			'fallback_pmc_ignored_when_test_child_pmc_is_returned' => [
				'mock_pmc_response'        => [ (object) self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC, (object) self::MOCK_CHILD_TEST_PMC ],
				'expected_pmc_id'          => self::MOCK_CHILD_TEST_PMC['id'],
				'expected_fallback_pmc_id' => null,
				'initial_fallback_pmc_id'  => self::MOCK_TEST_NON_CHILD_NON_DEFAULT_PMC['id'],
				'is_test_mode'             => true,
			],
			'default_pmc_ignored_when_test_child_pmc_is_returned' => [
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
			'existing_fallback_pmc_returned_when_multiple_non_child_default_pmcs_returned' => [
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
			'PMC is disabled - live'                       => [
				'is_test_mode'              => false,
				'pmc_enabled'               => false,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'PMC is disabled - test'                       => [
				'is_test_mode'              => true,
				'pmc_enabled'               => false,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Account is not connected - live'              => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => false,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Account is not connected - test'              => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => false,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Does not have a primary configuration - live' => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Does not have a primary configuration - test' => [
				'is_test_mode'              => true,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => false,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Has a primary configuration with no id - live' => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => null,
				'from_cache'                => false,
				'expected_configuration_id' => null,
			],
			'Has a primary configuration with no id - test' => [
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
			'Has a primary configuration with an id - live' => [
				'is_test_mode'              => false,
				'pmc_enabled'               => true,
				'account_connected'         => true,
				'has_primary_configuration' => true,
				'primary_configuration_id'  => 'pmc_12345',
				'from_cache'                => false,
				'expected_configuration_id' => 'pmc_12345',
			],
			'Has a primary configuration with an id - test' => [
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
}
