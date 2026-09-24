<?php
/**
 * Reads option values as they are actually stored, for tests that assert on stored bytes.
 *
 * @package WooCommerce_Stripe/Tests/Helpers
 */

/**
 * Bypasses the options cache and WordPress's unserialization so tests can compare the exact bytes
 * in the database. `get_option()` is unsuitable for that: it returns a rehydrated value, so a
 * serialized array and the string `Array` are indistinguishable through it.
 */
class WC_Stripe_Option_Inspector {

	/**
	 * Reads an option's stored value as a string straight from the options table.
	 *
	 * @param string $option_name Option to read.
	 * @return string|null The stored value, or null when the option does not exist.
	 */
	public static function read_option_as_string( string $option_name ): ?string {
		global $wpdb;

		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name )
		);

		return null === $value ? null : (string) $value;
	}
}
