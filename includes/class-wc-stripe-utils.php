<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Utils
 */
class WC_Stripe_Utils {
	/**
	 * Convert an object to an array.
	 *
	 * @param $array array to convert to object
	 * @return object object converted from array
	 */
	public static function array_to_object( $array ) {
		return json_decode( wp_json_encode( $array ) );
	}
}
