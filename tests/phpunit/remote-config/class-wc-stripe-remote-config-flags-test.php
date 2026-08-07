<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Flags_Test extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION );
		parent::tear_down();
	}

	/**
	 * The internal override option must force the channel on with 'yes' and
	 * off with 'no', regardless of the environment default.
	 *
	 * @param string $override Value stored in the override option.
	 * @param bool   $expected Expected is_remote_config_enabled() result.
	 *
	 * @dataProvider provide_override_values
	 */
	public function test_enabled_override_option( string $override, bool $expected ): void {
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, $override );

		$this->assertSame( $expected, WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() );
	}

	/**
	 * Data provider for {@see test_enabled_override_option()}.
	 *
	 * @return array
	 */
	public function provide_override_values(): array {
		return [
			'yes forces enabled' => [ 'yes', true ],
			'no forces disabled' => [ 'no', false ],
		];
	}
}
