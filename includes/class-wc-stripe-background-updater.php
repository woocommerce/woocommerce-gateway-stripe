<?php
/**
 * Background Updater for WooCommerce Stripe
  */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Background_Updater Class.
 */
class WC_Stripe_Background_Updater extends WC_Background_Updater {

	/**
	 * @var string
	 */
	protected $action = 'wc_stripe_updater';

	/**
	 * Dispatch updater.
	 *
	 * Updater will still run via cron job if this fails for any reason.
	 */
	public function dispatch() {
		$dispatched = parent::dispatch();

		if ( is_wp_error( $dispatched ) ) {
			WC_Stripe::log( sprintf( 'DB updater - Unable to dispatch WooCommerce Stripe updater: %s', $dispatched->get_error_message() ) );
		}
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param string $callback Update callback function
	 * @return mixed
	 */
	protected function task( $callback ) {

		include_once( 'wc-stripe-update-functions.php' );

		if ( is_callable( $callback ) ) {
			WC_Stripe::log( sprintf( 'DB updater - Running %s callback', $callback ) );
			call_user_func( $callback );
			WC_Stripe::log( sprintf( 'DB updater - Finished %s callback', $callback ) );
		} else {
			WC_Stripe::log( sprintf( 'DB updater - Could not find %s callback', $callback ) );
		}

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		WC_Stripe::log( 'Data updater done updating' );
		parent::complete();
	}
}
