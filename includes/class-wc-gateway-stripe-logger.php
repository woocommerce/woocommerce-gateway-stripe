<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Stripe logging class which saves important data to the log
 *
 * @since 2.6.10
 */
class WC_Stripe_Logger {

	public static $logger;

	/**
	 * What rolls down stairs
	 * alone or in pairs,
	 * and over your neighbor's dog?
	 * What's great for a snack,
	 * And fits on your back?
	 * It's log, log, log
	 *
	 * @since 2.6.10
	 */
	public static function log( $message ) {
		if ( empty( self::$logger ) ) {
			self::$logger = new WC_Logger();
		}

		self::$logger->add( 'woocommerce-gateway-stripe', $message );

	}
}

new WC_Stripe_Logger();
