<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Stripe_Connect_REST_Deauthorize_Controller' ) ) {
	/**
	 * Stripe Connect deauthorize controller class.
	 */
	class WC_Stripe_Connect_REST_Deauthorize_Controller extends WC_Stripe_Connect_REST_Controller {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'connect/stripe/account/deauthorize';

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
		 * Deauthorize Stripe Connection.
		 *
		 * @param array $request request.
		 *
		 * @return array|WP_Error
		 */
		public function post( $request ) {

			$response = $connect->deauthorize_account();

			if ( is_wp_error( $response ) ) {

				WC_Stripe_Logger::log( $response, __CLASS__ );

				return new WP_Error(
					$response->get_error_code(),
					$response->get_error_message(),
					array( 'status' => 400 )
				);
			}

			return array(
				'success'    => true,
				'account_id' => $response->accountId, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			);
		}
	}
}
