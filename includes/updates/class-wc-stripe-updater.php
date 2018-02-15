<?php
/**
 * Class WC_Stripe_Updater
 *
 * @since: 4.1.0
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WC_STRIPE_VERSION' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Updater
 *
 * Execute the functions if needed to update.
 */
final class WC_Stripe_Updater {

	/**
	 * WC_Stripe_Updater constructor.
	 */
	public function __construct() {
		$this->check_update();
	}

	/**
	 * Check if the wc-stripe-functions file has a function called 'wc_stripe_update_%WC_STRIPE_VERSION%'.
	 * Execute the function.
	 * Has hooks 'wc_stripe_update_%WC_STRIPE_VERSION%_before' and 'wc_stripe_update_%WC_STRIPE_VERSION%_after'.
	 * %WC_STRIPE_VERSION% to be replaced by the version number without a dot.
	 */
	public function check_update() {

		include_once dirname( __FILE__ ) . '/wc-stripe-update-functions.php';

		if ( function_exists( $this->update_function() ) ) {

			// Before.
			do_action( $this->update_function() . '_before' );

			// Update.
			$this->update_function()();

			// After.
			do_action( $this->update_function() . '_after' );

			// Clean Cache.
			wp_cache_delete();
		}
	}

	/**
	 * Returns the function name of the update of this version
	 *
	 * @return string
	 */
	private function update_function() {
		return sprintf(
			'wc_stripe_update_%d',
			str_replace( '.', '', WC_STRIPE_VERSION )
		);
	}
}
