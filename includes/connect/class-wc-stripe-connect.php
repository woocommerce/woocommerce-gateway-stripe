<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Stripe_Connect' ) ) {
	/**
	 * Stripe Connect class.
	 */
	class WC_Stripe_Connect {

		const SETTINGS_OPTION = 'woocommerce_stripe_settings';

		/**
		 * Stripe connect api.
		 *
		 * @var object $api
		 */
		private $api;

		/**
		 * Constructor.
		 *
		 * @param WC_Stripe_Connect_API $api stripe connect api.
		 */
		public function __construct( WC_Stripe_Connect_API $api ) {

			$this->api = $api;
		}

		/**
		 * Gets the OAuth URL for Stripe onboarding flow
		 *
		 * @param  string $return_url url to return to after oauth flow.
		 *
		 * @return string|WP_Error
		 */
		public function get_oauth_url( $return_url = '' ) {

			if ( empty( $return_url ) ) {
				$return_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' );
			}

			if ( substr( $return_url, 0, 8 ) !== 'https://' ) {
				return new WP_Error( 'invalid_url_protocol', __( 'Your site must be served over HTTPS in order to connect your Stripe account automatically.', 'woocommerce-gateway-stripe' ) );
			}

			$result = $this->api->get_stripe_oauth_init( $return_url );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			update_option( 'stripe_state', $result->state );

			return $result->oauthUrl; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		/**
		 * Deauthorize existing Stripe account
		 *
		 * @return array|WP_Error
		 */
		public function deauthorize_account() {

			$response = $this->api->deauthorize_stripe_account();

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$this->clear_stripe_keys();

			return $response;
		}

		/**
		 * Initiate OAuth connection request to Connect Server
		 *
		 * @param  bool $state Stripe onboarding state.
		 * @param  int  $code  OAuth code.
		 *
		 * @return string|WP_Error
		 */
		public function connect_oauth( $state, $code ) {

			if ( get_option( 'stripe_state', false ) !== $state ) {
				return new WP_Error( 'Invalid stripe state' );
			}

			$response = $this->api->get_stripe_oauth_keys( $code );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			delete_option( 'stripe_state' );

			return $this->save_stripe_keys( $response );
		}

		/**
		 * Handle redirect back from oauth-init
		 */
		public function maybe_connect_oauth() {

			if ( isset( $_GET['wcs_stripe_code'], $_GET['wcs_stripe_state'] ) ) {
				$response = $this->connect_oauth( $_GET['wcs_stripe_state'], $_GET['wcs_stripe_code'] );

				wp_safe_redirect( remove_query_arg( array( 'wcs_stripe_state', 'wcs_stripe_code' ) ) );
				exit;
			}
		}


		/**
		 * Saves stripe keys after OAuth response
		 *
		 * @param  array $result OAuth response result.
		 *
		 * @return array|WP_Error
		 */
		private function save_stripe_keys( $result ) {

			if ( ! isset( $result->publishableKey, $result->secretKey ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return new WP_Error( 'Invalid credentials received from WooCommerce Connect server' );
			}

			$is_test         = false !== strpos( $result->publishableKey, '_test_' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$prefix          = $is_test ? 'test_' : '';
			$default_options = array();

			$options                                = array_merge( $default_options, get_option( self::SETTINGS_OPTION, array() ) );
			$options['enabled']                     = 'yes';
			$options['testmode']                    = $is_test ? 'yes' : 'no';
			$options[ $prefix . 'publishable_key' ] = $result->publishableKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$options[ $prefix . 'secret_key' ]      = $result->secretKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// While we are at it, let's also clear the account_id and
			// test_account_id if present.
			unset( $options['account_id'] );
			unset( $options['test_account_id'] );

			update_option( self::SETTINGS_OPTION, $options );

			return $result;
		}

		/**
		 * Clears keys for test or production (whichever is presently enabled).
		 */
		private function clear_stripe_keys() {

			$default_options = $this->get_default_config();
			$options         = array_merge( $default_options, get_option( self::SETTINGS_OPTION, array() ) );

			if ( 'yes' === $options['testmode'] ) {
				$options['test_publishable_key'] = '';
				$options['test_secret_key']      = '';
			} else {
				$options['publishable_key'] = '';
				$options['secret_key']      = '';
			}

			// While we are at it, let's also clear the account_id and
			// test_account_id if present.
			unset( $options['account_id'] );
			unset( $options['test_account_id'] );

			update_option( self::SETTINGS_OPTION, $options );

		}
	}
}
