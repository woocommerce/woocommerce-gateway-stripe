<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Mode
 *
 * @method static bool is_live() Whether the extension is in live mode.
 * @method static bool is_test() Whether the extension is in test mode.
 */
class WC_Stripe_Mode {
	/**
	 * Whether the extension is in test mode.
	 *
	 * @var bool
	 */
	private static $is_test;

	/**
	 * Maybe initializes the extension mode if not yet initialized.
	 */
	private static function maybe_init() {
		if ( ! isset( static::$is_test ) ) {
			static::$is_test = 'yes' === ( WC_Stripe_Helper::get_stripe_settings()['testmode'] ?? 'no' );
		}
	}

	/**
	 * Checks if the extension is in test or live mode.
	 *
	 * @param $method string The method name.
	 * @param $arguments = []
	 * @return bool Whether the extension is in test or live mode.
	 */
	public static function __callStatic( $method, $arguments = [] ) {
		// Only allow is_live and is_test methods.
		if ( ! in_array( $method, [ 'is_live', 'is_test' ], true ) ) {
			throw new BadMethodCallException( 'Method not found: ' . $method );
		}

		static::maybe_init();
		return 'is_test' === $method ? static::$is_test : ! static::$is_test;
	}
}
