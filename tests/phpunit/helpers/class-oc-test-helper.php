<?php

namespace WooCommerce\Stripe\Tests\Helpers;

use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;

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
		update_option( WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME, 'yes' );

		$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['optimized_checkout_element'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Disables the Optimized Checkout feature flag and sets the corresponding setting.
	 *
	 * @return void
	 */
	public static function disable_oc() {
		update_option( WC_Stripe_Feature_Flags::OC_FEATURE_FLAG_NAME, 'no' );

		$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['optimized_checkout_element'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}
}
