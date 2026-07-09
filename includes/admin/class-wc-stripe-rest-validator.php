<?php
/**
 * Class WC_Stripe_REST_Validator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validator class for REST controller.
 *
 * @since 10.9.0
 */
abstract class WC_Stripe_REST_Validator extends WC_Stripe_REST_Base_Controller {
	public const QUERY_OPERATORS = [
		'token'   => ':',
		'string'  => [
			':',
			'~',
		],
		'numeric' => [
			'=',
			'>',
			'<',
			'>=',
			'<=',
		],
	];
	/**
	 * Validate a Unix timestamp parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_timestamp( $value, WP_REST_Request $request, string $param ) {
		if ( empty( $value ) ) {
			return true;
		}

		$unix_timestamp_pattern = '^\d+$';

		if ( is_string( $value ) ) {
			return preg_match( '/' . $unix_timestamp_pattern . '/', $value ) === 1;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		$allowed_operators = [ 'gt', 'gte', 'lt', 'lte' ];

		foreach ( $value as $operator => $operand ) {
			if ( ! in_array( $operator, $allowed_operators ) ) {
				return false;
			}

			if ( ! is_scalar( $operand ) || preg_match( '/' . $unix_timestamp_pattern . '/', (string) $operand ) !== 1 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize an Unix timestamp parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 *
	 * @return mixed
	 */
	public static function sanitize_timestamp( $value, WP_REST_Request $request, string $param ) {
		if ( ! is_array( $value ) ) {
			$value = sanitize_text_field( $value );
		} else {
			$sanitized_value = [];

			foreach ( $value as $operator => $operand ) {
				$sanitized_value[ sanitize_key( $operator ) ] = sanitize_text_field( $operand );
			}

			$value = $sanitized_value;
		}

		return $value;
	}
}
