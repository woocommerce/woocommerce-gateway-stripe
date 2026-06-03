<?php

/**
 * Provides useful methods to test logic related to the Optimized Checkout.
 */
class OC_Test_Helper {
	/**
	 * Enables the Optimized Checkout feature flag and sets the corresponding setting.
	 *
	 * @return void
	 */
	public static function enable_oc() {
		$stripe_settings                               = WC_Stripe::get_settings();
		$stripe_settings['optimized_checkout_element'] = 'yes';
		WC_Stripe::update_settings( $stripe_settings );
	}

	/**
	 * Disables the Optimized Checkout feature flag and sets the corresponding setting.
	 *
	 * @return void
	 */
	public static function disable_oc() {
		$stripe_settings                               = WC_Stripe::get_settings();
		$stripe_settings['optimized_checkout_element'] = 'no';
		WC_Stripe::update_settings( $stripe_settings );
	}
}
