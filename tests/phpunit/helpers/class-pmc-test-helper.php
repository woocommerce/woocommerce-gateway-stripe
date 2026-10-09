<?php

/**
 * Provides useful methods to test logic related to the Payment Method Configuration API.
 */
class PMC_Test_Helper {
	/**
	 * Enables the Payment Method Configuration API for testing purposes.
	 *
	 * @return void
	 */
	public static function enable_pmc( bool $with_connection_details = false ) {
		$stripe_settings                = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'yes';
		if ( $with_connection_details ) {
			$stripe_settings['testmode']             = 'yes';
			$stripe_settings['test_publishable_key'] = 'pk_test_mock';
			$stripe_settings['test_secret_key']      = 'sk_test_mock';
		}
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
	public static function cache_mocked_configuration( string $configuration_id = 'pmc_abcdef' ) {
		$payment_method_configuration = (object) [
			'id'                            => $configuration_id,
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
