<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WC_Stripe_UPE_Payment_Gateway;
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
	 * @param string $option_value The value of the feature flag option.
	 * @param string $filter_function The filter function to apply.
	 * @param bool   $expected     The expected result.
	 * @return void
	 * @dataProvider provide_test_is_oc_available
	 */
	public function test_is_oc_available( $option_value, $filter_function, $expected ) {
		if ( ! empty( $filter_function ) ) {
			add_filter( 'wc_stripe_is_optimized_checkout_available', $filter_function );
		}

		update_option( WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME, $option_value );
		$this->assertSame( $expected, WC_Stripe_Feature_Flags::is_oc_available() );

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
			'available'           => [
				'option value'    => 'yes',
				'filter function' => '',
				'expected'        => true,
			],
			'not available'       => [
				'option value'    => 'no',
				'filter function' => '',
				'expected'        => false,
			],
			'filter set to true'  => [
				'option value'    => 'no',
				'filter function' => '__return_true',
				'expected'        => true,
			],
			'filter set to false' => [
				'option value'    => 'yes',
				'filter function' => '__return_false',
				'expected'        => false,
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
