<?php
/**
 * These tests make assertions against the class WC_Stripe_Feature_Flags
 */

/**
 * Class WC_Stripe_Feature_Flags_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Feature_Flags
 */
class WC_Stripe_Feature_Flags_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_oc_available`.
	 *
	 * @param string $option_value The value of the feature flag option.
	 * @param bool   $expected     The expected result.
	 * @return void
	 * @dataProvider provide_test_is_oc_available
	 */
	public function test_is_oc_available( $option_value, $expected ) {
		update_option( WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME, $option_value );
		$this->assertSame( $expected, WC_Stripe_Feature_Flags::is_oc_available() );
	}

	/**
	 * Provider for `test_is_oc_available`.
	 *
	 * @return array
	 */
	public function provide_test_is_oc_available() {
		return [
			'available'     => [
				'option value' => 'yes',
				'expected'     => true,
			],
			'not available' => [
				'option value' => 'no',
				'expected'     => false,
			],
		];
	}
}
