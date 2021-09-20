<?php
/**
 * Wrapper class to use to mock dependency classes that use static methods
 *
 * @package WooCommerce\Tests
 */

/**
 * Class WC_Helper_Static_Method_Mock.
 *
 * It would be a bad idea to use this anywhere outside of a unit test.
 */
class WC_Helper_Static_Method_Mock {
	/**
	 * Mock object of class containing static methods we would like to mock.
	 *
	 * @var object
	 */
	private static $mock;

	/**
	 * Setter for $mock.
	 */
	public static function set_mock( $mock ) {
		self::$mock = $mock;
	}

	/**
	 * We will pass on any static requests to this class to internal mock object.
	 */
	public static function __callStatic( $method, $args ) {
		return call_user_func( [ self::$mock, $method ], $args );
	}
}
