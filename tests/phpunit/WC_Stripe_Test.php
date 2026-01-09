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

	/**
	 * Tests the {@see WC_Stripe::add_gateways()} method.
	 *
	 * @param array $payment_methods The payment methods to add.
	 * @param array $expected_gateways The expected gateways.
	 * @param bool $is_admin Whether the test is running in the admin.
	 * @return void
	 * @dataProvider provide_test_add_gateways
	 */
	public function test_add_gateways( array $payment_methods, array $expected_gateways, bool $is_admin = false ): void {
		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_main_stripe_gateway' ] )
			->getMock();

		$mock_main_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_main_gateway->payment_methods = $payment_methods;

		$wc_stripe->method( 'get_main_stripe_gateway' )
			->willReturn( $mock_main_gateway );

		$initial_current_screen = null;
		$reset_current_screen   = false;

		if ( $is_admin ) {
			$initial_current_screen = $GLOBALS['current_screen'] ?? null;
			$reset_current_screen   = true;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = \WP_Screen::get( 'post.php' );
		}

		$gateways = $wc_stripe->add_gateways( [] );

		if ( $reset_current_screen ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = $initial_current_screen;
		}

		// First gateway should always be the main stripe gateway.
		$main_stripe_gateway = array_shift( $gateways );
		$this->assertEquals( $mock_main_gateway, $main_stripe_gateway );

		// Remaining gateways should be the expected "other" gateways.
		$this->assertEquals( count( $expected_gateways ), count( $gateways ) );
		foreach ( $expected_gateways as $expected_gateway ) {
			$this->assertContains( $expected_gateway, $gateways );
		}
	}

	/**
	 * Data provider for {@see test_add_gateways()}.
	 */
	public function provide_test_add_gateways(): array {
		$card_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		// Use real classes as the code tests for the specific class names.
		$link_gateway = new \WC_Stripe_UPE_Payment_Method_Link();

		$amazon_pay_gateway = new \WC_Stripe_UPE_Payment_Method_Amazon_Pay();

		$klarna_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Method_Klarna::class )
			->disableOriginalConstructor()
			->getMock();

		$afterpay_clearpay_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::class )
			->disableOriginalConstructor()
			->getMock();

		return [
			'none active' => [
				'payment_methods'   => [],
				'expected_gateways' => [],
			],
			'none active admin' => [
				'payment_methods'   => [],
				'expected_gateways' => [],
				'is_admin'          => true,
			],
			'card only non-admin is filtered out' => [
				'payment_methods'   => [
					'card' => $card_gateway,
				],
				'expected_gateways' => [],
			],
			'card only admin is filtered out' => [
				'payment_methods'   => [
					'card' => $card_gateway,
				],
				'expected_gateways' => [],
				'is_admin'          => true,
			],
			'link correctly included non-admin' => [
				'payment_methods'   => [
					'klarna' => $klarna_gateway,
					'link'   => $link_gateway,
				],
				'expected_gateways' => [ $klarna_gateway, $link_gateway ],
			],
			'link correctly filtered out admin' => [
				'payment_methods'   => [
					'klarna' => $klarna_gateway,
					'link'   => $link_gateway,
				],
				'expected_gateways' => [ $klarna_gateway ],
				'is_admin'          => true,
			],
			'amazon pay correctly included non-admin' => [
				'payment_methods'   => [
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway, $amazon_pay_gateway ],
			],
			'amazon pay correctly filtered out admin' => [
				'payment_methods'   => [
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway ],
				'is_admin'          => true,
			],
			'card filtered out; amazon pay and link correctly included non-admin' => [
				'payment_methods'   => [
					'card'              => $card_gateway,
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway, $amazon_pay_gateway, $link_gateway ],
			],
			'card, amazon pay, and link filtered out admin' => [
				'payment_methods'   => [
					'card'              => $card_gateway,
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway ],
				'is_admin'          => true,
			],
		];
	}
}
