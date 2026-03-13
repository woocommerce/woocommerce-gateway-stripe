<?php

/**
 * Tests for the admin notices class.
 */
class WC_Stripe_Admin_Notices_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * The original value of the HPOS option.
	 *
	 * @var string
	 */
	private static $original_hpos_value;

	/**
	 * The original `WC_Stripe_Connect` instance, to be restored after tests.
	 *
	 * @var WC_Stripe_Connect
	 */
	private WC_Stripe_Connect $stripe_connect_original;

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
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

		// overriding the `WC_Stripe_Connect` in woocommerce_gateway_stripe(),
		$stripe_connect_mock = $this->createPartialMock(
			WC_Stripe_Connect::class,
			[ 'is_connected_via_oauth' ]
		);
		$stripe_connect_mock
			->expects( $this->any() )
			->method( 'is_connected_via_oauth' )
			->willReturn( true );

		$this->stripe_connect_original        = woocommerce_gateway_stripe()->connect;
		woocommerce_gateway_stripe()->connect = $stripe_connect_mock;
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();

		delete_option( 'wc_stripe_version' );
		delete_option( 'wc_stripe_show_ece_location_notice' );

		// Restoring the original `WC_Stripe_Connect` instance.
		woocommerce_gateway_stripe()->connect = $this->stripe_connect_original;
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

	/**
	 * Test that no notices are shown when the user is not an admin.
	 *
	 * @return void
	 */
	public function test_no_notices_are_shown_when_user_is_not_admin() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'enabled' => 'yes' ] );
		$notices = new WC_Stripe_Admin_Notices();
		ob_start();
		$notices->admin_notices();
		ob_end_clean();
		$this->assertCount( 0, $notices->notices );
	}

	/**
	 * Test that no notices are shown when Stripe is not enabled.
	 *
	 * @return void
	 */
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
	 * Test that the correct notices are shown in all scenarios.
	 *
	 * @param array $options_to_set         Options to set before running the test.
	 * @param bool $is_oauth_connected      Optional. Whether the account is connected via OAuth. Default true.
	 * @param array $expected_notices       Notices expected to be shown.
	 * @param string|false $expected_output Optional. If set, the output is expected to match this regex.
	 * @param array $query_params           Optional. Query parameters to set before running the test.
	 * @return void
	 *
	 * @dataProvider options_to_notices_map
	 */
	public function test_correct_stripe_notices_are_shown_in_all_scenarios(
		array $options_to_set,
		bool $is_oauth_connected = true,
		array $expected_notices = [],
		$expected_output = false,
		array $query_params = []
	) {
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

		if ( ! $is_oauth_connected ) {
			woocommerce_gateway_stripe()->connect = $this->createPartialMock(
				WC_Stripe_Connect::class,
				[ 'is_connected_via_oauth' ]
			);
			woocommerce_gateway_stripe()->connect
				->expects( $this->any() )
				->method( 'is_connected_via_oauth' )
				->willReturn( false );
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

	/**
	 * Data provider for `test_correct_stripe_notices_are_shown_in_all_scenarios`.
	 *
	 * @return array
	 */
	public function options_to_notices_map(): array {
		return [
			[
				[
					'woocommerce_stripe_settings' => [ 'enabled' => 'yes' ],
				],
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
				'is oauth connected' => true,
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
					'woocommerce_stripe_settings' => [
						'enabled'         => 'yes',
						'testmode'        => 'no',
						'publishable_key' => 'pk_live_valid_test_key',
						'secret_key'      => 'sk_live_valid_test_key',
						'upe_checkout_experience_accepted_payments' => [ 'card', 'eps' ],
					],
					'wc_stripe_show_style_notice' => 'no',
					'home'                        => 'https://...',
					'wc_stripe_show_sca_notice'   => 'no',
				],
				'is oauth connected' => true,
				[
					'upe_payment_methods',
				],
			],
			'OAuth required notice' => [
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
				'is oauth connected' => false,
				/* We are not showing the oauth_required notice for the time being. */
				[],
			],
		];
	}

	/**
	 * Test that the currency notice is shown when UPE methods are enabled.
	 *
	 * @return void
	 */
	public function test_currency_notice_is_shown_for_upe_methods() {
		add_filter(
			'pre_option__wcstripe_feature_upe',
			function () {
				return 'yes';
			}
		);
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$this->mock_payment_method_configurations(
			[
				WC_Stripe_Payment_Methods::CARD,
				WC_Stripe_Payment_Methods::GIROPAY,
				WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::EPS,
			]
		);

		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'enabled'                         => 'yes',
				'testmode'                        => 'yes',
				'test_publishable_key'            => 'pk_test_valid_test_key',
				'test_secret_key'                 => 'sk_test_valid_test_key',
				'upe_checkout_experience_enabled' => 'yes',
				'connection_type'                 => 'connect',
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

	/**
	 * Test that the invalid keys notice is shown when account data is not valid.
	 *
	 * @return void
	 */
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
			WC_Subscriptions::$wcs_get_subscription = null;
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
		$subscription->set_payment_method( 'stripe_klarna' );
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

	/**
	 * Tests for `subscription_check_detachment_bulk_action`.
	 *
	 * @param array|null $request_params Request parameters to simulate.
	 * @param int $expected_count Expected number of notices.
	 * @param string $expected_content Expected content in the notice message.
	 * @return void
	 *
	 * @dataProvider provide_test_subscription_check_detachment_bulk_action
	 */
	public function test_subscription_check_detachment_bulk_action( $request_params, $subscriptions, $expected_count, $expected_content ) {
		if ( $request_params ) {
			$_REQUEST = $request_params;
		}

		if ( count( $subscriptions ) > 0 ) {
			WC_Subscriptions::set_wcs_get_subscription(
				function ( $id ) use ( $subscriptions ) {
					return $subscriptions[0];
				}
			);
		}

		$notices = new WC_Stripe_Admin_Notices();
		$notices->subscription_check_detachment_bulk_action();

		$actual = $notices->notices;

		// Clean up.
		unset( $_REQUEST );
		WC_Subscriptions::$wcs_get_subscription = null;

		$this->assertCount( $expected_count, $actual );

		if ( $expected_content ) {
			$this->assertArrayHasKey( 'subscription_detached_bulk_action', $actual );
			$this->assertStringContainsString( $expected_content, $actual['subscription_detached_bulk_action']['message'] );
		} else {
			$this->assertArrayNotHasKey( 'subscription_detached_bulk_action', $actual );
		}
	}

	/**
	 * Data provider for `test_subscription_check_detachment_bulk_action`.
	 *
	 * @return array
	 */
	public function provide_test_subscription_check_detachment_bulk_action() {
		$subscription = new WC_Subscription();
		$subscription->save();

		return [
			'detached subscription IDs, but not actual subscriptions' => [
				'request params'   => [ 'detached-subscriptions' => '123' ],
				'subscriptions'    => [],
				'expected count'   => 1,
				'expected content' => 'No detached subscriptions found.',
			],
			'detached subscription IDs, with actual subscriptions' => [
				'request params'   => [ 'detached-subscriptions' => '123' ],
				'subscriptions'    => [ $subscription ],
				'expected count'   => 1,
				'expected content' => 'Below are the affected subscriptions and their update links:',
			],
			'no detached subscriptions' => [
				'request params'   => null,
				'subscriptions'    => [],
				'expected count'   => 0,
				'expected content' => '',
			],
		];
	}

	/**
	 * Creates a WC_Stripe_Admin_Notices instance with hooks removed to prevent side effects.
	 *
	 * @return WC_Stripe_Admin_Notices
	 */
	private function create_admin_notices_instance(): WC_Stripe_Admin_Notices {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'wp_loaded' );
		remove_all_actions( 'woocommerce_stripe_updated' );
		return new WC_Stripe_Admin_Notices();
	}

	/**
	 * Test that stripe_updated() sets the ECE location notice flag correctly based on the previous version.
	 *
	 * @param string|null $previous_version  Version to set as previous, or null to delete.
	 * @param string|null $initial_flag      Initial value for the notice option, or null to delete.
	 * @param string|false $expected         Expected option value after stripe_updated().
	 *
	 * @dataProvider provide_ece_location_flag_scenarios
	 */
	public function test_stripe_updated_sets_ece_location_flag( $previous_version, $initial_flag, $expected ) {
		if ( null === $previous_version ) {
			delete_option( 'wc_stripe_version' );
		} else {
			update_option( 'wc_stripe_version', $previous_version );
		}

		if ( null === $initial_flag ) {
			delete_option( 'wc_stripe_show_ece_location_notice' );
		} else {
			update_option( 'wc_stripe_show_ece_location_notice', $initial_flag );
		}

		$notices = $this->create_admin_notices_instance();
		$notices->stripe_updated();

		$this->assertSame( $expected, get_option( 'wc_stripe_show_ece_location_notice' ) );
	}

	/**
	 * Data provider for test_stripe_updated_sets_ece_location_flag.
	 *
	 * @return array
	 */
	public function provide_ece_location_flag_scenarios(): array {
		return [
			'affected version sets flag'                    => [ '10.2.0', null, 'yes' ],
			'affected version does not overwrite dismissal' => [ '10.2.0', 'no', 'no' ],
			'pre-affected version does not set flag'        => [ '10.0.0', null, false ],
			'post-fix version does not set flag'            => [ '10.4.0', null, false ],
			'fresh install does not set flag'               => [ null, null, false ],
		];
	}

	/**
	 * Test that check_express_checkout_location() shows or hides the notice based on settings.
	 *
	 * @param string|null $flag_value       Value for the notice option, or null to delete.
	 * @param array       $stripe_settings  Stripe settings to set.
	 * @param bool        $expect_notice    Whether the notice should be present.
	 *
	 * @dataProvider provide_ece_location_notice_scenarios
	 */
	public function test_ece_location_notice_display( $flag_value, array $stripe_settings, bool $expect_notice ) {
		if ( null === $flag_value ) {
			delete_option( 'wc_stripe_show_ece_location_notice' );
		} else {
			update_option( 'wc_stripe_show_ece_location_notice', $flag_value );
		}

		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		$notices = $this->create_admin_notices_instance();
		$notices->check_express_checkout_location();

		if ( $expect_notice ) {
			$this->assertArrayHasKey( 'ece_location', $notices->notices );
		} else {
			$this->assertArrayNotHasKey( 'ece_location', $notices->notices );
		}
	}

	/**
	 * Data provider for test_ece_location_notice_display.
	 *
	 * @return array
	 */
	public function provide_ece_location_notice_scenarios(): array {
		$base_settings = [
			'enabled'          => 'yes',
			'express_checkout' => 'yes',
		];

		return [
			'shown when all criteria met (product+cart, no checkout)' => [
				'yes',
				array_merge( $base_settings, [ 'express_checkout_button_locations' => [ 'product', 'cart' ] ] ),
				true,
			],
			'not shown when express checkout disabled' => [
				'yes',
				array_merge(
					$base_settings,
					[
						'express_checkout'                 => 'no',
						'express_checkout_button_locations' => [ 'product', 'cart' ],
					]
				),
				false,
			],
			'not shown when checkout already in locations' => [
				'yes',
				array_merge( $base_settings, [ 'express_checkout_button_locations' => [ 'product', 'cart', 'checkout' ] ] ),
				false,
			],
			'not shown when product not in locations' => [
				'yes',
				array_merge( $base_settings, [ 'express_checkout_button_locations' => [ 'cart' ] ] ),
				false,
			],
			'not shown when notice dismissed' => [
				'no',
				array_merge( $base_settings, [ 'express_checkout_button_locations' => [ 'product', 'cart' ] ] ),
				false,
			],
			'not shown when flag never set' => [
				null,
				array_merge( $base_settings, [ 'express_checkout_button_locations' => [ 'product', 'cart' ] ] ),
				false,
			],
		];
	}

	/**
	 * Test that dismissing the ece_location notice sets the option to 'no'.
	 */
	public function test_hide_notices_dismisses_ece_location_notice() {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		update_option( 'wc_stripe_show_ece_location_notice', 'yes' );

		$_GET['wc-stripe-hide-notice']   = 'ece_location';
		$_GET['_wc_stripe_notice_nonce'] = wp_create_nonce( 'wc_stripe_hide_notices_nonce' );

		$notices = $this->create_admin_notices_instance();
		$notices->hide_notices();

		$this->assertEquals( 'no', get_option( 'wc_stripe_show_ece_location_notice' ) );

		unset( $_GET['wc-stripe-hide-notice'], $_GET['_wc_stripe_notice_nonce'] );
	}
}
