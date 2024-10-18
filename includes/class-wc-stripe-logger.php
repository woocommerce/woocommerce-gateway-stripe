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

	public static $logger;
	const WC_LOG_FILENAME = 'woocommerce-gateway-stripe';

	/**
	 * Utilize WC logger class
	 *
	 * @since 4.0.0
	 * @version 4.0.0
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

	/**
	 * Creates a log entry of type error.
	 *
	 * @param string $message To send to the log file.
	 * @return void
	 */
	public static function error( $message ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->error( $message, [ 'source' => self::WC_LOG_FILENAME ] );
	}

	/**
	 * Creates a log entry of type debug.
	 *
	 * @param string $message To send to the log file.
	 * @return void
	 */
	public static function debug( $message ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->debug( $message, [ 'source' => self::WC_LOG_FILENAME ] );
	}

	/**
	 * Whether we can log based on settings and filters.
	 *
	 * @return boolean
	 */
	private static function can_log(): bool {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return false;
		}

		$settings = WC_Stripe_Helper::get_stripe_settings();

		if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Log a message with additional context about the request for troubleshooting purposes.
	 *
	 * @param string $message Message to log.
	 */
	public static function log_detailed_info( $message ) {
		if ( ! self::can_log() ) {
			return;
		}

		if ( ! apply_filters( 'wc_stripe_logging', true, $message ) ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		$context = [ 'source' => self::WC_LOG_FILENAME ];

		// Record how long this request has been running.
		if ( defined( 'WCS_INIT_TIMESTAMP' ) ) {
			$context['init_timestamp'] = WCS_INIT_TIMESTAMP;
			$context['running-time']   = gmdate( 'U' ) - WCS_INIT_TIMESTAMP;
		} else {
			if ( ! defined( 'WC_STRIPE_INIT_TIMESTAMP' ) ) {
				define( 'WC_STRIPE_INIT_TIMESTAMP', gmdate( 'U' ) );
			}
			$context['init_timestamp'] = WCS_INIT_TIMESTAMP;
			$context['running-time'] = gmdate( 'U' ) - WC_STRIPE_INIT_TIMESTAMP . '*';
		}

		// Record the memory usage.
		$context['memory-usage'] = memory_get_usage( true ) . ' bytes';

		// Record the memory limit.
		if ( ! defined( 'WC_STRIPE_MEMORY_LIMIT' ) ) {
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				$memory_limit = '128M - assumed';
			}

			if ( ! $memory_limit || -1 === $memory_limit || '-1' === $memory_limit ) {
				$memory_limit = '32G - unlimited';
			}

			define( 'WC_STRIPE_MEMORY_LIMIT', $memory_limit );
		}

		$context['wp-memory-limit'] = WC_STRIPE_MEMORY_LIMIT;

		self::$logger->info( $message, $context );
	}
}
