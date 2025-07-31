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
	 * Tests for `maybe_deactivate_payment_methods`.
	 *
	 * @return void
	 *
	 * @dataProvider provide_test_maybe_deactivate_payment_methods
	 */
	public function test_maybe_deactivate_payment_methods(
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

		$wc_stripe->maybe_deactivate_payment_methods();

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;
	}

	/**
	 * Provider for `test_maybe_deactivate_payment_methods`.
	 *
	 * @return array
	 */
	public function provide_test_maybe_deactivate_payment_methods() {
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
}
