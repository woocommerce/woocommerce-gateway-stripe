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

		throw new Exception( 'Invalid expected type: ' . $expected_type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Drops WC_Stripe_Database_Cache's in-memory entries whose key contains $key_part.
	 *
	 * The per-test database rollback removes the stored rows, but the in-memory copy lives for the
	 * whole PHP process, so a value written in one test would otherwise still be read in the next.
	 *
	 * @param string $key_part Part of the cache key to match, e.g. 'paid_cart_'.
	 * @return void
	 */
	public static function forget_database_cache_entries( string $key_part ): void {
		$property = new ReflectionProperty( WC_Stripe_Database_Cache::class, 'in_memory_cache' );
		$property->setAccessible( true );

		$cache = (array) $property->getValue();
		foreach ( array_keys( $cache ) as $key ) {
			if ( false !== strpos( (string) $key, $key_part ) ) {
				unset( $cache[ $key ] );
			}
		}
		$property->setValue( null, $cache );
	}
}
