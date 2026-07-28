<?php

defined( 'ABSPATH' ) || exit;

/**
 * Filter a Stripe API response by a given allowed properties list.
 *
 * @since 10.9.0
 */
abstract class WC_Stripe_REST_Response_Filter {

	public const IDX_FORMAT_CALLBACK = 'format';
	public const IDX_PATH            = 'path';

	/**
	 * Filter a API response by a given allowed property list.
	 *
	 * @param object $response The response object.
	 * @param array $allowed_properties The property white list.
	 *
	 * @return object|array
	 */
	public static function filter_response( object $response, array $allowed_properties ) {
		if ( ! $allowed_properties ) {
			return $response;
		}
		$expanded_allowed_properties = static::expand_allowed_property_paths( $allowed_properties );

		return static::filter_value( $response, $expanded_allowed_properties );
	}

	protected static function expand_allowed_property_paths( array $allowed_property_paths ): array {
		$expanded_allowed_properties = [];

		foreach ( $allowed_property_paths as $path_as_string => $format_callback ) {
			$path = explode( '.', $path_as_string );

			$ref = &$expanded_allowed_properties;

			foreach ( $path as $property_name ) {
				if ( 0 === count( $ref ) || ! isset( $ref[ static::IDX_PATH ][ $property_name ] ) ) {
					$ref[ static::IDX_PATH ][ $property_name ] = [];
				}

				$ref = &$ref[ static::IDX_PATH ][ $property_name ];
			}

			$ref[ static::IDX_FORMAT_CALLBACK ] = $format_callback;
		}

		return $expanded_allowed_properties;
	}

	/**
	 * Filter a value by a given allowed property list.
	 *
	 * @param mixed $value The object.
	 * @param array $allowed_properties The property white list.
	 *
	 * @return mixed
	 */
	protected static function filter_value( $value, array $allowed_properties ) {
		if ( is_object( $value ) ) {
			$format_callback = isset( $allowed_properties[ static::IDX_FORMAT_CALLBACK ] ) ? $allowed_properties[ static::IDX_FORMAT_CALLBACK ] : null;
			$property_path   = isset( $allowed_properties[ static::IDX_PATH ] ) ? $allowed_properties[ static::IDX_PATH ] : null;

			if ( ! is_null( $format_callback ) ) {
				if ( is_callable( $format_callback ) ) {
					$filtered_object = $format_callback( $value );
				} else {
					$filtered_object = self::deep_clone( $value );
				}
			} else {
				$filtered_object = new stdClass();
			}

			if ( ! $property_path ) {
				return $filtered_object;
			}

			foreach ( $property_path as $property => $rule ) {
				if ( ! property_exists( $value, $property ) ) {
					continue;
				}

				$property_value = $value->{$property};

				if ( ! isset( $rule[ static::IDX_PATH ] ) || ! is_array( $rule[ static::IDX_PATH ] ) ) {
					if ( is_object( $property_value ) ) {
						$property_value = self::deep_clone( $property_value );
					}

					if ( is_callable( $rule[ static::IDX_FORMAT_CALLBACK ] ) ) {
						$format_callback = $rule[ static::IDX_FORMAT_CALLBACK ];
						$property_value  = $format_callback( $property_value );
					}

					$filtered_object->{$property} = $property_value;
				} else {
					$filtered_object->{$property} = static::filter_value( $property_value, $rule );
				}
			}

			return $filtered_object;
		}

		if ( is_array( $value ) ) {
			return array_map(
				static fn ( $item ) => static::filter_value( $item, $allowed_properties ),
				$value
			);
		}

		return $value;
	}

	/**
	 * Deep clone an object.
	 *
	 * @param mixed $obj The object.
	 *
	 * @return object
	 */
	private static function deep_clone( $obj ) {
		return unserialize( serialize( $obj ) );
	}

	/**
	 * Format a money amount
	 *
	 * @param int|float $value The amount in cents.
	 *
	 * @return string
	 */
	public static function money_format( $value ) {
		return number_format( round( $value / 100, 2 ), 2 );
	}

	/**
	 * Format a unix timestamp as a date
	 *
	 * @param int $value The timestamp.
	 *
	 * @return string
	 */
	public static function date_format( $value ) {
		return gmdate( 'F j, Y', $value );
	}

	/**
	 * Format a payment method details as a string
	 *
	 * @param object $value The payment method details.
	 *
	 * @return string
	 */
	public static function payment_method_details_format( $value ) {
		switch ( $value->type ?? '' ) {
			case 'card':
				$result = ucfirst( $value->card->network ?? '' );

				if ( $value->card->last4 ?? '' ) {
					$result .= ' (•••• ' . ( $value->card->last4 ?? '' ) . ')';
				}
				break;
			default:
				$result = '';
		}

		return $result;
	}

	/**
	 * Format a payment destination as a string
	 *
	 * @param object $value The payment method details.
	 *
	 * @return string
	 */
	public static function destination_bank_format( $value ) {
		return ( ( $value->bank_name ?? '' ) ? ' ' . $value->bank_name : '' ) . '( •••• ' . ( $value->last4 ?? '••••' ) . ' )';
	}

	/**
	 * Format a status
	 *
	 * @param string $value The payment method details.
	 *
	 * @return string
	 */
	public static function status_format( $value ) {
		return ucfirst( str_replace( '_', ' ', $value ) );
	}
}
