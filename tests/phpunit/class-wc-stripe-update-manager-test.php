<?php

/**
 * These tests make assertions against the WC_Stripe_Update_Manager class.
 */
class WC_Stripe_Update_Manager_Test extends WP_UnitTestCase {

	/**
	 * Test that {@see WC_Stripe_Update_Manager::get_update_functions()} returns the expected functions.
	 */
	public function test_get_update_functions_returns_expected_functions(): void {
		$update_manager = WC_Stripe_Update_Manager::get_instance();

		$update_manager_reflection           = new ReflectionClass( WC_Stripe_Update_Manager::class );
		$update_manager_get_update_functions = $update_manager_reflection->getMethod( 'get_update_functions' );
		$update_manager_get_update_functions->setAccessible( true );
		$result = $update_manager_get_update_functions->invoke( $update_manager );

		$expected_functions = [
			[ WC_Stripe_Admin_Notices::class, 'check_update_notices', 'static' ],
			[ Allowed_Payment_Request_Button_Types_Update::class, 'maybe_migrate', 'instance' ],
			[ Migrate_Payment_Request_Data_To_Express_Checkout_Data::class, 'maybe_migrate', 'instance' ],
			[ Sepa_Tokens_For_Other_Methods_Settings_Update::class, 'maybe_migrate', 'instance' ],
			[ WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update::class, 'maybe_migrate', 'instance' ],
			[ WC_Stripe_OCS_AP_Default_On_Update::class, 'maybe_migrate', 'instance' ],
		];

		$this->assertCount( count( $expected_functions ), $result );

		foreach ( $expected_functions as $expected_function ) {
			$expected_target_type = $expected_function[2];
			if ( 'static' === $expected_target_type ) {
				$this->assertContainsEquals( [ $expected_function[0], $expected_function[1] ], $result );
			} else {
				$expected_target_class  = $expected_function[0];
				$expected_target_method = $expected_function[1];
				// Use array_filter as array_any() and array_find() are only shimmed in recent version of WordPress.
				$item_results = array_filter(
					$result,
					function ( $result_callback ) use ( $expected_target_class, $expected_target_method ) {
						if ( ! is_array( $result_callback ) || count( $result_callback ) !== 2 ) {
							return false;
						}
						return is_object( $result_callback[0] ) &&
							$result_callback[0] instanceof $expected_target_class &&
							$expected_target_method === $result_callback[1];
					}
				);
				$this->assertCount( 1, $item_results );
			}
		}
	}

	/**
	 * Test that {@see WC_Stripe_Update_Manager::run_update_checks()} runs all the functions returned
	 * from {@see WC_Stripe_Update_Manager::get_update_functions()}.
	 */
	public function test_run_run_update_checks_runs_callbacks_from_get_update_functions(): void {
		$mock_allowed_payment_request_button_types_update = $this->createMock( Allowed_Payment_Request_Button_Types_Update::class );

		$mock_migrate_payment_request_data_to_express_checkout_data = $this->createMock( Migrate_Payment_Request_Data_To_Express_Checkout_Data::class );

		$mock_sepa_tokens_for_other_methods_settings_update = $this->createMock( Sepa_Tokens_For_Other_Methods_Settings_Update::class );

		$mock_callbacks        = [
			[ $mock_allowed_payment_request_button_types_update, 'maybe_migrate' ],
			[ $mock_migrate_payment_request_data_to_express_checkout_data, 'maybe_migrate' ],
			[ $mock_sepa_tokens_for_other_methods_settings_update, 'maybe_migrate' ],
		];
		$mock_previous_version = '10.4.0';

		$mock_allowed_payment_request_button_types_update->expects( $this->once() )
			->method( 'maybe_migrate' )
			->with( $mock_previous_version );
		$mock_migrate_payment_request_data_to_express_checkout_data->expects( $this->once() )
			->method( 'maybe_migrate' )
			->with( $mock_previous_version );
		$mock_sepa_tokens_for_other_methods_settings_update->expects( $this->once() )
			->method( 'maybe_migrate' )
			->with( $mock_previous_version );

		$upgrade_manager = $this->getMockBuilder( WC_Stripe_Update_Manager::class )
			->onlyMethods( [ 'get_update_functions' ] )
			->disableOriginalConstructor()
			->getMock();
		$upgrade_manager->expects( $this->once() )
			->method( 'get_update_functions' )
			->willReturn( $mock_callbacks );

		$action_called = false;
		add_action(
			'woocommerce_stripe_updated',
			function () use ( &$action_called ) {
				$action_called = true;
			}
		);

		$upgrade_manager_instance = null;

		try {
			$upgrade_manager_reflection = new ReflectionClass( WC_Stripe_Update_Manager::class );
			$upgrade_manager_instance   = $upgrade_manager_reflection->getProperty( 'instance' );
			$upgrade_manager_instance->setAccessible( true );
			$upgrade_manager_instance->setValue( null, $upgrade_manager );

			WC_Stripe_Update_Manager::run_update_checks( $mock_previous_version );

			$this->assertTrue( $action_called );
		} finally {
			if ( $upgrade_manager_instance ) {
				$upgrade_manager_instance->setValue( null, null );
			}
		}
	}
}
