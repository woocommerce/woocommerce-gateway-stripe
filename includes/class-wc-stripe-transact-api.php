<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Transact_API class.
 *
 * Handles Transact API requests.
 */
class WC_Stripe_Transact_API {
	private static $instance = null;

	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function is_transact_api_enabled( $api ): bool {
		// TODO: Add a feature flag and check.

		$jetpack_connection_manager = new \Automattic\Jetpack\Connection\Manager( 'woocommerce' );

		if ( ! $jetpack_connection_manager->is_connected() ) {
			return false;
		}

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		if ( ! $stripe_settings || ! isset( $stripe_settings['transact_onboarding_complete'] ) ) {
			return false;
		}

		return 'yes' === $stripe_settings['transact_onboarding_complete'];
	}

	public function call_transact_api( string $api, string $method, array $headers = [], array $request_body = [] ) {
		if ( ! self::is_transact_api_enabled( $api ) ) {
			return new WP_Error( 'transact_api_not_enabled', __( 'Transact API is not enabled.', 'woocommerce-gateway-stripe' ), [ 'api' => $api ] );
		}

		$site_id = \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return new WP_Error( 'site_id_not_found', __( 'Site ID not found.', 'woocommerce-gateway-stripe' ) );
		}

		$endpoint = sprintf( '/sites/%d/transact/stripe/proxy/v1/%s', $site_id, $api );

		if ( isset( $headers['Authorization'] ) ) {
			$headers['stripe-authorization'] = $headers['Authorization'];
			unset( $headers['Authorization'] );
		}

		WC_Stripe_Logger::debug(
			"Calling Transact API: $api",
			[
				'endpoint' => $endpoint,
				'method'   => $method,
				'body'     => $request_body,
				'headers'  => $headers,
			]
		);

		$response = \Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_blog(
			$endpoint,
			2,
			[
				'headers' => $headers,
				'method'  => $method,
				'timeout' => 60,
			],
			'GET' === $method ? null : $request_body,
			'wpcom'
		);

		WC_Stripe_Logger::debug(
			"Transact API response: $api",
			[
				'response' => $response,
			]
		);

		return $response;
	}
}
