<?php
/**
 * Class WC_Stripe_REST_Helper
 */

defined( 'ABSPATH' ) || exit;

/**
 *
 * Helper class for REST controller.
 *
 * @since 10.9.0
 */
abstract class WC_Stripe_REST_Helper extends WC_Stripe_REST_Base_Controller {
	/**
	 * Given an incoming REST request, build and return an array of query parameters to be appended to Stripe API request URL.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 * @param array $rest_args REST endpoint params.
	 *
	 * @return array
	 */
	public static function build_http_query_array_from_request( $request, $rest_args ): array {
		/**
		 * Route args.
		 *
		 * @var array<string, mixed> $rest_args
		 */

		$search_params = [];
		$has_query     = false;

		foreach ( $rest_args as $search_param_name => $search_param_definition ) {
			$search_param_value = $request->get_param( $search_param_name );

			if ( '' === $search_param_value || is_null( $search_param_value ) ) {
				continue;
			}

			$search_params[ $search_param_name ] = $search_param_value;

			if ( 'query' === $search_param_name ) {
				$has_query = true;
			}
		}

		if ( $has_query ) {
			$search_params = [ 'query' => static::build_query_param( $request->get_param( 'query' ) ) ];
		}

		return $search_params;
	}

	/**
	 * Implode and return a query param fields.
	 *
	 * @param array $query_param 'Query' param fields.
	 *
	 * @return string
	 */
	public static function build_query_param( $query_param ) {
		$query_as_string = '';

		foreach ( $query_param as $query_param_item ) {
			$query_as_string .= ( $query_as_string ? ' or ' : '' ) . $query_param_item['field'] . $query_param_item['operator'] . $query_param_item['value'];
		}

		return $query_as_string;
	}

	/**
	 * Given an incoming REST request, build and return a query parameters string to be appended to Stripe API request URL.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 * @param array $rest_args REST endpoint params.
	 *
	 * @return string
	 */
	public static function build_http_query_string_from_request( $request, $rest_args ): string {
		return http_build_query( WC_Stripe_REST_Helper::build_http_query_array_from_request( $request, $rest_args ) );
	}

	/**
	 * Checks if a given request contains a query param.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request A REST request.
	 *
	 * @return bool
	 */
	public static function is_search_request( $request ) {
		return $request->has_param( 'query' );
	}
}
