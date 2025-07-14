<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Log all things!
 *
 * @since 4.0.0
 * @version 4.0.0
 */
class WC_Stripe_Logger {

	const WC_LOG_FILENAME = 'woocommerce-gateway-stripe';

	const LOG_CONTEXT = [
		'source'             => self::WC_LOG_FILENAME,
		'stripe_version'     => WC_STRIPE_VERSION,
		'stripe_api_version' => WC_Stripe_API::STRIPE_API_VERSION,
	];

	/**
	 * Log handler instance.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_logger/
	 * @see https://developer.woocommerce.com/docs/best-practices/data-management/logging/#log-handlers
	 *
	 * @var WC_Logger
	 */
	public static $logger;

	/**
	 * Utilize WC logger class
	 *
	 * @deprecated 9.7.0 Use the shortcut methods for each log severity level: info(), error(), etc. instead.
	 *
	 * @since 4.0.0
	 */
	public static function log( $message, $start_time = null, $end_time = null ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( ! apply_filters( 'wc_stripe_logging', true, $message ) ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		$log_entry  = "\n" . '====Stripe Version: ' . WC_STRIPE_VERSION . '====' . "\n";
		$log_entry .= '====Stripe Plugin API Version: ' . WC_Stripe_API::STRIPE_API_VERSION . '====' . "\n";

		if ( ! is_null( $start_time ) ) {
			$formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
			$end_time             = is_null( $end_time ) ? current_time( 'timestamp' ) : $end_time;
			$formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
			$elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

			$log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
			$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

		} else {
			$log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";
		}

		self::$logger->debug( $log_entry, [ 'source' => self::WC_LOG_FILENAME ] );
	}

	// Logs have eight different severity levels:
	// - emergency
	// - alert
	// - critical
	// - error
	// - warning
	// - notice
	// - info
	// - debug

	/**
	 * Creates a log entry of type emergency.
	 *
	 * @since 9.7.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function emergency( $message, $context = [] ) {
		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->emergency( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type alert.
	 *
	 * @since 9.7.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function alert( $message, $context = [] ) {
		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->alert( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type critical.
	 *
	 * @since 9.7.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function critical( $message, $context = [] ) {
		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->critical( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type error.
	 *
	 * @since 4.0.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function error( $message, $context = [] ) {
		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->error( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type warning.
	 *
	 * @since 9.7.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function warning( $message, $context = [] ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->warning( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type notice.
	 *
	 * @since 9.7.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function notice( $message, $context = [] ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->notice( $message, array_merge( self::LOG_CONTEXT, $context ) );
	}

	/**
	 * Creates a log entry of type info.
	 *
	 * @since 9.7.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function info( $message, $context = [] ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->info( $message, array_merge( self::LOG_CONTEXT, $context ) );
	}

	/**
	 * Creates a log entry of type debug.
	 *
	 * @since 4.0.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function debug( $message, $context = [] ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->debug( $message, array_merge( self::LOG_CONTEXT, $context ) );
	}

	/**
	 * Whether we can log based on the plugin settings.
	 *
	 * @return boolean
	 */
	public static function can_log(): bool {
		$settings = WC_Stripe_Helper::get_stripe_settings();

		if ( empty( $settings ) || ( isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) ) {
			return false;
		}

		return true;
	}
}
