<?php

defined( 'ABSPATH' ) || exit;

/**
 * Filter a Stripe API response by a given allowed properties list.
 *
 * @since 11.1.0
 */
abstract class WC_Stripe_REST_Response_Filter {
	private const IDX_PATH = 'path';

	/**
	 * Filter a API response by a given allowed property list.
	 *
	 * @param stdClass $response           The response object.
	 * @param array    $allowed_properties The property white list.
	 *
	 * @return stdClass
	 */
	public static function filter_response( stdClass $response, array $allowed_properties ) {
		$expanded_allowed_properties = self::expand_allowed_property_paths( $allowed_properties );

		return self::filter_value( $response, $expanded_allowed_properties );
	}

	private static function expand_allowed_property_paths( array $allowed_property_paths ): array {
		$expanded_allowed_properties = [];

		foreach ( $allowed_property_paths as $path_as_string ) {
			$path = explode( '.', $path_as_string );

			$ref = &$expanded_allowed_properties;

			foreach ( $path as $property_name ) {
				if ( 0 === count( $ref ) || ! isset( $ref[ self::IDX_PATH ][ $property_name ] ) ) {
					$ref[ self::IDX_PATH ][ $property_name ] = [];
				}

				$ref = &$ref[ self::IDX_PATH ][ $property_name ];
			}
		}

		return $expanded_allowed_properties;
	}

	/**
	 * Filter a value by a given allowed property list.
	 *
	 * @param stdClass $value The object.
	 * @param array $allowed_properties The property white list.
	 *
	 * @return mixed
	 */
	private static function filter_value( $value, array $allowed_properties ) {
		if ( is_object( $value ) ) {
			$property_path = isset( $allowed_properties[ self::IDX_PATH ] ) ? $allowed_properties[ self::IDX_PATH ] : null;

			$filtered_object = new stdClass();

			if ( ! $property_path ) {
				return $filtered_object;
			}

			foreach ( $property_path as $property => $rule ) {
				if ( ! property_exists( $value, $property ) ) {
					continue;
				}

				$property_value = $value->{$property};

				if ( ! isset( $rule[ self::IDX_PATH ] ) || ! is_array( $rule[ self::IDX_PATH ] ) ) {
					if ( is_object( $property_value ) ) {
						$property_value = self::deep_clone( $property_value );
					}

					$filtered_object->{$property} = $property_value;
				} else {
					$filtered_object->{$property} = self::filter_value( $property_value, $rule );
				}
			}

			return $filtered_object;
		}

		if ( is_array( $value ) ) {
			return array_map(
				static fn ( $item ) => self::filter_value( $item, $allowed_properties ),
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
}
