<?php
/**
 * Class WC_Stripe_REST_Response_Filter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter a Stripe API response by a given allowed properties list.
 *
 * @since 10.9.0
 */
abstract class WC_Stripe_REST_Response_Filter {

	/**
	 * Filter a API response by a given allowed property list.
	 *
	 * @param object $response The response object.
	 * @param array $allowed_properties The property white list.
	 *
	 * @return object|array
	 */
	public static function filter_response( object $response, array $allowed_properties ) {
		$expanded_allowed_properties = static::expand_allowed_property_paths( $allowed_properties );

		return static::filter_object( $response, $expanded_allowed_properties );
	}

	protected static function expand_allowed_property_paths( array $allowed_property_paths ): array {
		$expanded_allowed_properties = [];

		foreach ( $allowed_property_paths as $path_as_string => $format_callback ) {
			$path = explode( '.', $path_as_string );

			$ref = &$expanded_allowed_properties;

			foreach ( $path as $property_name ) {
				if ( ! array_key_exists( $property_name, $ref ) ) {
					$ref[ $property_name ] = [];
				}

				$ref = &$ref[ $property_name ];
			}

			$ref = $format_callback;
		}

		return $expanded_allowed_properties;
	}

	/**
	 * Filter an object by a given allowed property list.
	 *
	 * @param object $obj The object.
	 * @param array $allowed_properties The property white list.
	 *
	 * @return object|array
	 */
	protected static function filter_object( object $obj, array $allowed_properties ) {
		if ( $obj instanceof stdClass ) {
			$filtered_object = new stdClass();

			foreach ( $allowed_properties as $property => $rule ) {
				if ( ! property_exists( $obj, $property ) ) {
					continue;
				}

				$property_value = $obj->{$property};

				if ( is_callable( $rule ) ) {
					$filtered_object->{$property} = $rule( $property_value );
				} else {
					$filtered_object->{$property} = ! is_array( $rule )
						? $property_value
						: static::filter_object( $property_value, $rule );
				}
			}

			return $filtered_object;
		}

		if ( is_array( $obj ) ) {
			return array_map(
				static fn ( $item ) => static::filter_object( $item, $allowed_properties ),
				$obj
			);

			$filtered_object = [];

			foreach ( $allowed_properties as $property => $rule ) {
				if ( ! array_key_exists( $property, $obj ) ) {
					continue;
				}

				$property_value = $obj[ $property ];

				$filtered_object[ $property ] = true === $rule
					? $property_value
					: static::filter_object( $property_value, $rule );
			}

			return $filtered_object;
		}

		return $obj;
	}
}
