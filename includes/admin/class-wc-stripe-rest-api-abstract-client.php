<?php
/**
 * Class WC_Stripe_REST_API_Abstract_Client
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller exposing Stripe payment intent details to the admin UI.
 *
 * @since 10.9.0
 */
abstract class WC_Stripe_REST_API_Abstract_Client {
	/**
	 * Builds an array of parameters to forward to Stripe API
	 * by selecting only the parameters that are present in incoming request from a given list of parameter names.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request An incoming REST request.
	 * @param array $params_to_forward                       Names of params to forward.
	 * @param array $expand_param                            Array of value to populate the 'expand' Stripe API param.
	 *
	 * @return array
	 */
	public static function build_params_to_forward( WP_REST_Request $request, array $params_to_forward, array $expand_param ) {
		$stripe_params = array_intersect_key(
			$request->get_params(),
			array_flip( $params_to_forward )
		);

		$stripe_params['expand'] = $expand_param;

		return $stripe_params;
	}

	/**
	 * Fetch data from an Stripe API endpoint and returns its raw data or a WP_Error if an error occurs.
	 *
	 * @param string $endpoint The Stripe endpoint.
	 * @param array $params    Parameters to pass to the endpoint.
	 *
	 * @return stdClass|WP_Error
	 */
	public static function fetch_from_stripe( string $endpoint, array $params = [] ) {
		$query_string = 0 === count( $params ) ? '' : http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$stripe_resource_url = $endpoint . ( '' === $query_string ? '' : '?' . $query_string );

		$response = WC_Stripe_API::retrieve( $stripe_resource_url );

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_error',
				__( 'Unable to fetch data from Stripe.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 401 ]
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_object( $response ) && isset( $response->error ) ) {
			$error_code    = isset( $response->error->code ) ? (string) $response->error->code : 'wc_stripe_api_error';
			$error_message = isset( $response->error->message ) ? (string) $response->error->message : __( 'Stripe API returned an error.', 'woocommerce-gateway-stripe' );

			return new WP_Error( $error_code, $error_message, [ 'status' => 400 ] );
		}

		return $response;
	}
}
