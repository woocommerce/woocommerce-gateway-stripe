<?php

namespace WooCommerce\Stripe\Tests;

use ReflectionClass;
use WC_Stripe;
use WC_Stripe_API;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Method_Configurations;
use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Gateway;
use WooCommerce\Stripe\Tests\Helpers\PMC_Test_Helper;
use WP_UnitTestCase;

/**
 * These tests make assertions against the class WC_Stripe.
 *
 * Class WC_Stripe_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe
 */
class WC_Stripe_Test extends WP_UnitTestCase {
//	public function test_constants_defined() {
//		$this->assertTrue( defined( 'WC_STRIPE_VERSION' ) );
//		$this->assertTrue( defined( 'WC_STRIPE_MIN_PHP_VER' ) );
//		$this->assertTrue( defined( 'WC_STRIPE_MIN_WC_VER' ) );
//		$this->assertTrue( defined( 'WC_STRIPE_MAIN_FILE' ) );
//		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_URL' ) );
//		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_PATH' ) );
//	}

	/**
	 * Tests for `maybe_deactivate_bnpls`.
	 *
	 * @return void
	 *
	 * @dataProvider provide_test_maybe_deactivate_bnpls
	 */
	public function test_maybe_deactivate_bnpls(
		$active_gateways,
		$pmc_enabled_payment_methods
	) {
		PMC_Test_Helper::enable_pmc();
		PMC_Test_Helper::cache_mocked_configuration( $pmc_enabled_payment_methods );

		// Mock the Stripe API response to return a valid configuration
		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_api->expects( $this->once() )
			->method( 'update_payment_method_configurations' )
			->willReturn( (object) [] );

		// Set the mock API instance
		$reflection = new ReflectionClass( WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		// Mock the available payment gateways.
		$original_payment_gateways               = WC()->payment_gateways->payment_gateways;
		WC()->payment_gateways->payment_gateways = $active_gateways;

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'init' ] )
			->getMock();

		$wc_stripe->maybe_deactivate_bnpls();

		// Only the card payment method should be enabled.
		$actual = $wc_stripe->get_main_stripe_gateway()->get_upe_enabled_payment_method_ids();
		$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], $actual );

		// Clean up.
		PMC_Test_Helper::delete_cached_configuration();
		PMC_Test_Helper::disable_pmc();
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;
	}

	/**
	 * Provider for `test_maybe_deactivate_bnpls`.
	 *
	 * @return array
	 */
	public function provide_test_maybe_deactivate_bnpls() {
		return [
//			'none active'                    => [
//				'active gateways'                     => [],
//				'enabled payment method IDs'          => [
//					WC_Stripe_Payment_Methods::CARD,
//				],
//				'update enable payment methods calls' => 0,
//			],
			'affirm'                         => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
				],
				'PMC enabled payment methods'          => [
					WC_Stripe_Payment_Methods::CARD => (object) [
						'display_preference' => (object) [ 'value' => 'on' ],
					],
					WC_Stripe_Payment_Methods::AFFIRM => (object) [
						'display_preference' => (object) [ 'value' => 'on' ],
					],
				],
			],
//			'klarna'                         => [
//				'active gateways'                     => [
//					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
//						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
//						'enabled' => 'yes',
//					],
//				],
//				'enabled payment method IDs'          => [
//					WC_Stripe_Payment_Methods::CARD,
//					WC_Stripe_Payment_Methods::KLARNA,
//				],
//				'update enable payment methods calls' => 1,
//			],
//			'both active, but not on Stripe' => [
//				'active gateways'                     => [
//					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
//						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
//						'enabled' => 'yes',
//					],
//					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
//						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
//						'enabled' => 'yes',
//					],
//				],
//				'enabled payment method IDs'          => [
//					WC_Stripe_Payment_Methods::CARD,
//				],
//				'update enable payment methods calls' => 0,
//			],
//			'both active in both'            => [
//				'active gateways'                     => [
//					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
//						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
//						'enabled' => 'yes',
//					],
//					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
//						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
//						'enabled' => 'yes',
//					],
//				],
//				'enabled payment method IDs'          => [
//					WC_Stripe_Payment_Methods::CARD,
//					WC_Stripe_Payment_Methods::AFFIRM,
//					WC_Stripe_Payment_Methods::KLARNA,
//				],
//				'update enable payment methods calls' => 1,
//			],
		];
	}
}
