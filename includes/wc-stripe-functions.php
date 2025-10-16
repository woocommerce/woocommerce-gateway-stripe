<?php
/**
 * WooCommerce Stripe Functions
 *
 * General functions available on both the front-end and admin.
 *
 * @package WooCommerce_Stripe\Functions
 * @version 10.1.0
 */

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for str_contains function for PHP < 8.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for in the haystack.
	 * @return bool
	 */
	function str_contains( string $haystack, string $needle ) {
		return '' !== $needle && mb_strpos( $haystack, $needle ) !== false;
	}
}
