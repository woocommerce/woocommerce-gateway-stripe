<?php
/**
 * Test harness for driving WC_Stripe_Webhook_Handler::check_for_webhook() end to end.
 *
 * @package WooCommerce_Stripe/Tests/Helpers
 */

require_once __DIR__ . '/class-wc-stripe-webhook-terminated-exception.php';

/**
 * Simulates a real Stripe webhook request against WC_Stripe_Webhook_Handler::check_for_webhook().
 *
 * This class implements that via two key approaches:
 *
 * 1. This class doubles as a stream wrapper that replaces PHP's built-in `php://` wrapper for the duration of
 *    a single dispatch so `file_get_contents( 'php://input' )` returns the supplied body.
 * 2. This class throws from `status_header()` via the `'status_header'` filter, which ensures we handle
 *    `exit()` calls and can check the status code that would have been returned.
 *
 * Both the wrapper and the superglobals are restored in a `finally` block: leaving the mock `php://`
 * wrapper registered would break every later test that touches a `php://` stream.
 */
class WC_Stripe_Webhook_Request_Simulator {

	/**
	 * Required by the streams API so stream contexts can be attached to the wrapper.
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * Body served for reads of `php://input` while the wrapper is registered.
	 *
	 * @var string
	 */
	private static $body = '';

	/**
	 * Read cursor into {@see self::$body}.
	 *
	 * @var int
	 */
	private $position = 0;

	/**
	 * Whether the current PHP SAPI lets us fake request headers.
	 *
	 * WC_Stripe_Webhook_Handler::get_request_headers() prefers getallheaders() when it exists and
	 * only falls back to scanning `$_SERVER['HTTP_*']`, which is the only channel a test can write.
	 * PHP CLI does not define getallheaders(), so this is true under PHPUnit — but assert it rather
	 * than assume it, so the harness fails loudly instead of silently sending headerless requests.
	 *
	 * @return bool
	 */
	public static function can_simulate_headers(): bool {
		return ! function_exists( 'getallheaders' );
	}

	/**
	 * Dispatches a webhook request through the real check_for_webhook() entry point.
	 *
	 * @param WC_Stripe_Webhook_Handler $handler The handler under test.
	 * @param string                    $body    Raw request body served as `php://input`.
	 * @param array                     $headers Request headers, keyed as Stripe sends them (e.g. `STRIPE-SIGNATURE`).
	 * @return int The HTTP status code the handler terminated with, or 0 when it returned without sending one.
	 */
	public static function dispatch( WC_Stripe_Webhook_Handler $handler, string $body, array $headers ): int {
		$original_server = $_SERVER;
		$original_get    = $_GET;

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['wc-api']            = 'wc_stripe';

		// If no headers are provided, unset all default/pre-existing HTTP_* headers in $_SERVER.
		if ( [] === $headers ) {
			foreach ( $_SERVER as $name => $value ) {
				if ( str_starts_with( strtoupper( $name ), 'HTTP_' ) ) {
					unset( $_SERVER[ $name ] );
				}
			}
		} else {
			foreach ( $headers as $name => $value ) {
				$_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) ) ] = $value;
			}
		}

		$status_code             = 0;
		$intercept_status_header = static function ( $status_header, $code ) {
			throw new WC_Stripe_Webhook_Terminated_Exception( (int) $code );
		};

		self::register( $body );
		add_filter( 'status_header', $intercept_status_header, 10, 2 );

		try {
			$handler->check_for_webhook();
		} catch ( WC_Stripe_Webhook_Terminated_Exception $e ) {
			$status_code = $e->status_code;
		} finally {
			remove_filter( 'status_header', $intercept_status_header, 10 );
			self::unregister();

			$_SERVER = $original_server;
			$_GET    = $original_get;
		}

		return $status_code;
	}

	/**
	 * Replaces PHP's built-in `php://` stream wrapper with this one.
	 *
	 * @param string $body Body to serve for `php://input`.
	 */
	private static function register( string $body ): void {
		self::$body = $body;
		stream_wrapper_unregister( 'php' );
		stream_wrapper_register( 'php', self::class );
	}

	/**
	 * Restores PHP's built-in `php://` stream wrapper.
	 */
	private static function unregister(): void {
		self::$body = '';
		stream_wrapper_restore( 'php' );
	}

	/**
	 * Opens the stream. Only `php://input` is served; any other `php://` path fails loudly rather
	 * than silently handing unrelated code the webhook body.
	 *
	 * @param string      $path        Requested stream path.
	 * @param string      $mode        Open mode (ignored).
	 * @param int         $options     Stream options (ignored).
	 * @param string|null $opened_path Set to the opened path (ignored).
	 * @return bool
	 */
	public function stream_open( $path, $mode, $options, &$opened_path ) {
		if ( 'php://input' !== strtolower( $path ) ) {
			return false;
		}

		$this->position = 0;
		return true;
	}

	/**
	 * @param int $count Bytes to read.
	 * @return string
	 */
	public function stream_read( $count ) {
		$chunk           = substr( self::$body, $this->position, $count );
		$this->position += strlen( $chunk );

		return $chunk;
	}

	/**
	 * @return bool
	 */
	public function stream_eof() {
		return $this->position >= strlen( self::$body );
	}

	/**
	 * @return int
	 */
	public function stream_tell() {
		return $this->position;
	}

	/**
	 * @param int $offset Seek offset.
	 * @param int $whence Seek mode.
	 * @return bool
	 */
	public function stream_seek( $offset, $whence = SEEK_SET ) {
		switch ( $whence ) {
			case SEEK_SET:
				$position = $offset;
				break;
			case SEEK_CUR:
				$position = $this->position + $offset;
				break;
			case SEEK_END:
				$position = strlen( self::$body ) + $offset;
				break;
			default:
				return false;
		}

		if ( $position < 0 ) {
			return false;
		}

		$this->position = $position;
		return true;
	}

	/**
	 * @return array
	 */
	public function stream_stat() {
		return [ 'size' => strlen( self::$body ) ];
	}
}
