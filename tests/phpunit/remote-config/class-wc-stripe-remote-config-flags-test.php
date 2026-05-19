<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Flags_Test extends WP_UnitTestCase {

	public function test_is_known_flag_returns_true_for_declared_flag(): void {
		$this->assertTrue( WC_Stripe_Remote_Config_Flags::is_known_flag( 'optimized_checkout' ) );
	}

	public function test_is_known_flag_returns_false_for_unknown_flag(): void {
		$this->assertFalse( WC_Stripe_Remote_Config_Flags::is_known_flag( 'no_such_flag' ) );
	}

	public function test_validate_value_accepts_correct_type(): void {
		$this->assertTrue( WC_Stripe_Remote_Config_Flags::validate_value( 'optimized_checkout', true ) );
		$this->assertTrue( WC_Stripe_Remote_Config_Flags::validate_value( 'optimized_checkout', false ) );
	}

	/**
	 * @dataProvider provide_invalid_values
	 */
	public function test_validate_value_rejects_wrong_type( $value ): void {
		$this->assertFalse( WC_Stripe_Remote_Config_Flags::validate_value( 'optimized_checkout', $value ) );
	}

	public function provide_invalid_values(): array {
		return [
			'string' => [ 'true' ],
			'int'    => [ 1 ],
			'null'   => [ null ],
			'array'  => [ [ true ] ],
			'object' => [ new stdClass() ],
		];
	}

	public function test_validate_value_returns_false_for_unknown_flag(): void {
		$this->assertFalse( WC_Stripe_Remote_Config_Flags::validate_value( 'no_such_flag', true ) );
	}
}
