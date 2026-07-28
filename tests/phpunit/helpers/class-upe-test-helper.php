<?php

use PHPUnit\Framework\MockObject\Generator;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Provides methods useful when testing UPE-related logic.
 */
class UPE_Test_Helper {
	/**
	 * Deletes Stripe settings and reloads payment gateways to force re-initialization.
	 *
	 * @return void
	 */
	public function reset_stripe_settings_and_reload_gateways(): void {
		WC_Stripe_Helper::delete_main_stripe_settings();
		$this->reload_payment_gateways();
	}

	/**
	 * Reload payment gateways to reflect any settings changes.
	 *
	 * @return void
	 */
	public function reload_payment_gateways(): void {
		$closure = Closure::bind(
			function () {
				$this->stripe_gateway = null;
			},
			woocommerce_gateway_stripe(),
			WC_Stripe::class
		);
		$closure();
		WC()->payment_gateways()->payment_gateways = [];
		WC()->payment_gateways()->init();
		$settings                         = WC_Stripe_Helper::get_stripe_settings();
		$settings['publishable_key']      = 'pk_live_1234567890';
		$settings['secret_key']           = 'sk_live_1234567890';
		$settings['connection_type']      = 'connect';
		$settings['test_publishable_key'] = 'pk_test_1234567890';
		$settings['test_secret_key']      = 'sk_test_1234567890';
		$settings['test_connection_type'] = 'connect';
		$settings['pmc_enabled']          = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		WC_Stripe_Helper::$stripe_legacy_gateways = [];
	}

	/**
	 * Enable UPE in the Stripe settings.
	 *
	 * @return void
	 */
	public function enable_upe(): void {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}

	/**
	 * Disable UPE in the Stripe settings.
	 *
	 * @return void
	 */
	public function disable_upe(): void {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}
}
