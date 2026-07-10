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

	/**
	 * Validate a 'query' parameter value.
	 *
	 * @param string $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 * @param array $rest_query_args Query field types.
	 *
	 * @return bool
	 */
	public static function validate_query( $value, WP_REST_Request $request, string $param, $rest_query_args ) {
		if ( ! is_array( $value ) || 0 === count( $value ) ) {
			return false;
		}

		foreach ( $value as $i => $query_field ) {
			if ( ! is_array( $query_field ) ) {
				return false;
			}

			foreach ( $query_field as $key => $value ) {
				if ( ! in_array( $key, [ 'field' , 'value', 'operator' ] ) ) {
					return false;
				}

				if ( ! is_scalar( $value ) ) {
					return false;
				}
			}

			if ( ! isset( $query_field['field'] ) || '' === $query_field['field'] ) {
				return false;
			}

			if ( ! isset( $query_field['value'] ) || '' === $query_field['value'] ) {
				return false;
			}

			if ( ! isset( $query_field['operator'] ) ) {
				continue;
			}

			$query_field_name      = $query_field['field'];
			$query_field_type      = $rest_query_args[ $query_field_name ];
			$query_field_operators = self::QUERY_OPERATORS[ $query_field_type ];

			if ( ( ! is_array( $query_field_operators ) && $query_field_operators !== $query_field['operator'] ) ||
				( ( is_array( $query_field_operators ) && ! in_array( $query_field['operator'], $query_field_operators ) ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize a 'query' parameter value.
	 *
	 * @param array $value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param The parameter name.
	 * @param array $rest_query_args Query field types.
	 *
	 * @return mixed
	 */
	public static function sanitize_query( $value, WP_REST_Request $request, string $param, $rest_query_args ) {
		foreach ( $value as $i => $query_field ) {
			$query_field_name      = $query_field['field'];
			$query_field_type      = $rest_query_args[ $query_field_name ];
			$query_field_operators = self::QUERY_OPERATORS[ $query_field_type ];

			if ( ! isset( $query_field['operator'] ) ) {
				$value[ $i ]['operator'] = is_array( $query_field_operators ) ? reset( $query_field_operators ) : $query_field_operators;
			}

			if ( 'string' === $query_field_type || 'token' === $query_field_type ) {
				$value[ $i ]['value'] = '"' . $query_field['value'] . '"';
			}
		}

		return $value;
	}
}
