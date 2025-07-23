<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Feature_Flags;
use WooCommerce\Stripe\Tests\Helpers\PMC_Test_Helper;
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
}
