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

		foreach ( $rest_args as $search_param_name => $search_param_definition ) {
			$search_param_value = $request->get_param( $search_param_name );

			if ( '' === $search_param_value || is_null( $search_param_value ) ) {
				continue;
			}

			$search_params[ $search_param_name ] = $search_param_value;
		}

		return $search_params;
	}

	/**
	 * Given an incoming REST request, build and return a query parameters string to be appended to Stripe API request URL.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 *
	 * @return string
	 */
	public static function build_http_query_string_from_request( $request, $rest_args ): string {
		return http_build_query( WC_Stripe_REST_Helper::build_http_query_array_from_request( $request, $rest_args ) );
	}
}
