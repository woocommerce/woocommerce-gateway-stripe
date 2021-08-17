<?php

class WC_Stripe_Admin_Notices_Test extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-admin-notices.php';
	}

	public function test_no_notices_are_shown_when_user_is_not_admin() {
		update_option( 'woocommerce_stripe_settings', [ 'enabled' => 'yes' ] );
		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();
		$this->assertCount( 0, $notices->notices );
	}

	public function test_no_notices_are_shown_when_stripe_is_not_enabled() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'woocommerce_stripe_settings', [ 'enabled' => 'no' ] );
		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();
		$this->assertCount( 0, $notices->notices );
	}

	/**
	 * @dataProvider options_to_notices_map
	 */
	public function test_correct_stripe_notices_are_shown_in_all_scenarios( $options_to_set, $expected_notices = [], $expected_output = false, $query_params = []) {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		foreach ( $query_params as $param => $value ) {
			$_GET[ $param ] = $value;
		}
		foreach ( $options_to_set as $option_name => $option_value ) {
			update_option( $option_name, $option_value );
		}
		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		if ( $expected_output ) {
			$this->assertStringContainsString( $expected_output, ob_get_contents() );
		}
		ob_end_clean();
		$this->assertCount( count( $expected_notices ), $notices->notices );
		foreach ( $expected_notices as $expected_notice ) {
			$this->assertArrayHasKey( $expected_notice, $notices->notices );
		}
	}

	public function options_to_notices_map() {
		return [
			[
				[
					'woocommerce_stripe_settings' => [ 'enabled' => 'yes' ],
				],
				[
					'style',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'        => 'yes',
						'three_d_secure' => 'yes',
					],
				],
				[
					'3ds',
					'style',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'        => 'yes',
						'three_d_secure' => 'yes',
					],
					'wc_stripe_show_3ds_notice'   => 'no',
				],
				[
					'style',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'        => 'yes',
						'three_d_secure' => 'yes',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
				[
					'3ds',
				],
				false,
				[
					'page'    => 'wc-settings',
					'section' => 'stripe',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled' => 'yes',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
				[
					'keys',
				],
				'set your Stripe account keys',
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'              => 'yes',
						'testmode'             => 'yes',
						'test_publishable_key' => 'invalid test key',
						'test_secret_key'      => 'invalid test key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
				[
					'keys',
				],
				'your test keys may not be valid',
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'              => 'yes',
						'testmode'             => 'yes',
						'test_publishable_key' => 'pk_test_valid_test_key',
						'test_secret_key'      => 'sk_test_valid_test_key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'invalid live key',
						'secret_key'      => 'invalid live key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
				[
					'keys',
				],
				'your live keys may not be valid',
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
				],
				[
					'ssl',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'home'                        => 'https://...',
				],
				[
					'sca',
				],
			],
			[
				[
					'woocommerce_stripe_settings'        => [
						'enabled'        => 'yes',
						'testmode'       => 'no',
						'three_d_secure' => 'yes',
					],
					'wc_stripe_show_style_notice'        => 'no',
					'wc_stripe_show_changed_keys_notice' => 'yes',
				],
				[
					'3ds',
					'keys',
					'ssl',
					'sca',
					'changed_keys',
				],
				'set your Stripe account keys',
			],
			[
				[
					'woocommerce_stripe_settings'        => [
						'enabled'  => 'yes',
						'testmode' => 'no',
					],
					'wc_stripe_show_style_notice'        => 'no',
					'wc_stripe_show_changed_keys_notice' => 'yes',
				],
				[
					'keys',
					'ssl',
					'sca',
					'changed_keys',
				],
				'set your Stripe account keys',
			],
			[
				[
					'woocommerce_stripe_settings'        => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice'        => 'no',
					'wc_stripe_show_changed_keys_notice' => 'yes',
				],
				[
					'ssl',
					'sca',
					'changed_keys',
				],
			],
			[
				[
					'woocommerce_stripe_settings'        => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice'        => 'no',
					'wc_stripe_show_changed_keys_notice' => 'yes',
					'home'                               => 'https://...',
				],
				[
					'sca',
					'changed_keys',
				],
			],
			[
				[
					'woocommerce_stripe_settings'        => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice'        => 'no',
					'wc_stripe_show_changed_keys_notice' => 'yes',
					'home'                               => 'https://...',
					'wc_stripe_show_sca_notice'          => 'no',
				],
				[
					'changed_keys',
				],
			],
			[
				[
					'woocommerce_stripe_settings' => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'home'                        => 'https://...',
					'wc_stripe_show_sca_notice'   => 'no',
				],
			],
		];
	}
}
