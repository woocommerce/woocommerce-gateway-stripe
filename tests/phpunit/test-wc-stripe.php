<?php

class WC_Stripe_Test extends WP_UnitTestCase {

	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	public function set_up() {
		parent::set_up();
		$this->upe_helper = new UPE_Test_Helper();
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
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50, 'USD' ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 10050, 'JPY' ) );
		$this->assertEquals( 100, WC_Stripe_Helper::get_stripe_amount( 100.50, 'JPY' ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50 ) );
		$this->assertIsInt( WC_Stripe_Helper::get_stripe_amount( 100.50, 'USD' ) );
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
		$balance_fee1->currency = 'USD';

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee1, 'fee' ) );

		$balance_fee2           = new stdClass();
		$balance_fee2->fee      = 10500;
		$balance_fee2->net      = 10000;
		$balance_fee2->currency = 'JPY';

		$this->assertEquals( 10500, WC_Stripe_Helper::format_balance_fee( $balance_fee2, 'fee' ) );

		$balance_fee3           = new stdClass();
		$balance_fee3->fee      = 10500;
		$balance_fee3->net      = 10000;
		$balance_fee3->currency = 'USD';

		$this->assertEquals( 100.00, WC_Stripe_Helper::format_balance_fee( $balance_fee3, 'net' ) );

		$balance_fee4           = new stdClass();
		$balance_fee4->fee      = 10500;
		$balance_fee4->net      = 10000;
		$balance_fee4->currency = 'JPY';

		$this->assertEquals( 10000, WC_Stripe_Helper::format_balance_fee( $balance_fee4, 'net' ) );

		$balance_fee5           = new stdClass();
		$balance_fee5->fee      = 10500;
		$balance_fee5->net      = 10000;
		$balance_fee5->currency = 'USD';

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

		update_option( 'woocommerce_stripe_settings', [ 'upe_checkout_experience_enabled' => 'yes' ] );
		$this->upe_helper->reload_payment_gateways();

		$this->assertTrue( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() );

		$loaded_gateway_classes = array_map(
			function( $gateway ) {
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

	public function test_turning_on_upe_with_no_stripe_legacy_payment_methods_enabled_will_not_turn_on_the_upe_gateway_and_default_to_card_only() {
		$this->upe_helper->enable_upe_feature_flag();
		// Store default stripe options
		update_option( 'woocommerce_stripe_settings', [] );

		$stripe_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		$this->assertEquals( 'no', $stripe_settings['upe_checkout_experience_enabled'] );

		$stripe_settings['upe_checkout_experience_enabled'] = 'yes';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		$stripe_settings = get_option( 'woocommerce_stripe_settings' );
		// Because no Stripe LPM's were enabled when UPE was enabled, the Stripe gateway is not enabled yet.
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		$this->assertEquals( 'yes', $stripe_settings['upe_checkout_experience_enabled'] );
		$this->assertContains( 'card', $stripe_settings['upe_checkout_experience_accepted_payments'] );
		$this->assertCount( 1, $stripe_settings['upe_checkout_experience_accepted_payments'] );
	}

	public function test_turning_on_upe_enables_the_correct_upe_methods_based_on_which_legacy_payment_methods_were_enabled_and_vice_versa() {
		$this->upe_helper->enable_upe_feature_flag();

		// Enable giropay and iDEAL LPM gateways.
		update_option( 'woocommerce_stripe_giropay_settings', [ 'enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_ideal_settings', [ 'enabled' => 'yes' ] );
		$this->upe_helper->reload_payment_gateways();

		// Initialize default stripe settings, turn on UPE.
		update_option( 'woocommerce_stripe_settings', [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$stripe_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertEquals( 'yes', $stripe_settings['enabled'] );
		$this->assertEquals( 'yes', $stripe_settings['upe_checkout_experience_enabled'] );
		$this->assertNotContains( 'card', $stripe_settings['upe_checkout_experience_accepted_payments'] );
		$this->assertContains( 'giropay', $stripe_settings['upe_checkout_experience_accepted_payments'] );
		$this->assertContains( 'ideal', $stripe_settings['upe_checkout_experience_accepted_payments'] );

		// Make sure the giropay and iDEAL LPMs were disabled.
		$giropay_settings = get_option( 'woocommerce_stripe_giropay_settings' );
		$this->assertEquals( 'no', $giropay_settings['enabled'] );
		$ideal_settings = get_option( 'woocommerce_stripe_ideal_settings' );
		$this->assertEquals( 'no', $ideal_settings['enabled'] );

		// Enable the EPS UPE method. Now when UPE is disabled, the EPS LPM should be enabled.
		$stripe_settings['upe_checkout_experience_accepted_payments'][] = 'eps';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		// Turn UPE off.
		$stripe_settings['upe_checkout_experience_enabled'] = 'no';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		// Check that the main 'stripe' gateway was disabled because the 'card' UPE method was not enabled.
		$stripe_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		// Check that the correct LPMs were re-enabled.
		$giropay_settings = get_option( 'woocommerce_stripe_giropay_settings' );
		$this->assertEquals( 'yes', $giropay_settings['enabled'] );
		$ideal_settings = get_option( 'woocommerce_stripe_ideal_settings' );
		$this->assertEquals( 'yes', $ideal_settings['enabled'] );
		$eps_settings = get_option( 'woocommerce_stripe_eps_settings' );
		$this->assertEquals( 'yes', $eps_settings['enabled'] );
	}
}
