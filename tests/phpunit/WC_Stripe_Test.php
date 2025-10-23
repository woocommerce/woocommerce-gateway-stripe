<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;
use WC_Stripe_Transact_Account_Manager;
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
	 * Tests for `maybe_onboard_with_transact`.
	 *
	 * @param bool $can_manage_woocommerce Whether the user can manage WooCommerce.
	 * @param bool $gateway_enabled        Whether the gateway is enabled.
	 * @param bool $onboarding_called      Whether onboarding is expected to be called.
	 * @return void
	 *
	 * @dataProvider provide_test_maybe_onboard_with_transact
	 */
	public function test_maybe_onboard_with_transact( $can_manage_woocommerce = false, $gateway_enabled = true, $onboarding_called = false ): void {
		// Mock the GLOBALS to return `true` for `is_admin`
		$current_screen = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->expects( $this->any() )
			->method( 'in_admin' )
			->willReturn( true );

		$GLOBALS['current_screen'] = $current_screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$user_cap_filter = function ( $allcaps ) use ( $can_manage_woocommerce ) {
			$allcaps['manage_woocommerce'] = $can_manage_woocommerce;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $user_cap_filter );

		$transact_account_manager = $this->getMockBuilder( WC_Stripe_Transact_Account_Manager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'do_onboarding' ] )
			->getMock();

		$transact_account_manager->expects( $onboarding_called ? $this->once() : $this->never() )
			->method( 'do_onboarding' );

		WC_Stripe_Transact_Account_Manager::set_instance( $transact_account_manager );

		$upe_payment_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$upe_payment_gateway->enabled = $gateway_enabled ? 'yes' : 'no';

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_main_stripe_gateway' ] )
			->getMock();

		$wc_stripe->method( 'get_main_stripe_gateway' )
			->willReturn( $upe_payment_gateway );

		$wc_stripe->maybe_onboard_with_transact();

		// Clean up.
		remove_filter( 'user_has_cap', $user_cap_filter );
		unset( $GLOBALS['current_screen'] );
	}

	/**
	 * Provider for `test_maybe_onboard_with_transact`.
	 *
	 * @return array
	 */
	public function provide_test_maybe_onboard_with_transact(): array {
		return [
			'user cannot manage woocommerce' => [
				'user can manage woocommerce' => false,
				'gateway is enabled'          => true,
				'onboarding called'           => false,
			],
			'gateway is not enabled'         => [
				'user can manage woocommerce' => true,
				'gateway is enabled'          => false,
				'onboarding called'           => false,
			],
			'onboarding called successfully' => [
				'user can manage woocommerce' => true,
				'gateway is enabled'          => true,
				'onboarding called'           => true,
			],
		];
	}
}
