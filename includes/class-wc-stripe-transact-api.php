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

	/**
	 * The API version for the proxy endpoint.
	 *
	 * @var int
	 */
	private const WPCOM_PROXY_ENDPOINT_API_VERSION = 2;

	/**
	 * The timeout for requests to the WPCOM proxy endpoint.
	 *
	 * @var int
	 */
	private const WPCOM_PROXY_REQUEST_TIMEOUT = 60;

	/**
	 * The base for the proxy REST endpoint.
	 *
	 * @var string
	 */
	private const WPCOM_PROXY_REST_BASE = 'transact/stripe/proxy/v1';

	/**
	 * Instance of WC_Stripe_Transact_API.
	 *
	 * @var WC_Stripe_Transact_API
	 */
	private static $instance = null;

	/**
	 * Get instance of WC_Stripe_Transact_API.
	 *
	 * @return WC_Stripe_Transact_API
	 */
	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if the Transact API is enabled.
	 *
	 * @return bool
	 */
	public function is_transact_api_enabled(): bool {

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

	/**
	 * Send a request to the Transact platform.
	 *
	 * @param string $method The HTTP method to use.
	 * @param string $endpoint The endpoint to request.
	 * @param array  $headers The headers to send.
	 * @param array  $request_body The request body.
	 *
	 * @return array|null The API response body, or null if the request fails.
	 */
	public function send_wpcom_proxy_request( $method, $endpoint, $headers = [], $request_body = [] ) {
		$site_id = \Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			WC_Stripe_Logger::error( sprintf( 'Site ID not found. Cannot send request to %s.', $endpoint ) );
			throw new Exception( 'Site ID not found. Cannot send proxy request.' );
		}

		if ( isset( $headers['Authorization'] ) ) {
			$headers['Stripe-Authorization'] = $headers['Authorization'];
			unset( $headers['Authorization'] );
		}

		$response = \Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_blog(
			sprintf( '/sites/%d/%s/%s', $site_id, self::WPCOM_PROXY_REST_BASE, $endpoint ),
			self::WPCOM_PROXY_ENDPOINT_API_VERSION,
			[
				'headers' => $headers,
				'method'  => $method,
				'timeout' => 70,
			],
			'GET' === $method ? null : wp_json_encode( $request_body ),
			'wpcom'
		);

		return $response;
	}
}
