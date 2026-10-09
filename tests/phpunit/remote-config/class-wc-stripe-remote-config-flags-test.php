<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Flags_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		$option = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Remote_Config_Flags::class, 'ENABLED_OVERRIDE_OPTION', 'string' );
		delete_option( $option );
		parent::tear_down();
	}

	/**
	 * The internal override option must force the channel on with 'yes' and
	 * off with 'no'; without an override the phase-1 default keeps it off.
	 *
	 * @param string $override Value stored in the override option.
	 * @param bool   $expected Expected is_remote_config_enabled() result.
	 *
	 * @dataProvider provide_override_values
	 */
	public function test_enabled_override_option( string $override, bool $expected ): void {
		$option = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Remote_Config_Flags::class, 'ENABLED_OVERRIDE_OPTION', 'string' );
		update_option( $option, $override );

		$this->assertSame( $expected, WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() );
	}

	/**
	 * Data provider for {@see test_enabled_override_option()}.
	 *
	 * @return array
	 */
	public function provide_override_values(): array {
		return [
			'yes forces enabled'                  => [ 'yes', true ],
			'no forces disabled'                  => [ 'no', false ],
			'no override: phase-1 default is off' => [ '', false ],
		];
	}

	/**
	 * With the option absent entirely, the phase-1 default must keep the
	 * channel off.
	 *
	 * @return void
	 */
	public function test_disabled_by_default(): void {
		$option = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Remote_Config_Flags::class, 'ENABLED_OVERRIDE_OPTION', 'string' );
		delete_option( $option );

		$this->assertFalse( WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() );
	}
}
