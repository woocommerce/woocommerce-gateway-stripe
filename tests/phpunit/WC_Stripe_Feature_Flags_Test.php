<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Feature_Flags;
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
}
