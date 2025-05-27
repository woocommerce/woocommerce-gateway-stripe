<?php
/**
 * Class Migrate_Payment_Methods_From_Db_To_Pmc_Test
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Migrate_Payment_Methods_From_DB_To_PMC_Test unit tests.
 */
class Migrate_Payment_Methods_From_DB_To_PMC_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * @var WC_Stripe_Payment_Method_Configurations
	 */
	private $pmc;

	public function set_up() {
		parent::set_up();

		$this->pmc = new WC_Stripe_Payment_Method_Configurations();

		// Set up test connection type to enable PMC
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_connection_type'] = 'connect';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	public function test_migration_not_executed_when_pmc_enabled_is_yes() {
		// Set up environment with pmc_enabled = 'yes'
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Mock payment method configurations to verify they are not updated
		$this->mock_payment_method_configurations( [ 'card' ], [] );

		// Migration should not update payment method configurations
		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc();

		// Verify payment method configurations were not updated
		$this->stripe_api->expects( $this->never() )
			->method( 'update_payment_method_configurations' );
	}

	public function test_migration_executed_when_pmc_enabled_is_not_set() {
		// Set pmc_enabled to '' to trigger migration
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_connection_type']                      = 'connect';
		$stripe_settings['payment_request']                           = 'yes';
		$stripe_settings['upe_checkout_experience_accepted_payments'] = [ 'link', 'sepa_debit' ];
		$stripe_settings['pmc_enabled']                               = '';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [], [ 'link', 'sepa_debit', 'google_pay', 'apple_pay' ] );
		$this->expect_payment_method_configurations_update( [ 'link', 'sepa_debit', 'google_pay', 'apple_pay' ], [] );

		// Execute migration
		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc();

		// Verify pmc_enabled is set to 'yes'
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayHasKey( 'pmc_enabled', $updated_settings );
		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	public function test_migration_handles_empty_enabled_payment_methods() {
		// Set up environment with pmc_enabled not set
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = '';
		$stripe_settings['upe_checkout_experience_accepted_payments'] = [];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [], [] );
		$this->expect_payment_method_configurations_update( [] );

		// Execute migration
		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc();

		// Verify pmc_enabled is set to 'yes'
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayHasKey( 'pmc_enabled', $updated_settings );
		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	public function test_migration_sets_default_payment_method_order() {
		// Set up environment with pmc_enabled not set and no payment method order
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_connection_type'] = 'connect';
		$stripe_settings['pmc_enabled'] = '';
		$stripe_settings['stripe_upe_payment_method_order'] = '';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [], [] );

		// Execute migration
		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc();

		// Verify payment method order is set to default
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayHasKey( 'stripe_upe_payment_method_order', $updated_settings );
		$this->assertEquals( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ), $updated_settings['stripe_upe_payment_method_order'] );
	}

	public function test_migration_preserves_existing_payment_method_order() {
		// Set up environment with pmc_enabled not set and existing payment method order
		$existing_order = [ 'card', 'sepa_debit', 'ideal' ];
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_connection_type'] = 'connect';
		$stripe_settings['pmc_enabled'] = '';
		$stripe_settings['stripe_upe_payment_method_order'] = $existing_order;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [], [] );

		// Execute migration
		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc();

		// Verify payment method order is preserved
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayHasKey( 'stripe_upe_payment_method_order', $updated_settings );
		$this->assertEquals( $existing_order, $updated_settings['stripe_upe_payment_method_order'] );
	}
}
