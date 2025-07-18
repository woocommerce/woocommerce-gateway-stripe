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
	const OPTION_LIVE_PENDING_WEBHOOKS    = 'wc_stripe_wh_live_pending_webhooks';

	const OPTION_TEST_MONITORING_BEGAN_AT = 'wc_stripe_wh_test_monitor_began_at';
	const OPTION_TEST_LAST_SUCCESS_AT     = 'wc_stripe_wh_test_last_success_at';
	const OPTION_TEST_LAST_FAILURE_AT     = 'wc_stripe_wh_test_last_failure_at';
	const OPTION_TEST_LAST_ERROR          = 'wc_stripe_wh_test_last_error';
	const OPTION_TEST_PENDING_WEBHOOKS    = 'wc_stripe_wh_test_pending_webhooks';

	const VALIDATION_SUCCEEDED                 = 'validation_succeeded';
	const VALIDATION_FAILED_EMPTY_HEADERS      = 'empty_headers';
	const VALIDATION_FAILED_EMPTY_BODY         = 'empty_body';
	const VALIDATION_FAILED_EMPTY_SECRET       = 'empty_secret';
	const VALIDATION_FAILED_USER_AGENT_INVALID = 'user_agent_invalid';
	const VALIDATION_FAILED_SIGNATURE_INVALID  = 'signature_invalid';
	const VALIDATION_FAILED_DUPLICATE_WEBHOOKS = 'duplicate_webhooks';
	const VALIDATION_FAILED_TIMESTAMP_MISMATCH = 'timestamp_out_of_range';
	const VALIDATION_FAILED_SIGNATURE_MISMATCH = 'signature_mismatch';

	/**
	 * Gets whether Stripe is in test mode or not
	 *
	 * @since 5.0.0
	 * @return bool
	 *
	 * @deprecated 8.9.0
	 */
	public static function get_testmode() {
		wc_deprecated_function( __METHOD__, '8.9.0', 'WC_Stripe_Mode::is_test()' );

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
			delete_option( self::OPTION_LIVE_PENDING_WEBHOOKS );
		}

		if ( 'all' === $mode || 'test' === $mode ) {
			delete_option( self::OPTION_TEST_MONITORING_BEGAN_AT );
			delete_option( self::OPTION_TEST_LAST_SUCCESS_AT );
			delete_option( self::OPTION_TEST_LAST_FAILURE_AT );
			delete_option( self::OPTION_TEST_LAST_ERROR );
			delete_option( self::OPTION_TEST_PENDING_WEBHOOKS );
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
		$option              = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_MONITORING_BEGAN_AT : self::OPTION_LIVE_MONITORING_BEGAN_AT;
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
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_LAST_SUCCESS_AT : self::OPTION_LIVE_LAST_SUCCESS_AT;
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
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_LAST_SUCCESS_AT : self::OPTION_LIVE_LAST_SUCCESS_AT;
		return get_option( $option, 0 );
	}

	/**
	 * Sets the timestamp of the last failed webhook.
	 *
	 * @since 5.0.0
	 * @param integer UTC seconds since 1970.
	 */
	public static function set_last_webhook_failure_at( $timestamp ) {
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_LAST_FAILURE_AT : self::OPTION_LIVE_LAST_FAILURE_AT;
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
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_LAST_FAILURE_AT : self::OPTION_LIVE_LAST_FAILURE_AT;
		return get_option( $option, 0 );
	}

	/**
	 * Sets the reason for the last failed webhook.
	 *
	 * @since 5.0.0
	 * @param string Reason code.
	 */
	public static function set_last_error_reason( $reason ) {
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_LAST_ERROR : self::OPTION_LIVE_LAST_ERROR;
		update_option( $option, $reason );
	}

	/**
	 * Returns the localized reason the last webhook failed.
	 *
	 * @since 5.0.0
	 * @return string Reason the last webhook failed.
	 */
	public static function get_last_error_reason() {
		$option     = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_LAST_ERROR : self::OPTION_LIVE_LAST_ERROR;
		$last_error = get_option( $option, false );

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
			return( __( 'The webhook secret is not set in the store. Please configure the webhooks', 'woocommerce-gateway-stripe' ) );
		}

		// Legacy failure reason. Removed in 8.6.0.
		if ( self::VALIDATION_FAILED_USER_AGENT_INVALID == $last_error ) {
			return( __( 'The webhook received did not come from Stripe', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_SIGNATURE_INVALID == $last_error ) {
			return( __( 'The webhook signature was missing or was incorrectly formatted', 'woocommerce-gateway-stripe' ) );
		}

		if ( self::VALIDATION_FAILED_DUPLICATE_WEBHOOKS == $last_error ) {
			return( __( 'Multiple webhooks exist for this site. Please remove the duplicate webhooks or re-configure the webhooks', 'woocommerce-gateway-stripe' ) );
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
		$last_success_at = self::get_last_webhook_success_at();
		$last_failure_at = self::get_last_webhook_failure_at();

		// Case 1 (Nominal case): Most recent = success
		if ( $last_success_at > $last_failure_at ) {
			return 1;
		}

		// Case 2: No webhooks received yet
		if ( ( 0 == $last_success_at ) && ( 0 == $last_failure_at ) ) {
			return 2;
		}

		// Case 3: Failure after success
		if ( $last_success_at > 0 ) {
			return 3;
		}

		// Case 4: Failure with no prior success
		return 4;
	}

	/**
	 * Sets the number of pending webhooks.
	 *
	 * @since 9.7.0
	 *
	 * @param int $pending_webhooks The number of pending webhooks.
	 */
	public static function set_pending_webhooks_count( $pending_webhooks ) {
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_PENDING_WEBHOOKS : self::OPTION_LIVE_PENDING_WEBHOOKS;
		update_option( $option, $pending_webhooks );
	}

	/**
	 * Gets the number of pending webhooks.
	 *
	 * @since 9.7.0
	 *
	 * @return int The number of pending webhooks.
	 */
	public static function get_pending_webhooks_count() {
		$option = WC_Stripe_Mode::is_test() ? self::OPTION_TEST_PENDING_WEBHOOKS : self::OPTION_LIVE_PENDING_WEBHOOKS;
		return get_option( $option, 0 );
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
		$test_mode           = WC_Stripe_Mode::is_test();
		$code                = self::get_webhook_status_code();
		$pending_webhooks    = self::get_pending_webhooks_count();

		$date_format = 'Y-m-d H:i:s e';

		$message = '';

		switch ( $code ) {
			case 1: // Case 1 (Nominal case): Most recent = success
				$message = sprintf(
					$test_mode ?
						/* translators: 1) date and time of last webhook received, e.g. 2020-06-28 10:30:50 UTC */
						__( 'The most recent test webhook, timestamped %s, was processed successfully.', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time of last webhook received, e.g. 2020-06-28 10:30:50 UTC */
						__( 'The most recent live webhook, timestamped %s, was processed successfully.', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_success_at )
				);
				break;
			case 2: // Case 2: No webhooks received yet
				$message = sprintf(
					$test_mode ?
						/* translators: 1) date and time webhook monitoring began, e.g. 2020-06-28 10:30:50 UTC */
						__( 'No test webhooks have been received since monitoring began at %s.', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time webhook monitoring began, e.g. 2020-06-28 10:30:50 UTC */
						__( 'No live webhooks have been received since monitoring began at %s.', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $monitoring_began_at )
				);
				break;
			case 3: // Case 3: Failure after success
				$message = sprintf(
					$test_mode ?
						/*
						 * translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time of last successful webhook e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The most recent test webhook, received at %1$s, could not be processed. Reason: %2$s. (The last test webhook to process successfully was timestamped %3$s.)', 'woocommerce-gateway-stripe' ) :
						/*
						 * translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time of last successful webhook e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The most recent live webhook, received at %1$s, could not be processed. Reason: %2$s. (The last live webhook to process successfully was timestamped %3$s.)', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_failure_at ),
					$last_error,
					gmdate( $date_format, $last_success_at ),
				);
				break;
			default: // Case 4: Failure with no prior success
				$message = sprintf(
					$test_mode ?
						/* translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time webhook monitoring began e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The most recent test webhook, received at %1$s, could not be processed. Reason: %2$s. (No test webhooks have been processed successfully since monitoring began at %3$s.)', 'woocommerce-gateway-stripe' ) :
						/* translators: 1) date and time of last failed webhook e.g. 2020-06-28 10:30:50 UTC
						 * translators: 2) reason webhook failed
						 * translators: 3) date and time webhook monitoring began e.g. 2020-05-28 10:30:50 UTC
						 */
						__( 'Warning: The most recent live webhook, received at %1$s, could not be processed. Reason: %2$s. (No live webhooks have been processed successfully since monitoring began at %3$s.)', 'woocommerce-gateway-stripe' ),
					gmdate( $date_format, $last_failure_at ),
					$last_error,
					gmdate( $date_format, $monitoring_began_at ),
				);
		}

		if ( $pending_webhooks > 0 ) {
			$message .= '. ' . sprintf(
				/* translators: 1) number of pending webhooks */
				_n(
					'There is at least %d webhook pending.',
					'There are approximately %d webhooks pending.',
					$pending_webhooks,
					'woocommerce-gateway-stripe'
				),
				$pending_webhooks
			);
		}

		return $message;
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
};
