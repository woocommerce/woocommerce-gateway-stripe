<?php

/**
 * Class WC_Stripe_Smart_Payment_Method_Defaults tests.
 */
class WC_Stripe_Smart_Payment_Method_Defaults_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * @var WC_Stripe_Smart_Payment_Method_Defaults
	 */
	private $smart_defaults;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		remove_all_filters( 'pre_update_option_woocommerce_stripe_settings' );
		$this->smart_defaults = new WC_Stripe_Smart_Payment_Method_Defaults();
		delete_option( 'wc_stripe_smart_default_explicitly_disabled_payment_methods' );
		WC_Stripe_Helper::delete_main_stripe_settings();
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		delete_option( 'wc_stripe_smart_default_explicitly_disabled_payment_methods' );
		remove_all_filters( 'wc_stripe_smart_defaults_is_subscriptions_active' );
		remove_all_filters( 'wc_stripe_smart_default_payment_methods' );
		remove_all_filters( 'wc_stripe_smart_default_payment_method_map' );
		WC_Stripe_Helper::delete_main_stripe_settings();
		add_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ], 10, 2 );

		parent::tear_down();
	}

	public function test_applies_universal_and_country_defaults_to_pmc(): void {
		$this->set_connected_test_settings( [ 'pmc_enabled' => 'yes' ] );
		$this->set_stripe_account_data( [ 'country' => WC_Stripe_Country_Code::POLAND ] );

		$expected_enabled_methods = [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::AFFIRM,
			WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY,
			WC_Stripe_Payment_Methods::KLARNA,
			WC_Stripe_Payment_Methods::LINK,
			WC_Stripe_Payment_Methods::APPLE_PAY,
			WC_Stripe_Payment_Methods::GOOGLE_PAY,
			WC_Stripe_Payment_Methods::BLIK,
			WC_Stripe_Payment_Methods::P24,
		];

		$this->mock_payment_method_configurations( [], $expected_enabled_methods );
		$this->expect_payment_method_configurations_update( $expected_enabled_methods, [] );

		$result = $this->smart_defaults->apply_for_account_connection( 'test', [] );

		$this->assertTrue( $result['applied'], wp_json_encode( $result ) );
		$this->assertEqualSets( $expected_enabled_methods, $result['methods_enabled'] );
		$this->assertSame( [], $result['methods_skipped'] );
	}

	public function test_skips_account_connection_when_payment_method_choices_already_exist(): void {
		$this->stripe_api->expects( $this->never() )->method( 'update_payment_method_configurations' );

		$result = $this->smart_defaults->apply_for_account_connection(
			'test',
			[ 'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD ] ]
		);

		$this->assertFalse( $result['applied'] );
		$this->assertSame( 'existing_configuration', $result['reason'] );
	}

	public function test_direct_debit_defaults_are_gated_by_subscriptions(): void {
		$without_subscriptions = $this->smart_defaults->get_default_payment_method_ids(
			WC_Stripe_Country_Code::UNITED_STATES,
			false,
			WC_Stripe_Smart_Payment_Method_Defaults::TRIGGER_ACCOUNT_CONNECTION
		);
		$with_subscriptions    = $this->smart_defaults->get_default_payment_method_ids(
			WC_Stripe_Country_Code::UNITED_STATES,
			true,
			WC_Stripe_Smart_Payment_Method_Defaults::TRIGGER_ACCOUNT_CONNECTION
		);

		$this->assertNotContains( WC_Stripe_Payment_Methods::ACH, $without_subscriptions );
		$this->assertContains( WC_Stripe_Payment_Methods::ACH, $with_subscriptions );
	}

	public function test_applies_defaults_to_settings_when_pmc_is_disabled(): void {
		$this->set_connected_live_settings( [ 'pmc_enabled' => 'no' ] );
		$this->set_stripe_account_data(
			[
				'country'      => WC_Stripe_Country_Code::NETHERLANDS,
				'capabilities' => [
					'card_payments'  => 'active',
					'ideal_payments' => 'active',
					'link_payments'  => 'inactive',
				],
			]
		);

		$result   = $this->smart_defaults->apply_for_account_connection( 'live', [] );
		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertTrue( $result['applied'] );
		$this->assertContains( WC_Stripe_Payment_Methods::IDEAL, $settings['upe_checkout_experience_accepted_payments'] );
		$this->assertNotContains( WC_Stripe_Payment_Methods::LINK, $settings['upe_checkout_experience_accepted_payments'] );
		$this->assertSame( 'yes', $settings['express_checkout'] );
		$this->assertContains( WC_Stripe_Payment_Methods::APPLE_PAY, $result['methods_enabled'] );
		$this->assertContains( WC_Stripe_Payment_Methods::GOOGLE_PAY, $result['methods_enabled'] );
		$this->assertContains( WC_Stripe_Payment_Methods::LINK, $result['methods_skipped'] );
	}

	public function test_subscriptions_activation_backfill_enables_country_direct_debit(): void {
		$this->set_connected_test_settings( [ 'pmc_enabled' => 'yes' ] );
		$this->set_stripe_account_data( [ 'country' => WC_Stripe_Country_Code::UNITED_STATES ] );
		$this->mock_payment_method_configurations(
			[ WC_Stripe_Payment_Methods::CARD ],
			[ WC_Stripe_Payment_Methods::ACH ]
		);
		$this->expect_payment_method_configurations_update(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::ACH ],
			[]
		);

		$result = $this->smart_defaults->apply_subscriptions_activation_backfill();

		$this->assertTrue( $result['applied'] );
		$this->assertSame( [ WC_Stripe_Payment_Methods::ACH ], $result['methods_enabled'] );
	}

	public function test_subscriptions_activation_backfill_respects_explicitly_disabled_methods(): void {
		$this->set_connected_test_settings( [ 'pmc_enabled' => 'yes' ] );
		$this->set_stripe_account_data( [ 'country' => WC_Stripe_Country_Code::UNITED_STATES ] );
		$this->mock_payment_method_configurations(
			[ WC_Stripe_Payment_Methods::CARD ],
			[ WC_Stripe_Payment_Methods::ACH ]
		);
		$this->stripe_api->expects( $this->never() )->method( 'update_payment_method_configurations' );

		WC_Stripe_Smart_Payment_Method_Defaults::record_explicitly_disabled_payment_methods( [ WC_Stripe_Payment_Methods::ACH ] );

		$result = $this->smart_defaults->apply_subscriptions_activation_backfill();

		$this->assertFalse( $result['applied'] );
		$this->assertSame( [ WC_Stripe_Payment_Methods::ACH ], $result['methods_skipped'] );
	}

	public function test_subscriptions_activation_backfill_ignores_unrelated_plugin_activation(): void {
		add_filter( 'wc_stripe_smart_defaults_is_subscriptions_active', '__return_true' );
		$this->stripe_api->expects( $this->never() )->method( 'get_payment_method_configurations' );

		$this->smart_defaults->maybe_apply_subscriptions_activation_backfill( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Sets connected test mode Stripe settings.
	 *
	 * @param array $settings Additional settings.
	 * @return void
	 */
	private function set_connected_test_settings( array $settings = [] ): void {
		WC_Stripe_Helper::delete_main_stripe_settings();
		add_option(
			WC_Stripe_Helper::SETTINGS_OPTION,
			array_merge(
				[
					'enabled'              => 'yes',
					'testmode'             => 'yes',
					'test_publishable_key' => 'pk_test_123',
					'test_secret_key'      => 'sk_test_123',
				],
				$settings
			)
		);
	}

	/**
	 * Sets connected live mode Stripe settings.
	 *
	 * @param array $settings Additional settings.
	 * @return void
	 */
	private function set_connected_live_settings( array $settings = [] ): void {
		WC_Stripe_Helper::delete_main_stripe_settings();
		add_option(
			WC_Stripe_Helper::SETTINGS_OPTION,
			array_merge(
				[
					'enabled'         => 'yes',
					'testmode'        => 'no',
					'publishable_key' => 'pk_live_123',
					'secret_key'      => 'sk_live_123',
				],
				$settings
			)
		);
	}
}
