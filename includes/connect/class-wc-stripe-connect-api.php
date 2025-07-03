<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WOOCOMMERCE_CONNECT_SERVER_URL' ) ) {
	define( 'WOOCOMMERCE_CONNECT_SERVER_URL', 'https://api.woocommerce.com/' );
}

if ( ! class_exists( 'WC_Stripe_Connect_API' ) ) {
	/**
	 * Stripe Connect API class.
	 */
	class WC_Stripe_Connect_API {

		const WOOCOMMERCE_CONNECT_SERVER_API_VERSION = '3';

		/**
		 * Send request to Connect Server to initiate Stripe OAuth
		 *
		 * @param string $return_url The URL to return to after the OAuth is completed.
		 * @param string $mode       Optional. The mode to connect to. 'live' or 'test'. Default is 'live'.
		 *
		 * @return array|WP_Error The response from the server.
		 */
		public function get_stripe_oauth_init( $return_url, $mode = 'live' ) {
			$current_user                   = wp_get_current_user();
			$account                        = WC_Stripe::get_instance()->account->get_cached_account_data( $mode );
			$business_data                  = [];
			$business_data['url']           = get_site_url();
			$business_data['business_name'] = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$business_data['first_name']    = $current_user->user_firstname;
			$business_data['last_name']     = $current_user->user_lastname;
			$business_data['phone']         = '';
			$business_data['currency']      = get_woocommerce_currency();

			$wc_countries = WC()->countries;

			if ( method_exists( $wc_countries, 'get_base_address' ) ) {
				$business_data['country']        = $wc_countries->get_base_country();
				$business_data['street_address'] = $wc_countries->get_base_address();
				$business_data['city']           = $wc_countries->get_base_city();
				$business_data['state']          = $wc_countries->get_base_state();
				$business_data['zip']            = $wc_countries->get_base_postcode();
			} else {
				$base_location                   = wc_get_base_location();
				$business_data['country']        = $base_location['country'];
				$business_data['street_address'] = '';
				$business_data['city']           = '';
				$business_data['state']          = $base_location['state'];
				$business_data['zip']            = '';
			}

			$request = [
				'returnUrl'    => $return_url,
				'businessData' => $business_data,
			];

			// If the store is already connected to an account and the account is connected to an Application, send the account ID so
			// api.woocommerce.com can determine the type of connection needed.
			if ( isset( $account['id'], $account['controller']['type'] ) && 'application' === $account['controller']['type'] ) {
				$request['accountId'] = $account['id'];
			}

			$path = 'test' === $mode ? '/stripe-sandbox/oauth-init' : '/stripe/oauth-init';

			return $this->request( 'POST', $path, $request );
		}

		/**
		 * Send request to Connect Server for Stripe keys
		 *
		 * @param string $code OAuth server code.
		 * @param string $type Optional. The type of the connection. 'connect' or 'app'. Default is 'connect'.
		 * @param string $mode Optional. The mode to connect to. 'live' or 'test'. Default is 'live'.
		 *
		 * @return array
		 */
		public function get_stripe_oauth_keys( $code, $type = 'connect', $mode = 'live' ) {
			$request = [ 'code' => $code ];

			if ( 'app' === $type ) {
				$request['mode'] = $mode;
				return $this->request( 'POST', '/stripe/app-oauth-keys', $request );
			}

			$path = 'test' === $mode ? '/stripe-sandbox/oauth-keys' : '/stripe/oauth-keys';
			return $this->request( 'POST', $path, $request );
		}

		/**
		 * Sends a request to the Connect Server for Stripe App refreshed keys.
		 *
		 * @since 8.6.0
		 *
		 * @param string $refresh_token Stripe App OAuth refresh token.
		 * @param string $mode          Optional. The mode to refresh keys for. 'live' or 'test'. Default is 'live'.
		 *
		 * @return array
		 */
		public function refresh_stripe_app_oauth_keys( $refresh_token, $mode = 'live' ) {
			$request = [
				'refreshToken' => $refresh_token,
				'mode'         => $mode,
			];
			return $this->request( 'POST', '/stripe/app-oauth-keys-refresh', $request );
		}

		/**
		 * General OAuth request method.
		 *
		 * @param string $method request method.
		 * @param string $path   path for request.
		 * @param array  $body   request body.
		 *
		 * @return array|WP_Error
		 */
		protected function request( $method, $path, $body = [] ) {

			if ( ! is_array( $body ) ) {
				return new WP_Error(
					'request_body_should_be_array',
					__( 'Unable to send request to WooCommerce Connect server. Body must be an array.', 'woocommerce-gateway-stripe' )
				);
			}

			$url = trailingslashit( WOOCOMMERCE_CONNECT_SERVER_URL );
			$url = apply_filters_deprecated(
				'wc_connect_server_url',
				[ $url ],
				'9.6.0',
				'',
				'The wc_connect_server_url filter is deprecated since WooCommerce Stripe Gateway 9.6.0, and will be removed in a future version.'
			);
			$url = trailingslashit( $url ) . ltrim( $path, '/' );

			// Add useful system information to requests that contain bodies.
			if ( in_array( $method, [ 'POST', 'PUT' ], true ) ) {
				$body = $this->request_body( $body );
				$body = wp_json_encode(
					apply_filters_deprecated(
						'wc_connect_api_client_body',
						[ $body ],
						'9.6.0',
						'',
						'The wc_connect_api_client_body filter is deprecated since WooCommerce Stripe Gateway 9.6.0, and will be removed in a future version.'
					)
				);

				if ( ! $body ) {
					return new WP_Error(
						'unable_to_json_encode_body',
						__( 'Unable to encode body for request to WooCommerce Connect server.', 'woocommerce-gateway-stripe' )
					);
				}
			}

			$headers = $this->request_headers();
			if ( is_wp_error( $headers ) ) {
				return $headers;
			}

			$http_timeout = 60; // 1 minute
			wc_set_time_limit( $http_timeout + 10 );
			$args = [
				'headers'     => $headers,
				'method'      => $method,
				'body'        => $body,
				'redirection' => 0,
				'compress'    => true,
				'timeout'     => $http_timeout,
			];

			$args          = apply_filters_deprecated(
				'wc_connect_request_args',
				[ $args ],
				'9.6.0',
				'',
				'The wc_connect_request_args filter is deprecated since WooCommerce Stripe Gateway 9.6.0, and will be removed in a future version.'
			);
			$response      = wp_remote_request( $url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );
			$content_type  = wp_remote_retrieve_header( $response, 'content-type' );

			if ( false === strpos( $content_type, 'application/json' ) ) {
				if ( 200 !== $response_code ) {
					return new WP_Error(
						'wcc_server_error',
						sprintf(
							// Translators: HTTP error code.
							__( 'Error: The WooCommerce Connect server returned HTTP code: %d', 'woocommerce-gateway-stripe' ),
							$response_code
						)
					);
				} else {
					return new WP_Error(
						'wcc_server_error_content_type',
						sprintf(
							// Translators: content-type error code.
							__( 'Error: The WooCommerce Connect server returned an invalid content-type: %s.', 'woocommerce-gateway-stripe' ),
							$content_type
						)
					);
				}
			}

			$response_body = wp_remote_retrieve_body( $response );
			if ( ! empty( $response_body ) ) {
				$response_body = json_decode( $response_body );
			}

			if ( 200 !== $response_code ) {
				if ( empty( $response_body ) ) {
					return new WP_Error(
						'wcc_server_empty_response',
						sprintf(
							// Translators: HTTP error code.
							__( 'Error: The WooCommerce Connect server returned ( %d ) and an empty response body.', 'woocommerce-gateway-stripe' ),
							$response_code
						)
					);
				}

				$error   = property_exists( $response_body, 'error' ) ? $response_body->error : '';
				$message = property_exists( $response_body, 'message' ) ? $response_body->message : '';
				$data    = property_exists( $response_body, 'data' ) ? $response_body->data : '';

				return new WP_Error(
					'wcc_server_error_response',
					sprintf(
						/* translators: %1$s: error code, %2$s: error message, %3$d: HTTP response code */
						__( 'Error: The WooCommerce Connect server returned: %1$s %2$s ( %3$d )', 'woocommerce-gateway-stripe' ),
						$error,
						$message,
						$response_code
					),
					$data
				);
			}

			return $response_body;
		}

		/**
		 * Adds useful WP/WC/WCC information to request bodies.
		 *
		 * @param array $initial_body body of initial request.
		 *
		 * @return array
		 */
		protected function request_body( $initial_body = [] ) {

			$default_body = [
				'settings' => [],
			];

			$body = array_merge( $default_body, $initial_body );

			// Add interesting fields to the body of each request.
			$body['settings'] = wp_parse_args(
				$body['settings'],
				[
					'base_city'      => WC()->countries->get_base_city(),
					'base_country'   => WC()->countries->get_base_country(),
					'base_state'     => WC()->countries->get_base_state(),
					'base_postcode'  => WC()->countries->get_base_postcode(),
					'currency'       => get_woocommerce_currency(),
					'stripe_version' => WC_STRIPE_VERSION,
					'wc_version'     => WC()->version,
					'wp_version'     => get_bloginfo( 'version' ),
				]
			);

			return $body;
		}

		/**
		 * Generates headers for request to the WooCommerce Connect Server.
		 *
		 * @return array|WP_Error
		 */
		protected function request_headers() {

			$headers                    = [];
			$locale                     = strtolower( str_replace( '_', '-', get_locale() ) );
			$locale_elements            = explode( '-', $locale );
			$lang                       = $locale_elements[0];
			$headers['Accept-Language'] = $locale . ',' . $lang;
			$headers['Content-Type']    = 'application/json; charset=utf-8';
			$headers['Accept']          = 'application/vnd.woocommerce-connect.v' . self::WOOCOMMERCE_CONNECT_SERVER_API_VERSION;

			return $headers;
		}
	}
}
