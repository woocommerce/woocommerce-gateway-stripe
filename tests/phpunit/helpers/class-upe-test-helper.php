<?php

/**
 * Provides methods useful when testing UPE-related logic.
 */
class UPE_Test_Helper {

	public function enable_upe_feature_flag() {
		// Force the UPE feature flag on.
		add_filter(
			'pre_option__wcstripe_feature_upe',
			function() {
				return 'yes';
			}
		);
		delete_option( 'woocommerce_stripe_settings' );
		$this->reload_payment_gateways();
	}

	public function reload_payment_gateways() {
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
	}

	public function enable_upe() {
		$settings = get_option( 'woocommerce_stripe_settings', [] );
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'yes';
		update_option( 'woocommerce_stripe_settings', $settings );
	}
}
