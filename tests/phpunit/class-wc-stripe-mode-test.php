<?php

/**
 * Class WC_Stripe_Mode
 *
 * @package WooCommerce/Stripe/WC_Stripe_Mode
 *
 * Class WC_Stripe_Mode tests.
 */
class WC_Stripe_Mode_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_live` and `is_test` methods.
	 *
	 * @param string $testmode  The testmode setting value ('yes' or 'no').
	 * @param bool   $is_live   Expected result of `is_live()`.
	 * @param bool   $is_test   Expected result of `is_test()`.
	 * @return void
	 * @dataProvider provide_test_mode
	 */
	public function test_mode( string $testmode, bool $is_live, bool $is_test ) {
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = $testmode;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->assertSame( $is_live, WC_Stripe_Mode::is_live() );
		$this->assertSame( $is_test, WC_Stripe_Mode::is_test() );
	}

	/**
	 * Data provider for `test_mode`.
	 *
	 * @return array
	 */
	public function provide_test_mode(): array {
		return [
			'test mode enabled'  => [ 'yes', false, true ],
			'test mode disabled' => [ 'no', true, false ],
		];
	}
}
