<?php
/**
 * Class WC_Stripe_REST_Args_Validator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for validating Stripe API endpoint specific argument types
 *
 * @since 10.9.0
 */
abstract class WC_Stripe_REST_Args_Validator {
	public const CUSTOMER_ID_PATTERN       = 'cus_[A-Za-z0-9_]+';
	public const PAYMENT_INTENT_ID_PATTERN = 'pi_[A-Za-z0-9_]+';
	public const PAYOUT_ID_PATTERN         = 'po_[A-Za-z0-9_]+';

	/**
	 * Validate a parameter value by a regexp pattern.
	 *
	 * @param string $regexp_pattern The regexp pattern to match.
	 * @param string $param_value The parameter value.
	 *
	 * @return bool
	 */
	private static function validate_by_regexp_pattern( $regexp_pattern, $param_value ) {
		if ( ! is_string( $param_value ) ) {
			return false;
		}

		return 1 === preg_match( '/^' . $regexp_pattern . '$/', $param_value );
	}

	/**
	 * Validate a parameter value that should be a customer ID.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_customer_id( $param_value, $request, $param_name ) {
		return self::validate_by_regexp_pattern( self::CUSTOMER_ID_PATTERN, $param_value );
	}

	/**
	 * Validate a pagination cursor (starting_after or ending_before) parameter value that should be of a given type of ID.
	 * Also raise an error if both starting_after and ending_before parameter are specified.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 * @param string $id_type_regexp_pattern A regular expression pattern to match the ID against.
	 *
	 * @return WP_Error|bool
	 */
	public static function validate_pagination_cursor( $param_value, $request, $param_name, $id_type_regexp_pattern ) {
		if ( $request->has_param( 'starting_after' ) && $request->has_param( 'ending_before' ) ) {
			return new WP_Error(
				'invalid_pagination_cursor',
				__( 'Received both starting_after and ending_before parameters. Please pass in only one.', 'woocommerce-gateway-stripe' )
			);
		}

		return self::validate_by_regexp_pattern( $id_type_regexp_pattern, $param_value );
	}

	/**
	 * Validate a parameter value as a payment intent id pagination cursor.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return WP_Error|bool
	 */
	public static function validate_payment_intent_pagination_cursor( $param_value, $request, $param_name ) {
		return self::validate_pagination_cursor( $param_value, $request, $param_name, self::PAYMENT_INTENT_ID_PATTERN );
	}

	/**
	 * Validate a parameter value as a payout id pagination cursor.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return WP_Error|bool
	 */
	public static function validate_payout_pagination_cursor( $param_value, $request, $param_name ) {
		return self::validate_pagination_cursor( $param_value, $request, $param_name, self::PAYOUT_ID_PATTERN );
	}

	/**
	 * Sanitize created parameter value.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return mixed
	 */
	public static function sanitize_unix_timestamp_range( $param_value, $request, $param_name ) {
		if ( ! is_array( $param_value ) ) {
			$sanitized_value = self::is_valid_timestamp( $param_value ) ? (int) $param_value : '';
		} else {
			$sanitized_value = [];

			foreach ( $param_value as $operator => $operand ) {
				if ( self::is_valid_timestamp( $operand ) ) {
					$sanitized_value[ sanitize_key( $operator ) ] = (int) $operand;
				} else {
					$sanitized_value[ sanitize_key( $operator ) ] = '';
				}
			}
		}

		return $sanitized_value;
	}

	/**
	 * Validate created parameter value
	 *
	 * Validates that the parameter is either a Unix timestamp containing digits only,
	 * or an array of Unix timestamps keyed by comparison operators (gt, gte, lt, lte).
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_unix_timestamp_range( $param_value, $request, $param_name ) {
		if ( self::is_valid_timestamp( $param_value ) ) {
			return true;
		}

		if ( ! is_array( $param_value ) ) {
			return false;
		}

		$allowed_operators = [ 'gt', 'gte', 'lt', 'lte' ];

		foreach ( $param_value as $operator => $operand ) {
			if ( ! in_array( $operator, $allowed_operators, true ) ) {
				return false;
			}

			if ( ! self::is_valid_timestamp( $operand ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a timestamp value.
	 *
	 * Validates that the value represents a non-negative integer, either as an int or as a non-empty string containing digits only.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public static function is_valid_timestamp( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		return ctype_digit( $value ) && ( (int) $value >= 0 );
	}

	/**
	 * Validate that a parameter is a non-empty string.
	 *
	 * @param string $param_value The parameter value.
	 * @param WP_REST_Request<array<string, mixed>> $request The incoming REST request.
	 * @param string $param_name The parameter name.
	 *
	 * @return bool
	 */
	public static function validate_non_empty_string( $param_value, $request, $param_name ) {
		return '' !== trim( $param_value );
	}
}
