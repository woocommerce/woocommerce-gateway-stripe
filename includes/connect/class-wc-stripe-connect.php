<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Stripe_Connect' ) ) {
	/**
	 * Stripe Connect class.
	 */
	class WC_Stripe_Connect {

		/**
		 * The option name for the Stripe gateway settings.
		 *
		 * @deprecated 8.7.0
		 */
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

			// refresh the connection, triggered by Action Scheduler
			add_action( 'wc_stripe_refresh_connection', [ $this, 'refresh_connection' ] );

			add_action( 'admin_init', [ $this, 'maybe_handle_redirect' ] );
		}

		/**
		 * Gets the OAuth URL for Stripe onboarding flow
		 *
		 * @param string $return_url The URL to return to after OAuth flow.
		 * @param string $mode       Optional. The mode to connect to. 'live' or 'test'. Default is 'live'.
		 *
		 * @return string|WP_Error
		 */
		public function get_oauth_url( $return_url = '', $mode = 'live' ) {

			if ( empty( $return_url ) ) {
				$return_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' );
			}

			if ( 'test' !== $mode && substr( $return_url, 0, 8 ) !== 'https://' ) {
				return new WP_Error( 'invalid_url_protocol', __( 'Your site must be served over HTTPS in order to connect your Stripe account automatically.', 'woocommerce-gateway-stripe' ) );
			}

			$return_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wcs_stripe_connected' ), $return_url );

			$result = $this->api->get_stripe_oauth_init( $return_url, $mode );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			set_transient( 'wcs_stripe_connect_state_' . $mode, $result->state, 6 * HOUR_IN_SECONDS );

			return $result->oauthUrl; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		/**
		 * Initiate OAuth connection request to Connect Server
		 *
		 * @param string $state State token to prevent request forgery.
		 * @param string $code  OAuth code.
		 * @param string $type  Optional. The type of the connection. 'connect' or 'app'. Default is 'connect'.
		 * @param string $mode  Optional. The mode to connect to. 'live' or 'test'. Default is 'live'.
		 *
		 * @return string|WP_Error
		 */
		public function connect_oauth( $state, $code, $type = 'connect', $mode = 'live' ) {
			// The state parameter is used to protect against CSRF.
			// It's a unique, randomly generated, opaque, and non-guessable string that is sent when starting the
			// authentication request and validated when processing the response.
			if ( get_transient( 'wcs_stripe_connect_state_' . $mode ) !== $state ) {
				return new WP_Error( 'Invalid state received from Stripe server' );
			}

			$response = $this->api->get_stripe_oauth_keys( $code, $type, $mode );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			delete_transient( 'wcs_stripe_connect_state_' . $mode );

			return $this->save_stripe_keys( $response, $type, $mode );
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

				$state = wc_clean( wp_unslash( $_GET['wcs_stripe_state'] ) );
				$code  = wc_clean( wp_unslash( $_GET['wcs_stripe_code'] ) );
				$type  = isset( $_GET['wcs_stripe_type'] ) ? wc_clean( wp_unslash( $_GET['wcs_stripe_type'] ) ) : 'connect';
				$mode  = isset( $_GET['wcs_stripe_mode'] ) ? wc_clean( wp_unslash( $_GET['wcs_stripe_mode'] ) ) : 'live';

				$response = $this->connect_oauth( $state, $code, $type, $mode );

				$this->record_account_connect_track_event( is_wp_error( $response ) );

				$redirect_url = remove_query_arg( [ 'wcs_stripe_state', 'wcs_stripe_code', 'wcs_stripe_type', 'wcs_stripe_mode' ] );
				if ( ! is_wp_error( $response ) ) {
					$redirect_url = add_query_arg( [ 'wc_stripe_connected' => 'true' ], $redirect_url );
				}
				wp_safe_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		}

		/**
		 * Saves Stripe keys after OAuth response
		 *
		 * @param stdObject $result OAuth's response result.
		 * @param string    $type   Optional. The type of the connection. 'connect' or 'app'. Default is 'connect'.
		 * @param string    $mode   Optional. The mode to connect to. 'live' or 'test'. Default is 'live'.
		 *
		 * @return stdObject|WP_Error OAuth's response result or WP_Error.
		 */
		private function save_stripe_keys( $result, $type = 'connect', $mode = 'live' ) {
			if ( ! isset( $result->publishableKey, $result->secretKey ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return new WP_Error( 'Invalid credentials received from WooCommerce Connect server' );
			}

			if ( 'app' === $type && ! isset( $result->refreshToken ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return new WP_Error( 'Invalid credentials received from WooCommerce Connect server' );
			}

			$publishable_key                            = $result->publishableKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$secret_key                                 = $result->secretKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$is_test                                    = 'live' !== $mode;
			$prefix                                     = $is_test ? 'test_' : '';
			$default_options                            = $this->get_default_stripe_config();
			$current_options                            = WC_Stripe_Helper::get_stripe_settings();
			$options                                    = array_merge( $default_options, is_array( $current_options ) ? $current_options : [] );
			$options['enabled']                         = 'yes';
			$options['testmode']                        = $is_test ? 'yes' : 'no';
			$options['upe_checkout_experience_enabled'] = $this->get_upe_checkout_experience_enabled();
			$options[ $prefix . 'publishable_key' ]     = $publishable_key;
			$options[ $prefix . 'secret_key' ]          = $secret_key;
			$options[ $prefix . 'connection_type' ]     = $type;
			$options['pmc_enabled']                     = 'connect' === $type ? 'yes' : 'no'; // When not connected via Connect OAuth, the PMC is disabled.
			if ( 'app' === $type ) {
				$options[ $prefix . 'refresh_token' ] = $result->refreshToken; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}

			// While we are at it, let's also clear the account_id and
			// test_account_id if present.
			unset( $options['account_id'] );
			unset( $options['test_account_id'] );

			// Enable ECE for new connections.
			$this->enable_ece_in_new_accounts();

			WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
			WC_Stripe_Helper::update_main_stripe_settings( $options );

			// Similar to what we do for webhooks, we save some stats to help debug oauth problems.
			update_option( 'wc_stripe_' . $prefix . 'oauth_updated_at', time() );
			update_option( 'wc_stripe_' . $prefix . 'oauth_failed_attempts', 0 );
			update_option( 'wc_stripe_' . $prefix . 'oauth_last_failed_at', '' );

			if ( 'app' === $type ) {
				// Stripe App OAuth access_tokens expire after 1 hour:
				// https://docs.stripe.com/stripe-apps/api-authentication/oauth#refresh-access-token
				$this->schedule_connection_refresh();
			} else {
				// Make sure that all refresh actions are cancelled if they haven't connected via the app.
				$this->unschedule_connection_refresh();
			}

			try {
				// Automatically configure webhooks for the account now that we have the keys.
				WC_Stripe::get_instance()->account->configure_webhooks( $is_test ? 'test' : 'live', $secret_key );
			} catch ( Exception $e ) {
				return new WP_Error( 'wc_stripe_webhook_error', $e->getMessage() );
			}

			// For new installs the legacy gateway gets instantiated because there is no settings in the DB yet,
			// so we need to instantiate the UPE gateway just for the PMC migration.
			$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
			if ( ! $gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
				$gateway = new WC_Stripe_UPE_Payment_Gateway();
			}
			// If pmc_enabled is not set (aka new install) or is not 'yes' (aka migration already done) we need to migrate the payment methods from the DB option to Stripe PMC API.
			if ( empty( $current_options ) || ! isset( $current_options['pmc_enabled'] ) || 'yes' !== $current_options['pmc_enabled'] ) {
				WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc( true );
			}

			return $result;
		}

		/**
		 * If user is reconnecting and there are existing settings data, return the value from the settings.
		 * Otherwise for new connections return 'yes' for `upe_checkout_experience_enabled` field.
		 */
		private function get_upe_checkout_experience_enabled() {
			$existing_stripe_settings = WC_Stripe_Helper::get_stripe_settings();

			if ( isset( $existing_stripe_settings['upe_checkout_experience_enabled'] ) ) {
				return $existing_stripe_settings['upe_checkout_experience_enabled'];
			}

			return 'yes';
		}

		/**
		 * Enable Stripe express checkout element for new connections.
		 */
		private function enable_ece_in_new_accounts() {
			$existing_stripe_settings = WC_Stripe_Helper::get_stripe_settings();

			if ( empty( $existing_stripe_settings ) ) {
				update_option( WC_Stripe_Feature_Flags::ECE_FEATURE_FLAG_NAME, 'yes' );
			}
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

			$result['upe_checkout_experience_enabled']             = 'yes';
			$result['upe_checkout_experience_accepted_payments'][] = WC_Stripe_Payment_Methods::LINK;

			return $result;
		}

		/**
		 * Determines if the store is connected to Stripe.
		 *
		 * @param string $mode Optional. The mode to check. 'live' or 'test' - if not provided, the currently enabled mode will be checked.
		 * @return bool True if connected, false otherwise.
		 */
		public function is_connected( $mode = null ) {
			// If the mode is not provided, we'll check the current mode.
			if ( is_null( $mode ) ) {
				$mode = WC_Stripe_Mode::is_test() ? 'test' : 'live';
			}

			$options = WC_Stripe_Helper::get_stripe_settings();
			if ( 'test' === $mode ) {
				return isset( $options['test_publishable_key'], $options['test_secret_key'] ) && trim( $options['test_publishable_key'] ) && trim( $options['test_secret_key'] );
			} else {
				return isset( $options['publishable_key'], $options['secret_key'] ) && trim( $options['publishable_key'] ) && trim( $options['secret_key'] );
			}
		}

		/**
		 * Determines if the store is connected to Stripe via OAuth.
		 *
		 * @param string $mode Optional. The mode to check. 'live' or 'test' (default: 'live').
		 * @return bool True if connected via OAuth, false otherwise.
		 */
		public function is_connected_via_oauth( $mode = 'live' ) {
			if ( ! $this->is_connected( $mode ) ) {
				return false;
			}

			return in_array( $this->get_connection_type( $mode ), [ 'connect', 'app' ], true );
		}

		/**
		 * Determines if the store is using a Stripe App OAuth connection.
		 *
		 * @since 8.6.0
		 *
		 * @param string $mode Optional. The mode to check. 'live' | 'test' | null (default: null).
		 * @return bool True if connected via Stripe App OAuth, false otherwise.
		 */
		public function is_connected_via_app_oauth( $mode = null ) {
			// If the mode is not provided, we'll check the current mode.
			if ( is_null( $mode ) ) {
				$mode = WC_Stripe_Mode::is_test() ? 'test' : 'live';
			}

			return 'app' === $this->get_connection_type( $mode );
		}

		/**
		 * Fetches the connection type for the account.
		 *
		 * @param string $mode The account mode. 'live' or 'test'.
		 * @return string The connection type. 'connect', 'app', or ''.
		 */
		public function get_connection_type( $mode ) {
			$options = WC_Stripe_Helper::get_stripe_settings();
			$key     = 'test' === $mode ? 'test_connection_type' : 'connection_type';

			return isset( $options[ $key ] ) ? $options[ $key ] : '';
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

			$event_name = ! $had_error ? 'wcstripe_stripe_connected' : 'wcstripe_stripe_connect_error';

			// We're recording this directly instead of queueing it because
			// a queue wouldn't be processed due to the redirect that comes after.
			WC_Tracks::record_event( $event_name, [ 'is_test_mode' => WC_Stripe_Mode::is_test() ] );
		}

		/**
		 * Schedules the App OAuth connection refresh.
		 *
		 * @since 8.6.0
		 */
		private function schedule_connection_refresh() {
			if ( ! $this->is_connected_via_app_oauth() ) {
				return;
			}

			/**
			 * Filters the frequency with which the App OAuth connection should be refreshed.
			 * Access tokens expire in 1 hour, and there seem to be no way to customize that from the Stripe Dashboard:
			 * https://docs.stripe.com/stripe-apps/api-authentication/oauth#refresh-access-token
			 * We schedule the connection refresh every 55 minutues.
			 *
			 * @param int $interval refresh interval
			 *
			 * @since 8.6.0
			 */
			$interval = apply_filters( 'wc_stripe_connection_refresh_interval', HOUR_IN_SECONDS - 5 * MINUTE_IN_SECONDS );

			// Make sure that all refresh actions are cancelled before scheduling it.
			$this->unschedule_connection_refresh();

			as_schedule_single_action( time() + $interval, 'wc_stripe_refresh_connection', [], WC_Stripe_Action_Scheduler_Service::GROUP_ID, false, 0 );
		}

		/**
		 * Unschedules the App OAuth connection refresh.
		 *
		 * @since 8.6.0
		 */
		protected function unschedule_connection_refresh() {
			as_unschedule_all_actions( 'wc_stripe_refresh_connection', [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );
		}

		/**
		 * Refreshes the App OAuth access_token via the Woo Connect Server.
		 *
		 * @since 8.6.0
		 */
		public function refresh_connection() {
			if ( ! $this->is_connected_via_app_oauth() ) {
				return;
			}

			$options       = WC_Stripe_Helper::get_stripe_settings();
			$mode          = WC_Stripe_Mode::is_test() ? 'test' : 'live';
			$prefix        = 'test' === $mode ? 'test_' : '';
			$refresh_token = $options[ $prefix . 'refresh_token' ];

			$retries = get_option( 'wc_stripe_' . $prefix . 'oauth_failed_attempts', 0 ) + 1;

			$response = $this->api->refresh_stripe_app_oauth_keys( $refresh_token, $mode );
			if ( ! is_wp_error( $response ) ) {
				$response = $this->save_stripe_keys( $response, 'app', $mode );
			}

			if ( is_wp_error( $response ) ) {
				update_option( 'wc_stripe_' . $prefix . 'oauth_failed_attempts', $retries );
				update_option( 'wc_stripe_' . $prefix . 'oauth_last_failed_at', time() );

				WC_Stripe_Logger::log( 'OAuth connection refresh failed: ' . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				// If after 10 attempts we are unable to refresh the connection keys, we don't re-schedule anymore,
				// in this case an error message is show in the account status indicating that the API keys are not
				// valid and that a reconnection is necessary.
				if ( $retries < 10 ) {
					// Re-schedule the connection refresh
					$this->schedule_connection_refresh();
				}
			}

			// save_stripe_keys() schedules a connection_refresh after saving the keys,
			// we don't need to do it explicitly here.
		}
	}
}
