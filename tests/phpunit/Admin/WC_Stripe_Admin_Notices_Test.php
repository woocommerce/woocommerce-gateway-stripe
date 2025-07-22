<?php

namespace WooCommerce\Stripe\Tests\Admin;

use WC_Stripe;
use WC_Stripe_Admin_Notices;
use WC_Stripe_Database_Cache;
use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;
use WC_Subscription;
use WC_Subscriptions;
use WooCommerce\Stripe\Tests\WC_Mock_Stripe_API_Unit_Test_Case;

class WC_Stripe_Admin_Notices_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * The original value of the HPOS option.
	 *
	 * @var string
	 */
	private static $original_hpos_value;

	public function set_up() {
		parent::set_up();
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-admin-notices.php';

		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
				->disableOriginalConstructor()
				->setMethods(
					[
						'get_cached_account_data',
					]
				)
				->getMock();

		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn(
			[
				'test' => 'test',
			]
		);
		$this->mock_payment_method_configurations(
			[
				WC_Stripe_Payment_Methods::CARD,
				WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::EPS,
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$original_hpos_value = get_option( 'woocommerce_custom_orders_table_enabled' );
	}

	/**
	 * @inheritDoc
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		update_option( 'woocommerce_custom_orders_table_enabled', self::$original_hpos_value );
	}

	public function test_no_notices_are_shown_when_user_is_not_admin() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'enabled' => 'yes' ] );
		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();
		$this->assertCount( 0, $notices->notices );
	}

	public function test_no_notices_are_shown_when_stripe_is_not_enabled() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		WC_Stripe_Helper::update_main_stripe_settings( [ 'enabled' => 'no' ] );
		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();
		$this->assertCount( 0, $notices->notices );
	}

	/**
	 * @dataProvider options_to_notices_map
	 */
	public function test_correct_stripe_notices_are_shown_in_all_scenarios( $options_to_set, $expected_notices = [], $expected_output = false, $query_params = [] ) {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		foreach ( $query_params as $param => $value ) {
			$_GET[ $param ] = $value;
		}
		foreach ( $options_to_set as $option_name => $option_value ) {
			update_option( $option_name, $option_value );
		}

		if ( isset( $options_to_set['woocommerce_stripe_settings']['upe_checkout_experience_accepted_payments'] ) ) {
			$this->mock_payment_method_configurations( $options_to_set['woocommerce_stripe_settings']['upe_checkout_experience_accepted_payments'] );
		}

		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		// Displaying the style notice results in an early return.
		if ( ! in_array( 'style', $expected_notices, true ) ) {
			if ( WC_Stripe_Helper::is_wc_lt( WC_STRIPE_FUTURE_MIN_WC_VER ) ) {
				// This means a version support notice will be added.
				$expected_notices[] = 'wcver';
			}
			if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
				// This means the legacy checkout support notice will be added.
				$expected_notices[] = 'legacy_deprecation';
			}
		}

		if ( $expected_output ) {
			$this->assertMatchesRegularExpression( $expected_output, ob_get_contents() );
		}
		ob_end_clean();
		$this->assertCount( count( $expected_notices ), $notices->notices );
		foreach ( $expected_notices as $expected_notice ) {
			$this->assertArrayHasKey( $expected_notice, $notices->notices );
		}
	}

	public function test_currency_notice_is_shown_for_upe_methods() {
		add_filter(
			'pre_option__wcstripe_feature_upe',
			function () {
				return 'yes';
			}
		);
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'testmode'                        => 'no',
				'publishable_key'                 => 'pk_live_valid_test_key',
				'secret_key'                      => 'sk_live_valid_test_key',
				'upe_checkout_experience_enabled' => 'yes',
				'connection_type'                 => 'connect',
			]
		);

		$this->mock_payment_method_configurations(
			[
				WC_Stripe_Payment_Methods::GIROPAY,
				WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::EPS,
			]
		);

		update_option( 'wc_stripe_show_style_notice', 'no' );
		update_option( 'home', 'https://...' );
		update_option( 'wc_stripe_show_sca_notice', 'no' );

		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();
		if ( WC_Stripe_Helper::is_wc_lt( WC_STRIPE_FUTURE_MIN_WC_VER ) ) {
			$this->assertCount( 2, $notices->notices );
			$this->assertArrayHasKey( 'wcver', $notices->notices );
		} else {
			$this->assertCount( 1, $notices->notices );
		}
		$this->assertArrayHasKey( 'upe_payment_methods', $notices->notices );
	}

	public function test_invalid_keys_notice_is_shown_when_account_data_is_not_valid() {
		// We need to re-create the mock object to override the mocked 'get_cached_account_data' function.
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_cached_account_data',
				]
			)
			->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn( null );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'         => 'yes',
				'testmode'        => 'no',
				'publishable_key' => 'pk_live_invalid_test_key',
				'secret_key'      => 'sk_live_invalid_test_secret',
			]
		);
		update_option( 'wc_stripe_show_style_notice', 'no' );
		update_option( 'wc_stripe_show_sca_notice', 'no' );
		update_option( 'wc_stripe_show_legacy_deprecation_notice', 'no' );
		update_option( 'home', 'https://...' );

		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();

		if ( WC_Stripe_Helper::is_wc_lt( WC_STRIPE_FUTURE_MIN_WC_VER ) ) {
			$this->assertCount( 2, $notices->notices );
			$this->assertArrayHasKey( 'wcver', $notices->notices );
		} else {
			$this->assertCount( 1, $notices->notices );
		}

		$this->assertArrayHasKey( 'keys', $notices->notices );
		$this->assertMatchesRegularExpression( '/Your customers cannot use Stripe on checkout/', $notices->notices['keys']['message'] );
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
						'enabled'              => 'yes',
						'testmode'             => 'yes',
						'test_publishable_key' => 'pk_test_valid_test_key',
						'test_secret_key'      => 'sk_test_valid_test_key',
					],
					'wc_stripe_show_style_notice' => 'no',
					'wc_stripe_show_sca_notice'   => 'no',
					'home'                        => 'https://...',
				],
				[
					'mode',
				],
				'/All transactions are simulated. Customers can\'t make real purchases through Stripe./',
				[
					'page'    => 'wc-settings',
					'section' => 'stripe',
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
						'enabled'         => 'yes',
						'three_d_secure'  => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
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
				'/and use the \<strong\>Configure Connection\<\/strong\> button to reconnect/',
			],
			[
				[
					'woocommerce_stripe_settings'    => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
					],
					'wc_stripe_show_style_notice'    => 'no',
					'wc_stripe_show_sca_notice'      => 'no',
					'_wcstripe_feature_upe_settings' => 'yes',
					'home'                           => 'https://...',
				],
				[],
				false,
				[
					'page'    => 'wc-settings',
					'section' => 'stripe',
				],
			],
			[
				[
					'woocommerce_stripe_settings'    => [
						'enabled' => 'yes',
					],
					'wc_stripe_show_style_notice'    => 'no',
					'wc_stripe_show_sca_notice'      => 'no',
					'_wcstripe_feature_upe_settings' => 'yes',
					'home'                           => 'https://...',
				],
				[
					'keys',
				],
				false,
				[
					'page' => 'wc-settings',
				],
				'/and use the \<strong\>Configure Connection\<\/strong\> button to reconnect/',
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
				'/Stripe is in test mode however your API keys may not be valid/',
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
				[
					'mode',
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
				'/Stripe is in live mode however your API keys may not be valid/',
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
				'/and use the \<strong\>Configure Connection\<\/strong\> button to reconnect/',
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
				'/and use the \<strong\>Configure Connection\<\/strong\> button to reconnect/',
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
			[
				[
					'woocommerce_stripe_settings'         => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
						'upe_checkout_experience_accepted_payments' => [ 'card', 'eps' ],
					],
					'wc_stripe_show_style_notice'         => 'no',
					'home'                                => 'https://...',
					'wc_stripe_show_sca_notice'           => 'no',
				],
				[
					'upe_payment_methods',
				],
			],
		];
	}

	/**
	 * Test for `subscription_check_detachment`.
	 *
	 * @return void
	 * @dataProvider provide_test_subscription_check_detachment
	 */
	public function test_subscription_check_detachment( $hpos_enabled, $theorder_global, $request_params, $post_globals ) {
		global $theorder;
		$original_order = $theorder;

		if ( count( $request_params ) > 0 ) {
			$_REQUEST = $request_params;
		}

		if ( count( $post_globals ) > 0 ) {
			foreach ( $post_globals as $key => $value ) {
				$GLOBALS[ $key ] = $value;
				if ( 'post' === $key && is_a( $value, 'WC_Subscription' ) ) {
					WC_Subscriptions::set_wcs_get_subscription(
						function ( $id ) use ( $value ) {
							return $value;
						}
					);
				}
			}
		}

		if ( $hpos_enabled ) {
			update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
		} else {
			update_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		}

		if ( ! is_null( $theorder_global ) ) {
			$theorder = $theorder_global;
		}

		// Mock response from Stripe API.
		$test_request = function () {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'customer' => null,
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$notices = new WC_Stripe_Admin_Notices();
		$notices->subscription_check_detachment();

		$actual = $notices->notices;

		// Clean up.
		remove_filter( 'pre_http_request', $test_request, 10, 3 );

		unset( $_REQUEST );
		if ( count( $post_globals ) > 0 ) {
			foreach ( $post_globals as $key => $value ) {
				unset( $GLOBALS[ $key ] );
			}
		}

		$theorder = $original_order;
		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_src_123' );

		$this->assertCount( 1, $actual );
		$this->assertArrayHasKey( 'subscription_detached', $actual );
		$this->assertStringContainsString( 'The payment method for this subscription has been detached', $actual['subscription_detached']['message'] );
	}

	/**
	 * Provider for `test_subscription_check_detachment`.
	 *
	 * @return array
	 */
	public function provide_test_subscription_check_detachment() {
		$source_id = 'src_123';

		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_' . $source_id );

		$subscription = new WC_Subscription();
		$subscription->set_id( 123 );
		$subscription->set_status( 'active' );
		$subscription->save();

		$subscription->update_meta_data( '_stripe_source_id', $source_id );
		$subscription->save_meta_data();

		return [
			'HPOS enabled, theorder global' => [
				'hpos enabled'    => true,
				'theorder global' => $subscription,
				'request params'  => [
					'page' => 'wc-orders--shop_subscription',
					'id'   => $subscription->get_id(),
				],
				'post globals'    => [],
			],
			'HPOS disabled, post globals'   => [
				'hpos enabled'    => false,
				'theorder global' => null,
				'request params'  => [
					'page'   => 'wc-orders--shop_subscription',
					'id'     => $subscription->get_id(),
					'post'   => $subscription,
					'action' => 'edit',
				],
				'post globals'    => [
					'post' => $subscription,
				],
			],
		];
	}
}
