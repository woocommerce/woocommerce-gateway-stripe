<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Gateway;

/**
 * These tests make assertions against the class WC_Stripe.
 *
 * Class WC_Stripe_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe
 */
class WC_Stripe_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	public function test_constants_defined() {
		$this->assertTrue( defined( 'WC_STRIPE_VERSION' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_PHP_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_WC_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MAIN_FILE' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_PATH' ) );
	}

	/**
	 * Tests for `maybe_toggle_payment_methods`.
	 *
	 * @param array $active_gateways The active payment gateways.
	 * @param array $enabled_payment_method_ids The enabled payment method IDs.
	 * @param int $update_enable_payment_methods_calls The number of times `update_enabled_payment_methods` should be called.
	 * @return void
	 *
	 * @dataProvider provide_test_maybe_toggle_payment_methods
	 */
	public function test_maybe_toggle_payment_methods(
		$active_gateways,
		$enabled_payment_method_ids,
		$update_enable_payment_methods_calls
	) {
		$original_payment_gateways = WC()->payment_gateways->payment_gateways;

		// Mock the available payment gateways.
		WC()->payment_gateways->payment_gateways = $active_gateways;

		$upe_payment_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$upe_payment_gateway->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( $enabled_payment_method_ids );

		$upe_payment_gateway->expects( $this->exactly( $update_enable_payment_methods_calls ) )
			->method( 'update_enabled_payment_methods' )
			->with( [ WC_Stripe_Payment_Methods::CARD ] );

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_main_stripe_gateway' ] )
			->getMock();

		$wc_stripe->method( 'get_main_stripe_gateway' )
			->willReturn( $upe_payment_gateway );

		$wc_stripe->maybe_toggle_payment_methods( WC()->payment_gateways );

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;
	}

	/**
	 * Provider for `test_maybe_deactivate_payment_methods`.
	 *
	 * @return array
	 */
	public function provide_test_maybe_toggle_payment_methods() {
		return [
			'none active'                                 => [
				'active gateways'                     => [],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
				],
				'update enable payment methods calls' => 0,
			],
			'affirm'                                      => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::AFFIRM,
				],
				'update enable payment methods calls' => 1,
			],
			'klarna'                                      => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::KLARNA,
				],
				'update enable payment methods calls' => 1,
			],
			'klarna and affirm active, but not on Stripe' => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
				],
				'update enable payment methods calls' => 0,
			],
			'klarna and affirm active in both'            => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::AFFIRM,
					WC_Stripe_Payment_Methods::KLARNA,
				],
				'update enable payment methods calls' => 1,
			],
			'amazon pay'                                  => [
				'active gateways'                     => [],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::AMAZON_PAY,
				],
				'update enable payment methods calls' => 1,
			],
		];
	}

	/**
	 * Tests for the 'install' method when it updates the main Stripe settings.
	 *
	 * @param array $stripe_settings   The initial Stripe settings.
	 * @param array $expected_settings The expected Stripe settings after installation.
	 * @return void
	 *
	 * @dataProvider provide_test_install_settings
	 */
	public function test_install_settings_update( array $stripe_settings = [], array $expected_settings = [] ): void {
		// Activate the Stripe plugin.
		update_option( 'active_plugins', [ plugin_basename( WC_STRIPE_MAIN_FILE ) ] );

		// Set initial settings.
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'update_plugin_version',
					'update_prb_location_settings',
					'migrate_to_new_checkout_experience',
				]
			)
			->getMock();

		$wc_stripe->install();

		$actual_settings = WC_Stripe_Helper::get_stripe_settings();
		foreach ( $expected_settings as $key => $value ) {
			if ( null == $value ) {
				$this->assertArrayNotHasKey( $key, $actual_settings );
			} else {
				$this->assertArrayHasKey( $key, $actual_settings );
				$this->assertSame( $value, $actual_settings[ $key ] );
			}
		}
	}

	/**
	 * Data provider for `test_install_settings`.
	 *
	 * @return array
	 */
	public function provide_test_install_settings(): array {
		return [
			'will not enable OCS by default due to PMC being disabled' => [
				'stripe settings' => [
					'pmc_enabled' => 'no',
				],
				'expected settings' => [
					'pmc_enabled'                => null,
					'optimized_checkout_element' => null,
				],
			],
			'will not enable OCS by default due to OCS being set'  => [
				'stripe settings' => [
					'pmc_enabled'                => 'yes',
					'optimized_checkout_element' => 'no',
				],
				'expected settings' => [
					'pmc_enabled'                => 'yes',
					'optimized_checkout_element' => 'no',
				],
			],
		];
	}

	/**
	 * Tests that the 'install' method sets the fresh install flag.
	 *
	 * @return void
	 */
	public function test_install_sets_fresh_install_flag(): void {
		update_option( 'active_plugins', [ plugin_basename( WC_STRIPE_MAIN_FILE ) ] );

		// Ensure the flag is not set.
		delete_option( 'wc_stripe_optimized_checkout_default_on' );

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'update_plugin_version',
					'update_prb_location_settings',
					'migrate_to_new_checkout_experience',
				]
			)
			->getMock();

		$wc_stripe->install();

		$this->assertEquals( 'yes', get_option( 'wc_stripe_optimized_checkout_default_on' ) );
	}

	/**
	 * Tests that update_prb_location_settings copies payment_request_button_locations to express_checkout_button_locations.
	 *
	 * @return void
	 */
	public function test_update_prb_location_settings_copies_existing_payment_request_locations(): void {
		$this->remove_gateway_settings_update_filter();

		// Set up settings with payment_request_button_locations but no express_checkout_button_locations.
		$stripe_settings = [
			'enabled'                          => 'yes',
			'payment_request_button_locations' => [ 'checkout' ],
		];
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		WC_Stripe::get_instance()->update_prb_location_settings();

		$updated_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertSame( [ 'checkout' ], $updated_settings['express_checkout_button_locations'] );
	}

	/**
	 * Tests that update_prb_location_settings falls back to filter defaults when no existing locations are set.
	 *
	 * @return void
	 */
	public function test_update_prb_location_settings_uses_filter_defaults_when_no_existing_locations(): void {
		$this->remove_gateway_settings_update_filter();

		// Set up settings with no location settings.
		$stripe_settings = [
			'enabled' => 'yes',
		];
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		WC_Stripe::get_instance()->update_prb_location_settings();

		$updated_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertContains( 'product', $updated_settings['express_checkout_button_locations'] );
		$this->assertContains( 'cart', $updated_settings['express_checkout_button_locations'] );
	}

	/**
	 * Removes the gateway_settings_update filter that merges defaults when saving settings.
	 *
	 * This filter adds default field values when saving settings for the first time,
	 * which interferes with tests that need to set specific settings without defaults.
	 *
	 * @return void
	 */
	private function remove_gateway_settings_update_filter(): void {
		remove_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ] );
	}
}
