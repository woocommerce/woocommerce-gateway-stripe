<?php declare(strict_types=1);

/**
 * Helper class for testing Stripe functionality.
 */
class WC_Stripe_Test_Helper {
	/**
	 * Get the value of a class constant, which may not be public, and enforce a specific value type.
	 *
	 * @param string $class         The class name.
	 * @param string $const_name    The constant name.
	 * @param string $expected_type The expected type of the constant value. One of 'string', 'int', 'bool', or 'float'.
	 *
	 * @return mixed The value of the class constant.
	 */
	public static function get_class_const_value( string $class, string $const_name, string $expected_type ) {
		$constant = new ReflectionClassConstant( $class, $const_name );
		$value    = $constant->getValue();

		if ( 'string' === $expected_type ) {
			return (string) $value;
		}

		if ( 'int' === $expected_type ) {
			return (int) $value;
		}

		if ( 'bool' === $expected_type ) {
			return (bool) $value;
		}

		if ( 'float' === $expected_type ) {
			return (float) $value;
		}

		throw new Exception( 'Invalid expected type: ' . $expected_type );
	}
}
