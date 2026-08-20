<?php

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
	 * `is_amazon_pay_available()` is deprecated but must keep returning true so any
	 * lingering third-party callers continue to treat Amazon Pay as permanently enabled.
	 */
	public function test_is_amazon_pay_available_is_deprecated(): void {
		$this->setExpectedDeprecated( 'WC_Stripe_Feature_Flags::is_amazon_pay_available' );

		$this->assertTrue( WC_Stripe_Feature_Flags::is_amazon_pay_available() );
	}

	/**
	 * Test for `is_oc_available`.
	 *
	 * @param bool $pmc_enabled Whether the Payment Method Configuration API is enabled.
	 * @param string $filter_function The filter function to apply.
	 * @param bool   $expected  The expected result.
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
		if ( ! empty( $filter_function ) ) {
			remove_filter( 'wc_stripe_is_optimized_checkout_available', $filter_function );
		}
		PMC_Test_Helper::disable_pmc();
		PMC_Test_Helper::delete_cached_configuration();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_is_oc_available`.
	 *
	 * @return array
	 */
	public function provide_test_is_oc_available() {
		return [
			'PMC enabled'                            => [
				'PMC enabled'     => true,
				'filter function' => '',
				'expected'        => true,
			],
			'PMC disabled'                           => [
				'PMC enabled'     => false,
				'filter function' => '',
				'expected'        => false,
			],
			'PMC enabled, filter overrides to false' => [
				'PMC enabled'     => true,
				'filter function' => '__return_false',
				'expected'        => false,
			],
			'PMC disabled, filter overrides to true' => [
				'PMC enabled'     => false,
				'filter function' => '__return_true',
				'expected'        => false,
			],
		];
	}

	/**
	 * Test for `is_abilities_enabled`.
	 *
	 * @param string|null $option_value     The value to set on `_wcstripe_feature_abilities`, or null to delete.
	 * @param string      $filter_function  The filter function to apply to `wc_stripe_abilities_enabled`, or '' for none.
	 * @param bool        $expected         The expected return value.
	 *
	 * @dataProvider provide_test_is_abilities_enabled
	 */
	public function test_is_abilities_enabled( $option_value, string $filter_function, bool $expected ): void {
		if ( null === $option_value ) {
			delete_option( WC_Stripe_Feature_Flags::ABILITIES_FEATURE_FLAG_NAME );
		} else {
			update_option( WC_Stripe_Feature_Flags::ABILITIES_FEATURE_FLAG_NAME, $option_value );
		}

		if ( '' !== $filter_function ) {
			add_filter( 'wc_stripe_abilities_enabled', $filter_function );
		}

		$actual = WC_Stripe_Feature_Flags::is_abilities_enabled();

		if ( '' !== $filter_function ) {
			remove_filter( 'wc_stripe_abilities_enabled', $filter_function );
		}
		delete_option( WC_Stripe_Feature_Flags::ABILITIES_FEATURE_FLAG_NAME );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_is_abilities_enabled`.
	 *
	 * @return array
	 */
	public function provide_test_is_abilities_enabled(): array {
		return [
			'option absent, no filter — default off' => [
				'option_value'    => null,
				'filter_function' => '',
				'expected'        => false,
			],
			'option no, no filter'                   => [
				'option_value'    => 'no',
				'filter_function' => '',
				'expected'        => false,
			],
			'option yes, no filter'                  => [
				'option_value'    => 'yes',
				'filter_function' => '',
				'expected'        => true,
			],
			'option absent, filter true (override)'  => [
				'option_value'    => null,
				'filter_function' => '__return_true',
				'expected'        => true,
			],
			'option yes, filter false (override)'    => [
				'option_value'    => 'yes',
				'filter_function' => '__return_false',
				'expected'        => false,
			],
		];
	}
}
