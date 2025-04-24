<?php

class WC_Stripe_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	public function set_up() {
		parent::set_up();
		$this->upe_helper = new UPE_Test_Helper();
		$this->set_stripe_account_data( [ 'country' => 'US' ] );
	}

	public function test_constants_defined() {
		$this->assertTrue( defined( 'WC_STRIPE_VERSION' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_PHP_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_WC_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MAIN_FILE' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_PATH' ) );
	}

	/**
	 * Stripe requires price in the smallest dominations aka cents.
	 * This test will see if we're indeed converting the price correctly.
	 */
	public function test_price_conversion_before_send_to_stripe() {
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 10050, WC_Stripe_Currency_Code::JAPANESE_YEN ) );
		$this->assertEquals( 100, WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::JAPANESE_YEN ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50 ) );
		$this->assertIsInt( WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );
	}

	/**
	 * We store balance fee/net amounts coming from Stripe.
	 * We need to make sure we format it correctly to be stored in WC.
	 * These amounts are posted in lowest dominations.
	 */
	public function test_format_balance_fee() {
		$balance_fee1           = new stdClass();
		$balance_fee1->fee      = 10500;
		$balance_fee1->net      = 10000;
		$balance_fee1->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee1, 'fee' ) );

		$balance_fee2           = new stdClass();
		$balance_fee2->fee      = 10500;
		$balance_fee2->net      = 10000;
		$balance_fee2->currency = WC_Stripe_Currency_Code::JAPANESE_YEN;

		$this->assertEquals( 10500, WC_Stripe_Helper::format_balance_fee( $balance_fee2, 'fee' ) );

		$balance_fee3           = new stdClass();
		$balance_fee3->fee      = 10500;
		$balance_fee3->net      = 10000;
		$balance_fee3->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 100.00, WC_Stripe_Helper::format_balance_fee( $balance_fee3, 'net' ) );

		$balance_fee4           = new stdClass();
		$balance_fee4->fee      = 10500;
		$balance_fee4->net      = 10000;
		$balance_fee4->currency = WC_Stripe_Currency_Code::JAPANESE_YEN;

		$this->assertEquals( 10000, WC_Stripe_Helper::format_balance_fee( $balance_fee4, 'net' ) );

		$balance_fee5           = new stdClass();
		$balance_fee5->fee      = 10500;
		$balance_fee5->net      = 10000;
		$balance_fee5->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );

		$this->assertIsString( WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );
	}

	/**
	 * Stripe requires statement_descriptor to be no longer than 22 characters.
	 * In addition, it cannot contain <>"' special characters.
	 *
	 * @dataProvider statement_descriptor_sanitation_provider
	 */
	public function test_statement_descriptor_sanitation( $original, $expected ) {
		$this->assertEquals( $expected, WC_Stripe_Helper::clean_statement_descriptor( $original ) );
	}

	public function statement_descriptor_sanitation_provider() {
		return [
			'removes \''             => [ 'Test\'s Store', 'Tests Store' ],
			'removes "'              => [ 'Test " Store', 'Test  Store' ],
			'removes <'              => [ 'Test < Store', 'Test  Store' ],
			'removes >'              => [ 'Test > Store', 'Test  Store' ],
			'removes /'              => [ 'Test / Store', 'Test  Store' ],
			'removes ('              => [ 'Test ( Store', 'Test  Store' ],
			'removes )'              => [ 'Test ) Store', 'Test  Store' ],
			'removes {'              => [ 'Test { Store', 'Test  Store' ],
			'removes }'              => [ 'Test } Store', 'Test  Store' ],
			'removes \\'             => [ 'Test \\ Store', 'Test  Store' ],
			'removes *'              => [ 'Test * Store', 'Test  Store' ],
			'keeps at most 22 chars' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and >' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and <' => [ 'Test\'s Store < Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and "' => [ 'Test\'s Store " Driving Course Range', 'Tests Store  Driving C' ],
			'removes non-Latin'      => [ 'Test-Storeシ Drהiving?12', 'Test-Store Driving?12' ],
		];
	}

	public function test_legacy_payment_methods_supported_by_upe_are_not_loaded_when_upe_is_enabled() {
		$this->upe_helper->enable_upe_feature_flag();
		$this->assertTrue( WC_Stripe_Feature_Flags::is_upe_preview_enabled() );

		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );
		$this->upe_helper->reload_payment_gateways();

		$this->assertTrue( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() );

		$loaded_gateway_classes = array_map(
			function ( $gateway ) {
				return get_class( $gateway );
			},
			WC()->payment_gateways->payment_gateways()
		);

		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $upe_method ) {
			if ( ! defined( "$upe_method::LPM_GATEWAY_CLASS" ) ) {
				continue;
			}
			$this->assertNotContains( $upe_method::LPM_GATEWAY_CLASS, $loaded_gateway_classes );
		}

		$this->assertContains( WC_Stripe_UPE_Payment_Gateway::class, $loaded_gateway_classes );
	}

	public function test_turning_on_upe_with_no_stripe_legacy_payment_methods_enabled_will_not_turn_on_the_upe_gateway_and_default_to_card_and_link() {
		$this->upe_helper->enable_upe_feature_flag();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		$this->assertEquals( 'no', $stripe_settings['upe_checkout_experience_enabled'] );

		$stripe_settings['upe_checkout_experience_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		// Because no Stripe LPM's were enabled when UPE was enabled, the Stripe gateway is not enabled yet.
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		$this->assertEquals( 'yes', $stripe_settings['upe_checkout_experience_enabled'] );
	}

	public function test_turning_on_upe_enables_the_correct_upe_methods_based_on_which_legacy_payment_methods_were_enabled() {
		update_option( 'woocommerce_currency', 'EUR' );
		$this->upe_helper->enable_upe_feature_flag();

		// Enable sepa and iDEAL LPM gateways.
		update_option( 'woocommerce_stripe_sepa_settings', [ 'enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_ideal_settings', [ 'enabled' => 'yes' ] );
		$this->upe_helper->reload_payment_gateways();

		// Initialize default stripe settings, turn on UPE.
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertEquals( 'yes', $stripe_settings['enabled'] );
		$this->assertEquals( 'yes', $stripe_settings['upe_checkout_experience_enabled'] );

		// Make sure the SEPA and iDEAL LPMs were disabled.
		$sepa_settings = get_option( 'woocommerce_stripe_sepa_settings' );
		$this->assertEquals( 'no', $sepa_settings['enabled'] );
		$ideal_settings = get_option( 'woocommerce_stripe_ideal_settings' );
		$this->assertEquals( 'no', $ideal_settings['enabled'] );
	}

	public function test_turning_off_upe_enables_the_correct_legacy_payment_methods_based_on_which_upe_payment_methods_were_enabled() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		update_option( 'woocommerce_currency', 'EUR' );
		$this->upe_helper->enable_upe_feature_flag();

		// Disable sepa and iDEAL LPM gateways.
		update_option( 'woocommerce_stripe_sepa_settings', [ 'enabled' => 'no' ] );
		update_option( 'woocommerce_stripe_ideal_settings', [ 'enabled' => 'no' ] );

		// Turn UPE on first.
		$stripe_settings['upe_checkout_experience_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::SEPA_DEBIT, WC_Stripe_Payment_Methods::IDEAL ] );

		// Turn UPE off.
		$stripe_settings['upe_checkout_experience_enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Check that the main 'stripe' gateway was disabled because the 'card' UPE method was not enabled.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		// Check that the correct LPMs were re-enabled.
		$sepa_settings = get_option( 'woocommerce_stripe_sepa_settings' );
		$this->assertEquals( 'yes', $sepa_settings['enabled'] );
		$ideal_settings = get_option( 'woocommerce_stripe_ideal_settings' );
		$this->assertEquals( 'yes', $ideal_settings['enabled'] );
	}

	/**
	 * Test that {@see WC_Stripe_Helper::is_webhook_url()} works as expected.
	 *
	 * @dataProvider is_webhook_url_provider
	 * @covers WC_Stripe_Helper::is_webhook_url()
	 */
	public function test_is_webhook_url( $url, $webhook_url, $expected_result ) {
		$this->assertEquals( $expected_result, WC_Stripe_Helper::is_webhook_url( $url, $webhook_url ) );
	}

	/**
	 * Data provider for {@see test_is_webhook_url()}.
	 *
	 * @return array
	 */
	public function is_webhook_url_provider() {
		return [
			'webhook URLs with mismatched protocol should match'       => [ 'https://example.com/?wc-api=wc_stripe', 'http://example.com/?wc-api=wc_stripe', true ],
			'webhook URLs with mismatched host should not match'       => [ 'https://example.com/?wc-api=wc_stripe', 'https://test.example.com/?wc-api=wc_stripe', false ],
			'webhook URLs with mismatched path should not match'       => [ 'https://example.com/foo?wc-api=wc_stripe', 'https://example.com/bar?wc-api=wc_stripe', false ],
			'webhook URL with empty path should match'                 => [ 'https://example.com/', 'https://example.com/?wc-api=wc_stripe', true ],
			'webhook URL with empty query string should match'         => [ 'https://example.com/test/', 'https://example.com/test/', true ],
			'webhook URL with empty comparison query should not match' => [ 'https://example.com/test/?foo=bar', 'https://example.com/test/', false ],
			'webhook URL with missing parameter should not match'      => [ 'https://example.com/test/?wc-api=wc_stripe', 'https://example.com/test/?wc-api=wc_stripe&foo=bar', false ],
			'webhook URL with wrong parameter should not match'        => [ 'https://example.com/test/?wc-api=wc_stripe_BAD', 'https://example.com/test/?wc-api=wc_stripe', false ],
			'webhook URL with extra parameters should match'           => [ 'https://example.com/test/?wc-api=wc_stripe&foo=bar', 'https://example.com/test/?wc-api=wc_stripe', true ],
		];
	}
}
