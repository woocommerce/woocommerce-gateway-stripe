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

	public const WC_LOG_FILENAME = 'woocommerce-gateway-stripe';

	public const LOG_CONTEXT = [
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
		if ( ! self::can_log( 'warning' ) ) {
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
		if ( ! self::can_log( 'notice' ) ) {
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
		if ( ! self::can_log( 'info' ) ) {
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
		if ( ! self::can_log( 'debug' ) ) {
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
	 * @param string|null $log_level The log level to check. Can be one of 'warning', 'notice', 'info', 'debug'.
	 *
	 * @return boolean
	 */
	public static function can_log( ?string $log_level = null ): bool {
		if ( WC_Stripe_Helper::is_verbose_debug_mode_enabled() ) {
			return true;
		}

		$settings = WC_Stripe_Helper::get_stripe_settings();

		if ( is_array( $settings ) && 'yes' === ( $settings['logging'] ?? 'no' ) ) {
			return true;
		}

		// Return early if there are no listeners for the 'wc_stripe_logger_can_log' filter.
		// We only want to call get_caller() if there are listeners for the filter.
		if ( ! has_filter( 'wc_stripe_logger_can_log' ) ) {
			return false;
		}

		$caller = self::get_caller();

		$calling_class    = null;
		$calling_function = null;

		if ( is_array( $caller ) ) {
			$calling_class    = $caller['class'] ?? null;
			$calling_function = $caller['function'] ?? null;
		}

		/**
		 * Filter to determine if logging is allowed.
		 * Extreme care should be taken when implementing hooks against this filter,
		 * as it will be called many times per request when a filter is active.
		 *
		 * @param boolean     $can_log   Whether logging is allowed.
		 * @param string|null $log_level The log level to check. Can be one of 'warning', 'notice', 'info', 'debug'.
		 * @param string|null $calling_class The class that called the log method. May be null if the caller is a function.
		 * @param string|null $calling_function The function or method that called the WC_Stripe_Logger log method.
		 *
		 * @since 10.8.0
		 */
		$can_log = apply_filters( 'wc_stripe_logger_can_log', false, $log_level, $calling_class, $calling_function );

		if ( is_bool( $can_log ) ) {
			return $can_log;
		}

		return false;
	}

	/**
	 * Get the caller from outside this class.
	 *
	 * @return array|null {
	 *     The calling code that is trying to log something. When not null, has the following properties:
	 *
	 *     @type string|null $class    The class that called the log method. May be null if the caller is a function.
	 *     @type string      $function The function that called the log method.
	 * }
	 *
	 * @since 10.8.0
	 */
	private static function get_caller(): ?array {
		// Ignore arguments and only look at the last few frames, as we only want the first caller outside of this class.
		// - Direct call to WC_Stripe_Logger::can_log() -> self::get_caller() - we only need 2 frames.
		// - Direct call to WC_Stripe_Logger::<log_type>() -> self::can_log() -> self::get_caller() - we need 3 frames.
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );

		// Start looking at frame 1, as we know we were called from within this class.
		for ( $frame_index = 1; $frame_index < 4; $frame_index++ ) {
			$frame = $trace[ $frame_index ] ?? null;
			// Return early if the second frame is not an array or does not contain a class or function.
			if ( ! is_array( $frame ) || ( empty( $frame['class'] ) && empty( $frame['function'] ) ) ) {
				return null;
			}

			$calling_class = $frame['class'] ?? null;

			// If the current frame is from this class, move to the next frame.
			if ( self::class === $calling_class ) {
				continue;
			}

			// @phpstan-ignore nullCoalesce.offset (The 'function' key may be empty if called from outside a function.)
			$calling_function = $frame['function'] ?? null;

			// If we have a calling function, we have something usable. The calling class may be null.
			if ( ! empty( $calling_function ) ) {
				return [
					'class'    => $calling_class,
					'function' => $calling_function,
				];
			}
		}

		return null;
	}
}
