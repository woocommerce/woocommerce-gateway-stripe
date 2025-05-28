<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Stripe_Connect_REST_Oauth_Init_Controller' ) ) {
	/**
	 * Stripe Connect Oauth Init controller class.
	 */
	class WC_Stripe_Connect_REST_Oauth_Init_Controller extends WC_Stripe_Connect_REST_Controller {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'connect/stripe/oauth/init';

		/**
		 * Stripe Connect.
		 *
		 * @var WC_Stripe_Connect
		 */
		protected $connect;

		/**
		 * Constructor.
		 *
		 * @param WC_Stripe_Connect     $connect stripe connect.
		 * @param WC_Stripe_Connect_API $api     stripe connect api.
		 */
		public function __construct( WC_Stripe_Connect $connect, WC_Stripe_Connect_API $api ) {

			parent::__construct( $api );

			$this->connect = $connect;
		}

		/**
		 * Initiate OAuth flow.
		 *
		 * @param array $request POST request.
		 *
		 * @return array|WP_Error
		 */
		public function post( $request ) {

			$data     = $request->get_json_params();
			$response = $this->connect->get_oauth_url( isset( $data['returnUrl'] ) ? $data['returnUrl'] : '' );

			if ( is_wp_error( $response ) ) {

				WC_Stripe_Logger::log( $response, __CLASS__ );

				return new WP_Error(
					$response->get_error_code(),
					$response->get_error_message(),
					[ 'status' => 400 ]
				);
			}

			return [
				'success'  => true,
				'oauthUrl' => $response,
			];
		}
	}
}
