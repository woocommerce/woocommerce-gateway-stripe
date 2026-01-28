<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WC_Stripe_UPE_Payment_Gateway;
use WooCommerce\Stripe\Tests\Helpers\OC_Test_Helper;
use WooCommerce\Stripe\Tests\Helpers\PMC_Test_Helper;
use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;

/**
 * These tests make assertions against the class WC_Stripe_Feature_Flags
 *
 * Class WC_Stripe_Feature_Flags_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Feature_Flags
 */
class WC_Stripe_Feature_Flags_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	public function set_up() {
		parent::set_up();
		$this->upe_helper = new UPE_Test_Helper();
		$this->set_stripe_account_data( [ 'country' => 'US' ] );
	}

	/**
	 * Test for `is_oc_available`.
	 *
	 * @param bool $pmc_enabled Whether the Payment Method Configuration API is enabled.
	 * @param string $filter_function The filter function to apply.
	 * @param bool   $expected     The expected result.
	 * @return void
	 * @dataProvider provide_test_is_oc_available
	 */
	public function test_is_oc_available( $pmc_enabled, $filter_function, $expected ) {
		// Mock the payment method configuration for the test, to avoid it being disabled by default.
		PMC_Test_Helper::cache_mocked_configuration();

		if ( $pmc_enabled ) {
			PMC_Test_Helper::enable_pmc();
		} else {
			PMC_Test_Helper::disable_pmc();
		}

		if ( ! empty( $filter_function ) ) {
			add_filter( 'wc_stripe_is_optimized_checkout_available', $filter_function );
		}

		$actual = WC_Stripe_Feature_Flags::is_oc_available();

		// Clean up
		PMC_Test_Helper::disable_pmc();
		PMC_Test_Helper::delete_cached_configuration();

		$this->assertSame( $expected, $actual );

		if ( ! empty( $filter_function ) ) {
			remove_filter( 'wc_stripe_is_optimized_checkout_available', $filter_function );
		}
	}

	/**
	 * Provider for `test_is_oc_available`.
	 *
	 * @return array
	 */
	public function provide_test_is_oc_available() {
		return [
			'PMC enabled'                                => [
				'PMC enabled'     => true,
				'filter function' => '',
				'expected'        => true,
			],
			'PMC disabled'                               => [
				'PMC enabled'     => false,
				'filter function' => '',
				'expected'        => false,
			],
			'PMC disabled, filter set to true (ignored)' => [
				'PMC enabled'     => false,
				'filter function' => '__return_true',
				'expected'        => false,
			],
			'filter set to true'                         => [
				'PMC enabled'     => true,
				'filter function' => '__return_true',
				'expected'        => true,
			],
			'filter set to false'                        => [
				'PMC enabled'     => true,
				'filter function' => '__return_false',
				'expected'        => false,
			],
		];
	}

	/**
	 * Test for `is_checkout_sessions_available`.
	 *
	 * @param bool   $pmc_enabled           Whether the Payment Method Configuration API is enabled.
	 * @param bool   $oc_enabled             Whether the Optimized Checkout is enabled.
	 * @param bool   $automatic_capture      Whether automatic capture is enabled.
	 * @param bool   $feature_flag_enabled   Whether the checkout sessions feature flag is enabled.
	 * @param string $filter_function        The filter function to apply.
	 * @param bool   $expected               The expected result.
	 * @return void
	 * @dataProvider provide_test_is_checkout_sessions_available
	 */
	public function test_is_checkout_sessions_available( $pmc_enabled, $oc_enabled, $automatic_capture, $feature_flag_enabled, $filter_function, $expected ) {
		// Mock the payment method configuration for the test, to avoid it being disabled by default.
		PMC_Test_Helper::cache_mocked_configuration();

		if ( $pmc_enabled ) {
			PMC_Test_Helper::enable_pmc();
		} else {
			PMC_Test_Helper::disable_pmc();
		}

		if ( $oc_enabled ) {
			OC_Test_Helper::enable_oc();
		} else {
			OC_Test_Helper::disable_oc();
		}

		$stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['capture'] = $automatic_capture ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		update_option( WC_Stripe_Feature_Flags::CHECKOUT_SESSIONS_FEATURE_FLAG_NAME, $feature_flag_enabled ? 'yes' : 'no' );

		if ( ! empty( $filter_function ) ) {
			add_filter( 'wc_stripe_is_checkout_sessions_available', $filter_function );
		}

		$actual = WC_Stripe_Feature_Flags::is_checkout_sessions_available();

		// Clean up
		PMC_Test_Helper::disable_pmc();
		PMC_Test_Helper::delete_cached_configuration();
		OC_Test_Helper::disable_oc();
		update_option( WC_Stripe_Feature_Flags::CHECKOUT_SESSIONS_FEATURE_FLAG_NAME, 'no' );
		$stripe_settings['capture'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		if ( ! empty( $filter_function ) ) {
			remove_filter( 'wc_stripe_is_checkout_sessions_available', $filter_function );
		}

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_is_checkout_sessions_available`.
	 *
	 * @return array
	 */
	public function provide_test_is_checkout_sessions_available() {
		return [
			'All prerequisites met, feature flag enabled'  => [
				'PMC enabled'       => true,
				'OC enabled'        => true,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '',
				'expected'          => true,
			],
			'All prerequisites met, feature flag disabled' => [
				'PMC enabled'       => true,
				'OC enabled'        => true,
				'automatic capture' => true,
				'feature flag'      => false,
				'filter function'   => '',
				'expected'          => false,
			],
			'PMC disabled'                                 => [
				'PMC enabled'       => false,
				'OC enabled'        => true,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '',
				'expected'          => false,
			],
			'OC disabled'                                  => [
				'PMC enabled'       => true,
				'OC enabled'        => false,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '',
				'expected'          => false,
			],
			'Manual capture enabled'                       => [
				'PMC enabled'       => true,
				'OC enabled'        => true,
				'automatic capture' => false,
				'feature flag'      => true,
				'filter function'   => '',
				'expected'          => false,
			],
			'PMC disabled, filter set to true (filter ignored)' => [
				'PMC enabled'       => false,
				'OC enabled'        => true,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '__return_true',
				'expected'          => false,
			],
			'OC disabled, filter set to true (filter ignored)' => [
				'PMC enabled'       => true,
				'OC enabled'        => false,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '__return_true',
				'expected'          => false,
			],
			'Manual capture, filter set to true (filter ignored)' => [
				'PMC enabled'       => true,
				'OC enabled'        => true,
				'automatic capture' => false,
				'feature flag'      => true,
				'filter function'   => '__return_true',
				'expected'          => false,
			],
			'All prerequisites met, feature flag enabled, filter set to true' => [
				'PMC enabled'       => true,
				'OC enabled'        => true,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '__return_true',
				'expected'          => true,
			],
			'All prerequisites met, feature flag enabled, filter set to false' => [
				'PMC enabled'       => true,
				'OC enabled'        => true,
				'automatic capture' => true,
				'feature flag'      => true,
				'filter function'   => '__return_false',
				'expected'          => false,
			],
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
}
