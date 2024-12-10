<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait WC_Stripe_Capture_Method_Trait
 */
trait WC_Stripe_Capture_Method_Trait {
	/**
	 * Determines whether the "automatic" or "manual" capture setting is enabled.
	 *
	 * @return bool
	 */
	public function is_automatic_capture_enabled() {
		return empty( $this->get_option( 'capture' ) ) || $this->get_option( 'capture' ) === 'yes';
	}
}
