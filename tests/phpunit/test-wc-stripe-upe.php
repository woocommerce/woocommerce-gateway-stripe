<?php
class WC_Stripe_Test_Upe extends WP_UnitTestCase {
	public function setUp() {
		add_filter( 'pre_option__wcstripe_feature_upe', function() {
			return '1';
		});
		add_filter( 'default_option_woocommerce_stripe_settings', function( $settings ) {
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings['upe_checkout_experience_enabled'] = 'yes';
			return $settings;
		});
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testLegacyPaymentMethodsSupportedByUpeAreNotLoadedWhenUpeIsEnabled() {
		$this->assertTrue( WC_Stripe_Features::is_upe_enabled() );
		$this->assertTrue( WC_Stripe::get_instance()->is_upe_enabled() );

		$loaded_gateway_classes = array_map(
			function( $gateway ) {
				return get_class( $gateway );
			},
			WC()->payment_gateways->payment_gateways()
		);

		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $upe_method ) {
        	$this->assertFalse( in_array( $upe_method::LPM_GATEWAY_CLASS, $loaded_gateway_classes ) );
		}

		$this->assertContains( WC_Stripe_UPE_Payment_Gateway::class, $loaded_gateway_classes );
	}
}
