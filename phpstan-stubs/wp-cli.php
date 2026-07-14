<?php
/**
 * PHPStan stubs for WP-CLI classes and functions.
 *
 * @package WooCommerce_Stripe
 */

/**
 * WP_CLI main class.
 */
class WP_CLI {
	/**
	 * @param string $command
	 * @param string $class
	 * @param array  $args
	 * @return void
	 */
	public static function add_command( string $command, string $class, array $args = [] ): void {}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function log( string $message ): void {}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function success( string $message ): void {}

	/**
	 * @param string $message
	 * @return never
	 */
	public static function error( string $message ): void {
		exit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * WP_CLI_Command base class.
 */
class WP_CLI_Command {}

namespace WP_CLI\Utils;

/**
 * @param array  $assoc_args
 * @param string $flag
 * @param mixed  $default
 * @return mixed
 */
function get_flag_value( array $assoc_args, string $flag, $default = false ) {
	return $default;
}
