<?php

/**
 * Class Migrate_Payment_Methods_From_Db_To_Pmc_Test
 *
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

		// Set up test connection info to enable PMC
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_publishable_key'] = 'pk_test_1234567890';
		$stripe_settings['test_secret_key']      = 'sk_test_1234567890';
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

	/**
	 * Test WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc() when pmc_enabled is 'yes' and force_migration is true.
	 *
	 * @see WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc()
	 * @return void
	 */
	public function test_migration_executed_when_pmc_enabled_is_yes_and_force_migration_is_true() {
		// Set up environment with pmc_enabled = 'yes'
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'yes';
		$stripe_settings['upe_checkout_experience_accepted_payments'] = [ 'card' ];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [], [ 'card', 'link' ] );
		$this->expect_payment_method_configurations_update( [ 'card' ], [ 'link' ] );

		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc( true );

		// Verify pmc_enabled is set to 'yes'
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayHasKey( 'pmc_enabled', $updated_settings );
		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}

	public function test_migration_executed_when_pmc_enabled_is_not_set() {
		// Set pmc_enabled to '' to trigger migration
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_connection_type']                      = 'connect';
		$stripe_settings['express_checkout']                          = 'yes';
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

	/**
	 * Provide test cases for {@see test_migration_executed_and_amazon_pay_enabled_correctly()}.
	 */
	public function provide_test_migration_executed_and_amazon_pay_enabled_correctly_tests(): array {
		return [
			'Auto-enable undefined, US + USD, expect no Amazon Pay' => [ null, 'US', 'USD', false ],
			'Auto-enable on, US + USD, expect Amazon Pay'           => [ true, 'US', 'USD', true ],
			'Auto-enable off, US + USD, expect no Amazon Pay'       => [ false, 'US', 'USD', false ],
			'Auto-enable on, CA + USD, expect no Amazon Pay'        => [ true, 'CA', 'USD', false ],
			'Auto-enable on, GB + GBP, expect Amazon Pay'           => [ true, 'GB', 'GBP', true ],
			'Auto-enable on, DE + EUR, expect Amazon Pay'           => [ true, 'DE', 'EUR', true ],
			'Auto-enable on, GR + EUR, expect no Amazon Pay'        => [ true, 'GR', 'EUR', false ],
			'Auto-enable on, GB + ZAR, expect Amazon Pay'           => [ true, 'GB', 'ZAR', true ],
		];
	}

	/**
	 * Test the migration executed and Amazon Pay was enabled as expected.
	 *
	 * @param ?bool  $should_auto_enable_amazon_pay Whether Amazon Pay should be auto-enabled.
	 * @param string $account_country               The country code for the Stripe account.
	 * @param string $store_currency                The currency of the store.
	 * @param bool   $expect_amazon_pay             Whether Amazon Pay should be expected.
	 * @dataProvider provide_test_migration_executed_and_amazon_pay_enabled_correctly_tests
	 */
	public function test_migration_executed_and_amazon_pay_enabled_correctly( ?bool $should_auto_enable_amazon_pay, string $account_country, string $store_currency, bool $expect_amazon_pay ) {
		// Set pmc_enabled to '' to trigger migration
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['test_connection_type']                      = 'connect';
		$stripe_settings['express_checkout']                          = 'yes';
		$stripe_settings['upe_checkout_experience_accepted_payments'] = [ 'link', 'sepa_debit' ];
		$stripe_settings['pmc_enabled']                               = '';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->set_stripe_account_data( [ 'country' => $account_country ] );

		// Initialize the Amazon Pay auto-on option
		if ( null === $should_auto_enable_amazon_pay ) {
			delete_option( 'wc_stripe_amazon_pay_default_on' );
		} else {
			update_option( 'wc_stripe_amazon_pay_default_on', $should_auto_enable_amazon_pay ? 'yes' : 'no' );
		}

		$mock_currency = function () use ( $store_currency ) {
			return $store_currency;
		};
		add_filter( 'woocommerce_currency', $mock_currency );

		$disabled_method_ids = [ 'link', 'sepa_debit', 'google_pay', 'apple_pay', 'amazon_pay' ];
		$this->mock_payment_method_configurations( [], $disabled_method_ids );

		$expected_payment_methods     = [ 'link', 'sepa_debit', 'google_pay', 'apple_pay' ];
		$expected_disabled_method_ids = [];
		if ( $expect_amazon_pay ) {
			$expected_payment_methods[] = 'amazon_pay';
		} else {
			$expected_disabled_method_ids[] = 'amazon_pay';
		}

		$this->expect_payment_method_configurations_update( $expected_payment_methods, $expected_disabled_method_ids );

		// Execute migration
		$this->pmc->maybe_migrate_payment_methods_from_db_to_pmc();

		remove_filter( 'woocommerce_currency', $mock_currency );

		// Verify pmc_enabled is set to 'yes'
		$updated_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayHasKey( 'pmc_enabled', $updated_settings );
		$this->assertEquals( 'yes', $updated_settings['pmc_enabled'] );
	}
}
