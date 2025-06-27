<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WP_UnitTestCase;

/**
 * These tests make assertions against the class WC_Stripe_Feature_Flags
 *
 * Class WC_Stripe_Feature_Flags_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Feature_Flags
 */
class WC_Stripe_Feature_Flags_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_oc_available`.
	 *
	 * @param string $option_value The value of the feature flag option.
	 * @param string $filter_function The filter function to apply.
	 * @param bool   $expected     The expected result.
	 * @return void
	 * @dataProvider provide_test_is_oc_available
	 */
	public function test_is_oc_available( $settings_values, $option_value, $filter_function, $expected ) {
		// Merge the provided settings values with the default settings.
		WC_Stripe_Helper::update_main_stripe_settings(
			array_merge(
				WC_Stripe_Helper::get_stripe_settings(),
				$settings_values
			)
		);

		if ( ! empty( $filter_function ) ) {
			add_filter( 'wc_stripe_is_optimized_checkout_available', $filter_function );
		}

		update_option( WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME, $option_value );
		$this->assertSame( $expected, WC_Stripe_Feature_Flags::is_oc_available() );

		if ( ! empty( $filter_function ) ) {
			remove_filter( 'wc_stripe_is_optimized_checkout_available', $filter_function );
		}

		// Reset settings to the default values.
		$settings                         = WC_Stripe_Helper::get_stripe_settings();
		$settings['connection_type']      = 'connect';
		$settings['test_connection_type'] = 'connect';
		$settings['pmc_enabled']          = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}

	/**
	 * Provider for `test_is_oc_available`.
	 *
	 * @return array
	 */
	public function provide_test_is_oc_available() {
		return [
			'available'                                 => [
				'settings values' => [
					'connection_type'      => 'connect',
					'test_connection_type' => 'connect',
					'pmc_enabled'          => 'yes',
				],
				'option value'    => 'yes',
				'filter function' => '',
				'expected'        => true,
			],
			'not available indirectly due PMC disabled' => [
				'settings values' => [
					'connection_type'      => 'connect',
					'test_connection_type' => 'connect',
					'pmc_enabled'          => 'no',
				],
				'option value'    => 'yes',
				'filter function' => '',
				'expected'        => false,
			],
			'not available directly'                    => [
				'settings values' => [
					'connection_type'      => 'connect',
					'test_connection_type' => 'connect',
					'pmc_enabled'          => 'yes',
				],
				'option value'    => 'no',
				'filter function' => '',
				'expected'        => false,
			],
			'filter set to true'                        => [
				'settings values' => [
					'connection_type'      => 'connect',
					'test_connection_type' => 'connect',
					'pmc_enabled'          => 'yes',
				],
				'option value'    => 'no',
				'filter function' => '__return_true',
				'expected'        => true,
			],
			'filter set to false'                       => [
				'settings values' => [
					'connection_type'      => 'connect',
					'test_connection_type' => 'connect',
					'pmc_enabled'          => 'yes',
				],
				'option value'    => 'yes',
				'filter function' => '__return_false',
				'expected'        => false,
			],
		];
	}
}
