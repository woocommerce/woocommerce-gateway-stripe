<?php

/**
 * Provides methods useful when testing UPE-related logic.
 */
class UPE_Test_Helper {
	/**
	 * Creates a mock object for the specified class
	 *
	 * @param string $class_name Name of the class to mock
	 * @return PHPUnit\Framework\MockObject\MockObject
	 */
	private function create_mock( $class_name ) {
		$mock_builder = new PHPUnit\Framework\MockObject\Generator();
		return $mock_builder->getMock( $class_name );
	}

	public function enable_upe_feature_flag() {
		// Force the UPE feature flag on.
		add_filter(
			'pre_option__wcstripe_feature_upe',
			function () {
				return 'yes';
			}
		);
		WC_Stripe_Helper::delete_main_stripe_settings();
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
		$settings                         = WC_Stripe_Helper::get_stripe_settings();
		$settings['connection_type']      = 'connect';
		$settings['test_connection_type'] = 'connect';
		$settings['pmc_enabled']          = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		WC_Stripe_Helper::$stripe_legacy_gateways = [];
	}

	public function enable_upe() {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}

	public function disable_upe() {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}
}
