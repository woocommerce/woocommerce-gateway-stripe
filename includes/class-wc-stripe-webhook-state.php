<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Webhook_State.
 *
 * Tracks the most recent successful and unsuccessful webhooks in test and live modes.
 *
 * @since 5.0.0
 */
class WC_Stripe_Webhook_State {
	const OPTION_LIVE_MONITORING_BEGAN_AT = 'wc_stripe_wh_monitor_began_at';
	const OPTION_LIVE_LAST_SUCCESS_AT     = 'wc_stripe_wh_last_success_at';
	const OPTION_LIVE_LAST_FAILURE_AT     = 'wc_stripe_wh_last_failure_at';
	const OPTION_LIVE_LAST_ERROR          = 'wc_stripe_wh_last_error';

	const OPTION_TEST_MONITORING_BEGAN_AT = 'wc_stripe_wh_test_monitor_began_at';
	const OPTION_TEST_LAST_SUCCESS_AT     = 'wc_stripe_wh_test_last_success_at';
	const OPTION_TEST_LAST_FAILURE_AT     = 'wc_stripe_wh_test_last_failure_at';
	const OPTION_TEST_LAST_ERROR          = 'wc_stripe_wh_test_last_error';

	const VALIDATION_SUCCEEDED                 = 'validation_succeeded';
	const VALIDATION_FAILED_EMPTY_HEADERS      = 'empty_headers';
	const VALIDATION_FAILED_EMPTY_BODY         = 'empty_body';
	const VALIDATION_FAILED_EMPTY_SECRET       = 'empty_secret';
	const VALIDATION_FAILED_USER_AGENT_INVALID = 'user_agent_invalid';
	const VALIDATION_FAILED_SIGNATURE_INVALID  = 'signature_invalid';
	const VALIDATION_FAILED_TIMESTAMP_MISMATCH = 'timestamp_out_of_range';
	const VALIDATION_FAILED_SIGNATURE_MISMATCH = 'signature_mismatch';

	/**
	 * Gets whether Stripe is in test mode or not
	 *
	 * @since 5.0.0
	 * @return bool
	 */
	public static function get_testmode() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		return ( ! empty( $stripe_settings['testmode'] ) && 'yes' === $stripe_settings['testmode'] ) ? true : false;
	}

	/**
	 * Clears the webhook state.
	 *
	 * @param string $mode Optional. The mode to clear the webhook state for. Can be 'all', 'live', or 'test'. Default is 'all'.
	 */
	public static function clear_state( $mode = 'all' ) {
		if ( 'all' === $mode || 'live' === $mode ) {
			delete_option( self::OPTION_LIVE_MONITORING_BEGAN_AT );
			delete_option( self::OPTION_LIVE_LAST_SUCCESS_AT );
			delete_option( self::OPTION_LIVE_LAST_FAILURE_AT );
			delete_option( self::OPTION_LIVE_LAST_ERROR );
		}

		if ( 'all' === $mode || 'test' === $mode ) {
			delete_option( self::OPTION_TEST_MONITORING_BEGAN_AT );
			delete_option( self::OPTION_TEST_LAST_SUCCESS_AT );
			delete_option( self::OPTION_TEST_LAST_FAILURE_AT );
			delete_option( self::OPTION_TEST_LAST_ERROR );
		}
	}

	/**
	 * Gets (and sets, if unset) the timestamp the plugin first
	 * started tracking webhook failure and successes.
	 *
	 * @since 5.0.0
	 * @return integer UTC seconds since 1970.
	 */
	public static function get_monitoring_began_at() {
		$option              = self::get_testmode() ? self::OPTION_TEST_MONITORING_BEGAN_AT : self::OPTION_LIVE_MONITORING_BEGAN_AT;
		$monitoring_began_at = get_option( $option, 0 );
		if ( 0 == $monitoring_began_at ) {
			$monitoring_began_at = time();
			update_option( $option, $monitoring_began_at );

			// Enforce database consistency. This should only be needed if the user
			// has modified the database directly. We should not allow timestamps
			// before monitoring began.
			self::set_last_webhook_success_at( 0 );
			self::set_last_webhook_failure_at( 0 );
			self::set_last_error_reason( self::VALIDATION_SUCCEEDED );
		}
		return $monitoring_began_at;
	}

	/**
	 * Sets the timestamp of the last successfully processed webhook.
	 *
	 * @since 5.0.0
	 * @param integer UTC seconds since 1970.
	 */
	public static function set_last_webhook_success_at( $timestamp ) {
		$option = self::get_testmode() ? self::OPTION_TEST_LAST_SUCCESS_AT : self::OPTION_LIVE_LAST_SUCCESS_AT;
		update_option( $option, $timestamp );
	}

	/**
	 * Gets the timestamp of the last successfully processed webhook,
	 * or returns 0 if no webhook has ever been successfully processed.
	 *
	 * @since 5.0.0
	 * @return integer UTC seconds since 1970 | 0.
	 */
	public static function get_last_webhook_success_at() {
		$option = self::get_testmode() ? self::OPTION_TEST_LAST_SUCCESS_AT : self::OPTION_LIVE_LAST_SUCCESS_AT;
		return get_option( $option, 0 );
	}

	/**
	 * Sets the timestamp of the last failed webhook.
	 *
	 * @since 5.0.0
	 * @param integer UTC seconds since 1970.
	 */
	public static function set_last_webhook_failure_at( $timestamp ) {
		$option = self::get_testmode() ? self::OPTION_TEST_LAST_FAILURE_AT : self::OPTION_LIVE_LAST_FAILURE_AT;
		update_option( $option, $timestamp );
	}

	/**
	 * Gets the timestamp of the last failed webhook,
	 * or returns 0 if no webhook has ever failed to process.
	 *
	 * @since 5.0.0
	 * @return integer UTC seconds since 1970 | 0.
	 */
	public static function get_last_webhook_failure_at() {
		$option = self::get_testmode() ? self::OPTION_TEST_LAST_FAILURE_AT : self::OPTION_LIVE_LAST_FAILURE_AT;
		return get_option( $option, 0 );
	}

	/**
	 * Sets the reason for the last failed webhook.
	 *
	 * @since 5.0.0
	 * @param string Reason code.
	 */
	public static function set_last_error_reason( $reason ) {
		$option = self::get_testmode() ? self::OPTION_TEST_LAST_ERROR : self::OPTION_LIVE_LAST_ERROR;
		update_option( $option, $reason );
	}

	/**
	 * Gets the last webhook failure type.
	 *
	 * @return string|bool Reason the last webhook failed. False if no error.
	 */
	public static function get_last_error_type() {
		$option = self::get_testmode() ? self::OPTION_TEST_LAST_ERROR : self::OPTION_LIVE_LAST_ERROR;
		return get_option( $option, false );
	}

	/**
	 * Returns the localized reason the last webhook failed.
	 *
	 * @since 5.0.0
	 * @return string Reason the last webhook failed.
	 */
	public static function get_last_error_reason() {
		$last_error = self::get_last_error_type();

		if ( self::VALIDATION_SUCCEEDED == $last_error ) {
			return( __( 'No error', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_EMPTY_HEADERS == $last_error ) {
			return( __( 'The webhook was missing expected headers', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_EMPTY_BODY == $last_error ) {
			return( __( 'The webhook was missing expected body', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_EMPTY_SECRET === $last_error ) {
			return( __( 'The webhook secret is not set in the store', 'woocommerce-gateway-stripe' ) );
		}

		// Legacy failure reason. Removed in 8.6.0.
		if ( self::VALIDATION_FAILED_USER_AGENT_INVALID == $last_error ) {
			return( __( 'The webhook received did not come from Stripe', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_SIGNATURE_INVALID == $last_error ) {
			return( __( 'The webhook signature was missing or was incorrectly formatted', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_TIMESTAMP_MISMATCH == $last_error ) {
			return( __( 'The timestamp in the webhook differed more than five minutes from the site time', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_SIGNATURE_MISMATCH == $last_error ) {
			return( __( 'The webhook was not signed with the expected signing secret', 'woocommerce-gateway-stripe' ) );
		}

		return( __( 'Unknown error.', 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Gets the status code for the webhook processing.
	 *
	 * @since 8.6.0
	 * @return int The status code for the webhook processing.
	 */
	public static function get_webhook_status_code() {
		$last_success_at     = self::get_last_webhook_success_at();
		$last_failure_at     = self::get_last_webhook_failure_at();

		// Case 1 (Nominal case): Most recent = success
		if ( $last_success_at > $last_failure_at ) {
			return 1;
		}

		// Case 2: No webhooks received yet
		if ( ( 0 == $last_success_at ) && ( 0 == $last_failure_at ) ) {
			return 2;
		}

		if ( $last_success_at > 0 ) {
			// Case 5: Signature mismatch with a valid webhook.
			if ( self::VALIDATION_FAILED_SIGNATURE_MISMATCH === self::get_last_error_type() && self::has_valid_webhook( self::get_testmode() ) ) {
				return 5;
			}

			// Case 3: Failure after success
			return 3;
		}

		// Case 4: Failure with no prior success
		return 4;
	}

	/**
	 * Gets the state of webhook processing in a human readable format.
	 *
	 * @since 5.0.0
	 * @return string Details on recent webhook successes and failures.
	 */
	public static function get_webhook_status_message() {
		$monitoring_began_at = self::get_monitoring_began_at();
		$last_success_at     = self::get_last_webhook_success_at();
		$last_failure_at     = self::get_last_webhook_failure_at();
		$last_error          = self::get_last_error_reason();
		$test_mode           = self::get_testmode();
		$code                = self::get_webhook_status_code();

		$date_format = 'Y-m-d H:i:s e';

		switch ( $code ) {
			case 1: // Case 1 (Nominal case): Most recent = success
				return sprintf(
					$test_mode ?
						/* translators: 1) date and time of last webhook received, e.g. 2020-06-28 10:30:50 UTC */
						__( 'The most recent test webhook, timestamped %s, was processed successfully.', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time of last webhook received, e.g. 2020-06-28 10:30:50 UTC */
						__( 'The most recent live webhook, timestamped %s, was processed successfully.', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_success_at )
				);
			case 2: // Case 2: No webhooks received yet
				return sprintf(
					$test_mode ?
						/* translators: 1) date and time webhook monitoring began, e.g. 2020-06-28 10:30:50 UTC */
						__( 'No test webhooks have been received since monitoring began at %s.', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time webhook monitoring began, e.g. 2020-06-28 10:30:50 UTC */
						__( 'No live webhooks have been received since monitoring began at %s.', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $monitoring_began_at )
				);
			case 3: // Case 3: Failure after success
				return sprintf(
					$test_mode ?
						/*
						 * translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time of last successful webhook e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The latest test webhook received at %1$s, could not be processed. Reason: %2$s. (The last test webhook to process successfully was timestamped %3$s.)', 'woocommerce-gateway-stripe' ) :
						/*
						 * translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time of last successful webhook e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The latest live webhook received at %1$s, could not be processed. Reason: %2$s. Your current webhook configuration is correct -- you may have duplicate webhooks set up. The last live webhook to process successfully was timestamped %3$s.)', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_failure_at ),
					$last_error,
					gmdate( $date_format, $last_success_at )
				);
			case 5: // Case 5: Failure with a valid webhook.
				return sprintf(
					$test_mode ?
						/* translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
							* translators: 2) reason webhook failed
							* translators: 3) date and time webhook monitoring began e.g. 2020-05-28 10:30:50 UTC
							* translators: 4) opening anchor tag
							* translators: 5) closing anchor tag
							*/
						__( 'A test webhook received at %1$s could not be processed. Reason: %2$s. Your current webhook configuration is correct, so you can safely ignore this message, and Stripe will eventually stop sending the duplicate webhook. If you prefer, you can reconfigure your webhooks to remove any duplicates.', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
							* translators: 2) reason webhook failed
							* translators: 3) date and time webhook monitoring began e.g. 2020-05-28 10:30:50 UTC
							*/
						__( 'A webhook received at %1$s could not be processed. Reason: %2$s. Your current webhook configuration is correct, so you can safely ignore this message, and Stripe will eventually stop sending the duplicate webhook. If you prefer, you can reconfigure your webhooks to remove any duplicates.', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_failure_at ),
					$last_error,
					gmdate( $date_format, $monitoring_began_at )
				);
			default: // Case 4: Failure with no prior success
				return sprintf(
					$test_mode ?
						/* translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time webhook monitoring began e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The latest test webhook received at %1$s, could not be processed. Reason: %2$s. (No test webhooks have been processed successfully since monitoring began at %3$s.)', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time webhook monitoring began e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The latest live webhook received at %1$s, could not be processed. Reason: %2$s. (No live webhooks have been processed successfully since monitoring began at %3$s.)', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_failure_at ),
					$last_error,
					gmdate( $date_format, $monitoring_began_at )
				);
		}
	}

	/**
	 * Fetches the configured webhook URLs.
	 *
	 * @return array URLs for live and test mode webhooks.
	 */
	public static function get_configured_webhook_urls() {
		$live_webhook = WC_Stripe_Helper::get_settings( null, 'webhook_data' );
		$test_webhook = WC_Stripe_Helper::get_settings( null, 'test_webhook_data' );

		return [
			'live' => empty( $live_webhook['url'] ) ? null : rawurlencode( $live_webhook['url'] ),
			'test' => empty( $test_webhook['url'] ) ? null : rawurlencode( $test_webhook['url'] ),
		];
	}

	/**
	 * Checks if the currently configured webhook is valid.
	 *
	 * @param bool $test_mode Whether to check the test webhook or the live webhook.
	 * @return bool
	 */
	public static function has_valid_webhook( $test_mode ) {
		$webhook_secret = WC_Stripe_Helper::get_settings( null, $test_mode ? 'test_webhook_secret' : 'webhook_secret' );

		if ( empty( $webhook_secret ) ) {
			return false;
		}

		// Get the configured webhook URLs.
		$webhook_data = WC_Stripe_Helper::get_settings( null, $test_mode ? 'test_webhook_data' : 'webhook_data' );

		// If the webhook ID is not set, we're unable to validate it. This should never happen - unless the settings have been tampered with.
		if ( empty( $webhook_data['id'] ) ) {
			return false;
		}

		// If the webhook URL is not set, or doesn't match the current site return false.
		if ( empty( $webhook_data['url'] ) || ! WC_Stripe_Helper::is_webhook_url( $webhook_data['url'] ) ) {
			return false;
		}

		// Cache the webhook response to avoid multiple requests.
		// Cache it against the webhook ID and secret key to invalidate the cache if the account is changed.
		// We cannot use the account ID here because that causes an infinite loop.
		$secret_key    = WC_Stripe_Helper::get_settings( null, $test_mode ? 'test_secret_key' : 'secret_key' );
		$transient_key = 'wc-stripe-webhook-cache-' . md5( $webhook_data['id'] . $secret_key );
		$webhook       = get_transient( $transient_key );

		if ( false === $webhook ) {
			$webhook = WC_Stripe_API::request(
				[],
				"webhook_endpoints/{$webhook_data['id']}",
				'GET'
			);

			set_transient( $transient_key, $webhook, DAY_IN_SECONDS );
		}

		// Finally check the at the webhook is valid.
		if ( ! isset( $webhook->url ) || ! WC_Stripe_Helper::is_webhook_url( $webhook->url ) ) {
			return false;
		}

		return true;
	}
};
