<?php

namespace WooCommerce\Stripe\Tests\Helpers;

use WC_Stripe_Database_Cache;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Method_Configurations;
use WC_Stripe_Payment_Methods;

/**
 * Provides useful methods to test logic related to the Payment Method Configuration API.
 */
class PMC_Test_Helper {
	/**
	 * Enables the Payment Method Configuration API for testing purposes.
	 *
	 * @return void
	 */
	public static function enable_pmc() {
		$stripe_settings                = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Disables the Payment Method Configuration API for testing purposes.
	 *
	 * @return void
	 */
	public static function disable_pmc() {
		$stripe_settings                = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Caches a mocked payment method configuration for testing purposes.
	 *
	 * @return void
	 */
	public static function cache_mocked_configuration() {
		$payment_method_configuration = [
			'id'                            => 'pmc_abcdef',
			'object'                        => 'payment_method_configuration',
			'active'                        => true,
			'parent'                        => WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
			'livemode'                      => false,
			WC_Stripe_Payment_Methods::CARD => (object) [
				'display_preference' => (object) [ 'value' => 'on' ],
			],
		];
		WC_Stripe_Database_Cache::set( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, $payment_method_configuration );
	}

	/**
	 * Deletes the cached payment method configuration for testing purposes.
	 *
	 * @return void
	 */
	public static function delete_cached_configuration() {
		WC_Stripe_Database_Cache::delete( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY );
	}
}
