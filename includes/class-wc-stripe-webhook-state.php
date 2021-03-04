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
		$stripe_settings = get_option( 'woocommerce_stripe_settings', [] );
		return ( ! empty( $stripe_settings['testmode'] ) && 'yes' === $stripe_settings['testmode'] ) ? true : false;
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
	 * Returns the localized reason the last webhook failed.
	 *
	 * @since 5.0.0
	 * @return string Reason the last webhook failed.
	 */
	public static function get_last_error_reason() {
		$option     = self::get_testmode() ? self::OPTION_TEST_LAST_ERROR : self::OPTION_LIVE_LAST_ERROR;
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

		$date_format = 'Y-m-d H:i:s e';

		// Case 1 (Nominal case): Most recent = success
		if ( $last_success_at > $last_failure_at ) {
			$message = sprintf(
				$test_mode ?
					/* translators: 1) date and time of last webhook received, e.g. 2020-06-28 10:30:50 UTC */
					__( 'The most recent test webhook, timestamped %s, was processed successfully.', 'woocommerce-gateway-stripe' ) :
					/* translators: 1) date and time of last webhook received, e.g. 2020-06-28 10:30:50 UTC */
					__( 'The most recent live webhook, timestamped %s, was processed successfully.', 'woocommerce-gateway-stripe' ),
				gmdate( $date_format, $last_success_at )
			);
			return $message;
		}

		// Case 2: No webhooks received yet
		if ( ( 0 == $last_success_at ) && ( 0 == $last_failure_at ) ) {
			$message = sprintf(
				$test_mode ?
					/* translators: 1) date and time webhook monitoring began, e.g. 2020-06-28 10:30:50 UTC */
					__( 'No test webhooks have been received since monitoring began at %s.', 'woocommerce-gateway-stripe' ) :
					/* translators: 1) date and time webhook monitoring began, e.g. 2020-06-28 10:30:50 UTC */
					__( 'No live webhooks have been received since monitoring began at %s.', 'woocommerce-gateway-stripe' ),
				gmdate( $date_format, $monitoring_began_at )
			);
			return $message;
		}

		// Case 3: Failure after success
		if ( $last_success_at > 0 ) {
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
				gmdate( $date_format, $last_success_at )
			);
			return $message;
		}

		// Case 4: Failure with no prior success
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
			gmdate( $date_format, $monitoring_began_at )
		);
		return $message;
	}
};
