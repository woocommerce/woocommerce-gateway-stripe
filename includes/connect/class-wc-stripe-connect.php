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

			add_action( 'admin_init', [ $this, 'maybe_handle_redirect' ] );
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
				$return_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' );
			}

			if ( substr( $return_url, 0, 8 ) !== 'https://' ) {
				return new WP_Error( 'invalid_url_protocol', __( 'Your site must be served over HTTPS in order to connect your Stripe account automatically.', 'woocommerce-gateway-stripe' ) );
			}

			$return_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wcs_stripe_connected' ), $return_url );

			$result = $this->api->get_stripe_oauth_init( $return_url );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			set_transient( 'wcs_stripe_connect_state', $result->state, 6 * HOUR_IN_SECONDS );

			return $result->oauthUrl; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		/**
		 * Initiate OAuth connection request to Connect Server
		 *
		 * @param  string $state State token to prevent request forgery.
		 * @param  string $code  OAuth code.
		 *
		 * @return string|WP_Error
		 */
		public function connect_oauth( $state, $code ) {
			// The state parameter is used to protect against CSRF.
			// It's a unique, randomly generated, opaque, and non-guessable string that is sent when starting the
			// authentication request and validated when processing the response.
			if ( get_transient( 'wcs_stripe_connect_state' ) !== $state ) {
				return new WP_Error( 'Invalid state received from Stripe server' );
			}

			$response = $this->api->get_stripe_oauth_keys( $code );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			delete_transient( 'wcs_stripe_connect_state' );

			return $this->save_stripe_keys( $response );
		}

		/**
		 * Handle redirect back from oauth-init or credentials reset
		 */
		public function maybe_handle_redirect() {
			if ( ! is_admin() ) {
				return;
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			// redirect from oauth-init
			if ( isset( $_GET['wcs_stripe_code'], $_GET['wcs_stripe_state'] ) ) {
				$nonce = isset( $_GET['_wpnonce'] ) ? wc_clean( wp_unslash( $_GET['_wpnonce'] ) ) : '';

				if ( ! wp_verify_nonce( $nonce, 'wcs_stripe_connected' ) ) {
					return new WP_Error( 'Invalid nonce received from Stripe server' );
				}

				$response = $this->connect_oauth( wc_clean( wp_unslash( $_GET['wcs_stripe_state'] ) ), wc_clean( wp_unslash( $_GET['wcs_stripe_code'] ) ) );

				$this->record_account_connect_track_event( is_wp_error( $response ) );

				wp_safe_redirect( esc_url_raw( remove_query_arg( [ 'wcs_stripe_state', 'wcs_stripe_code' ] ) ) );
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

			$is_test                                    = false !== strpos( $result->publishableKey, '_test_' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$prefix                                     = $is_test ? 'test_' : '';
			$default_options                            = $this->get_default_stripe_config();
			$options                                    = array_merge( $default_options, get_option( self::SETTINGS_OPTION, [] ) );
			$options['enabled']                         = 'yes';
			$options['testmode']                        = $is_test ? 'yes' : 'no';
			$options['upe_checkout_experience_enabled'] = $this->get_upe_checkout_experience_enabled();
			$options[ $prefix . 'publishable_key' ]     = $result->publishableKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$options[ $prefix . 'secret_key' ]          = $result->secretKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// While we are at it, let's also clear the account_id and
			// test_account_id if present.
			unset( $options['account_id'] );
			unset( $options['test_account_id'] );

			update_option( self::SETTINGS_OPTION, $options );

			return $result;
		}

		/**
		 * If user is reconnecting and there are existing settings data, return the value from the settings.
		 * Otherwise for new connections return 'yes' for `upe_checkout_experience_enabled` field.
		 */
		private function get_upe_checkout_experience_enabled() {
			$existing_stripe_settings = get_option( self::SETTINGS_OPTION, [] );

			if ( isset( $existing_stripe_settings['upe_checkout_experience_enabled'] ) ) {
				return $existing_stripe_settings['upe_checkout_experience_enabled'];
			}

			return 'yes';
		}

		/**
		 * Clears keys for test or production (whichever is presently enabled).
		 */
		private function clear_stripe_keys() {

			$options = get_option( self::SETTINGS_OPTION, [] );

			if ( 'yes' === $options['testmode'] ) {
				$options['test_publishable_key'] = '';
				$options['test_secret_key']      = '';
				// clear test_account_id if present
				unset( $options['test_account_id'] );
			} else {
				$options['publishable_key'] = '';
				$options['secret_key']      = '';
				// clear account_id if present
				unset( $options['account_id'] );
			}

			update_option( self::SETTINGS_OPTION, $options );

		}

		/**
		 * Gets default Stripe settings
		 */
		private function get_default_stripe_config() {

			$result  = [];
			$gateway = new WC_Gateway_Stripe();
			foreach ( $gateway->form_fields as $key => $value ) {
				if ( isset( $value['default'] ) ) {
					$result[ $key ] = $value['default'];
				}
			}

			$result['upe_checkout_experience_enabled'] = 'yes';
			$result['upe_checkout_experience_accepted_payments'][] = 'link';

			return $result;
		}

		public function is_connected() {

			$options = get_option( self::SETTINGS_OPTION, [] );

			if ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) {
				return isset( $options['test_publishable_key'], $options['test_secret_key'] ) && trim( $options['test_publishable_key'] ) && trim( $options['test_secret_key'] );
			} else {
				return isset( $options['publishable_key'], $options['secret_key'] ) && trim( $options['publishable_key'] ) && trim( $options['secret_key'] );
			}
		}

		/**
		 * Records a track event after the user is redirected back to the store from the Stripe UX.
		 *
		 * @param bool $had_error Whether the Stripe connection had an error.
		 */
		private function record_account_connect_track_event( bool $had_error ) {
			if ( ! class_exists( 'WC_Tracks' ) ) {
				return;
			}

			$options    = get_option( self::SETTINGS_OPTION, [] );
			$is_test    = isset( $options['testmode'] ) && 'yes' === $options['testmode'];
			$event_name = ! $had_error ? 'wcstripe_stripe_connected' : 'wcstripe_stripe_connect_error';

			// We're recording this directly instead of queueing it because
			// a queue wouldn't be processed due to the redirect that comes after.
			WC_Tracks::record_event( $event_name, [ 'is_test_mode' => $is_test ] );
		}
	}
}
