<?php

/**
 * These tests make assertions against class WC_REST_Stripe_Account_Keys_Controller.
 *
 * @package WooCommerce/Stripe/WC_REST_Stripe_Account_Keys_Controller
 *
 * WC_REST_Stripe_Account_Keys_Controller unit tests.
 */
class WC_REST_Stripe_Account_Keys_Controller_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Tested REST route.
	 */
	const ROUTE = '/wc/v3/wc_stripe/account_keys';

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Stripe_Account_Keys_Controller
	 */
	private $controller;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		// Setup existing keys
		$settings                         = WC_Stripe_Helper::get_stripe_settings();
		$settings['publishable_key']      = 'original-live-key-9999';
		$settings['secret_key']           = '';
		$settings['test_publishable_key'] = 'original-test-key-9999';
		$settings['test_secret_key']      = '';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$mock_account = $this->getMockBuilder( WC_Stripe_Account::class )
							->disableOriginalConstructor()
							->getMock();

		$this->controller = new WC_REST_Stripe_Account_Keys_Controller( $mock_account );
		$this->mock_payment_method_configurations(
			[
				WC_Stripe_Payment_Methods::CARD,
				WC_Stripe_Payment_Methods::LINK,
			]
		);
	}

	public function test_get_account_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'GET', self::ROUTE );

		$response = $this->controller->get_account_keys( $request );
		$expected = [
			'test_publishable_key' => 'original-t**************************************************99',
			'test_secret_key'      => '',
			'test_webhook_secret'  => '',
			'publishable_key'      => 'original-l**************************************************99',
			'secret_key'           => '',
			'webhook_secret'       => '',
		];

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected, $response->get_data() );
	}

	/**
	 * Test as if the user update publishable_keys, secret_key, webhook
	 */
	public function test_adding_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'pk_live-key-12345' );
		$request->set_param( 'secret_key', 'sk_live_secret-key-12345' );
		$request->set_param( 'webhook_secret', 'webhook-secret-12345' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertEquals( 'pk_live-key-12345', $settings['publishable_key'] );
		$this->assertEquals( 'sk_live_secret-key-12345', $settings['secret_key'] );
		$this->assertEquals( 'webhook-secret-12345', $settings['webhook_secret'] );

		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	/**
	 * Test as if the user update test publishable_keys, secret_key, webhook
	 */
	public function test_adding_test_keys_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'test_publishable_key', 'pk_test-live-key-12345' );
		$request->set_param( 'test_secret_key', 'sk_test-secret-key-12345' );
		$request->set_param( 'test_webhook_secret', 'test-webhook-secret-12345' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertEquals( 'pk_test-live-key-12345', $settings['test_publishable_key'] );
		$this->assertEquals( 'sk_test-secret-key-12345', $settings['test_secret_key'] );
		$this->assertEquals( 'test-webhook-secret-12345', $settings['test_webhook_secret'] );

		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-live-key-9999', $settings['publishable_key'] );
	}

	/**
	 * Test updating only 1 property
	 */
	public function test_update_live_key_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'pk_live-key-12345' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertEquals( 'pk_live-key-12345', $settings['publishable_key'] );
		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	/**
	 * Test updating a key to "", as if user deleting the key.
	 */
	public function test_setting_blank_live_key_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', '' );

		$response = $this->controller->set_account_keys( $request );

		$this->assertEquals( 200, $response->get_status() );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertEquals( '', $settings['publishable_key'] );
		// Other settings do not change and do not get erased.
		$this->assertEquals( 'original-test-key-9999', $settings['test_publishable_key'] );
	}

	/**
	 * Test for `set_account_keys` checking if payment methods are reset
	 * @return void
	 */
	public function test_changing_keys_resets_payment_methods() {
		// Default options
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'publishable_key'                                            => 'pk_live-key',
				'secret_key'                                                 => 'sk_live-key',
				'testmode'                                                   => 'no',
				'connection_type'                                            => 'connect',
				'pmc_enabled'                                                => 'yes',
				WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => 'yes',
			]
		);

		// Build request params
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'publishable_key', 'pk_live-key-updated' );

		// Set initial payment methods
		$this->set_stripe_account_data( [ 'country' => 'US' ] );
		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK, WC_Stripe_Payment_Methods::SEPA, WC_Stripe_Payment_Methods::IDEAL ], [] );
		$this->expect_payment_method_configurations_update( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ] );

		$this->controller->set_account_keys( $request );
	}

	/**
	 * Test the various API key validation methods.
	 *
	 * @param string $method     Controller method to call.
	 * @param string $param_name Request parameter name.
	 * @param string $value      The key value to validate.
	 * @param mixed  $expected   `true` on success or a `WP_Error` on failure.
	 * @return void
	 * @dataProvider provide_test_validate_api_key
	 */
	public function test_validate_api_key( string $method, string $param_name, string $value, $expected ) {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( $param_name, $value );

		$response = $this->controller->$method( $value, $request, $param_name );

		$this->assertEquals( $expected, $response, "Testing param: $value" );
	}

	/**
	 * Data provider for `test_validate_api_key`.
	 *
	 * @return array
	 */
	public function provide_test_validate_api_key(): array {
		$live_pk_error = new WP_Error( 400, 'The "Live Publishable Key" should start with "pk_live", enter the correct key.' );
		$live_sk_error = new WP_Error( 400, 'The "Live Secret Key" should start with "sk_live" or "rk_live", enter the correct key.' );
		$test_pk_error = new WP_Error( 400, 'The "Test Publishable Key" should start with "pk_test", enter the correct key.' );
		$test_sk_error = new WP_Error( 400, 'The "Test Secret Key" should start with "sk_test" or "rk_test", enter the correct key.' );

		return [
			// validate_publishable_key
			'live publishable: blank is valid'     => [ 'validate_publishable_key', 'publishable_key', '', true ],
			'live publishable: generic invalid'    => [ 'validate_publishable_key', 'publishable_key', 'asd', $live_pk_error ],
			'live publishable: pk_live is valid'   => [ 'validate_publishable_key', 'publishable_key', 'pk_live_123123', true ],
			'live publishable: sk_live is invalid' => [ 'validate_publishable_key', 'publishable_key', 'sk_live_123123', $live_pk_error ],
			'live publishable: rk_live is invalid' => [ 'validate_publishable_key', 'publishable_key', 'rk_live_123123', $live_pk_error ],
			'live publishable: pk_test is invalid' => [ 'validate_publishable_key', 'publishable_key', 'pk_test_123123', $live_pk_error ],

			// validate_secret_key
			'live secret: blank is valid'          => [ 'validate_secret_key', 'secret_key', '', true ],
			'live secret: generic invalid'         => [ 'validate_secret_key', 'secret_key', 'asd', $live_sk_error ],
			'live secret: pk_live is invalid'      => [ 'validate_secret_key', 'secret_key', 'pk_live_123123', $live_sk_error ],
			'live secret: sk_live is valid'        => [ 'validate_secret_key', 'secret_key', 'sk_live_123123', true ],
			'live secret: rk_live is valid'        => [ 'validate_secret_key', 'secret_key', 'rk_live_123123', true ],
			'live secret: sk_test is invalid'      => [ 'validate_secret_key', 'secret_key', 'sk_test_123123', $live_sk_error ],

			// validate_test_publishable_key
			'test publishable: blank is valid'     => [ 'validate_test_publishable_key', 'test_publishable_key', '', true ],
			'test publishable: generic invalid'    => [ 'validate_test_publishable_key', 'test_publishable_key', 'asd', $test_pk_error ],
			'test publishable: pk_test is valid'   => [ 'validate_test_publishable_key', 'test_publishable_key', 'pk_test_123123', true ],
			'test publishable: sk_test is invalid' => [ 'validate_test_publishable_key', 'test_publishable_key', 'sk_test_123123', $test_pk_error ],
			'test publishable: rk_test is invalid' => [ 'validate_test_publishable_key', 'test_publishable_key', 'rk_test_123123', $test_pk_error ],
			'test publishable: pk_live is invalid' => [ 'validate_test_publishable_key', 'test_publishable_key', 'pk_live_123123', $test_pk_error ],

			// validate_test_secret_key
			'test secret: blank is valid'          => [ 'validate_test_secret_key', 'test_secret_key', '', true ],
			'test secret: generic invalid'         => [ 'validate_test_secret_key', 'test_secret_key', 'asd', $test_sk_error ],
			'test secret: pk_test is invalid'      => [ 'validate_test_secret_key', 'test_secret_key', 'pk_test_123123', $test_sk_error ],
			'test secret: sk_test is valid'        => [ 'validate_test_secret_key', 'test_secret_key', 'sk_test_123123', true ],
			'test secret: rk_test is valid'        => [ 'validate_test_secret_key', 'test_secret_key', 'rk_test_123123', true ],
			'test secret: sk_live is invalid'      => [ 'validate_test_secret_key', 'test_secret_key', 'sk_live_123123', $test_sk_error ],
		];
	}

	/**
	 * Regression test for STRIPE-816: test_account_keys() must not use wp_safe_remote_*,
	 * which would trigger wp_http_validate_url() and fail when the host's DNS is flaky.
	 */
	public function test_test_account_keys_does_not_use_safe_remote_http() {
		$captured_args_by_url = [];

		$capture_filter = function ( $return_value, $parsed_args, $url ) use ( &$captured_args_by_url ) {
			$captured_args_by_url[ $url ] = $parsed_args;

			$body = 'https://api.stripe.com/v1/tokens' === $url
				? [ 'id' => 'tok_visa_test' ]
				: [
					'id'   => 'tok_visa_test',
					'type' => 'pii',
				];

			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => json_encode( $body ),
			];
		};
		add_filter( 'pre_http_request', $capture_filter, 10, 3 );

		$request = new WP_REST_Request( 'POST', self::ROUTE . '/test' );
		$request->set_param( 'live_mode', true );
		$request->set_param( 'publishable', 'pk_live_test_123' );
		$request->set_param( 'secret', 'sk_live_test_123' );

		try {
			$this->controller->test_account_keys( $request );
		} finally {
			remove_filter( 'pre_http_request', $capture_filter, 10 );
		}

		$this->assertArrayHasKey( 'https://api.stripe.com/v1/tokens', $captured_args_by_url );
		$this->assertNotTrue(
			$captured_args_by_url['https://api.stripe.com/v1/tokens']['reject_unsafe_urls'] ?? false,
			'POST to https://api.stripe.com/v1/tokens must not set reject_unsafe_urls'
		);

		$get_url = 'https://api.stripe.com/v1/tokens/tok_visa_test';
		$this->assertArrayHasKey( $get_url, $captured_args_by_url );
		$this->assertNotTrue(
			$captured_args_by_url[ $get_url ]['reject_unsafe_urls'] ?? false,
			'GET to https://api.stripe.com/v1/tokens/{id} must not set reject_unsafe_urls'
		);
	}

	/**
	 * Asserts that set_account_keys() delegates the webhook decommission to the account
	 * and clears the stored webhook data and secret for the affected mode when a webhook is decommissioned.
	 */
	public function test_set_account_keys_decommissions_previous_webhook_when_secret_changes() {
		$previous_webhook_data = [
			'id'     => 'wh_old',
			'url'    => 'https://example.com',
			'secret' => 'sk_live_old',
		];

		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'publishable_key' => 'pk_live_old',
				'secret_key'      => 'sk_live_old',
				'webhook_data'    => $previous_webhook_data,
				'webhook_secret'  => 'whsec_old',
				'testmode'        => 'no',
			]
		);

		$mock_account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account->expects( $this->once() )
			->method( 'maybe_decommission_webhook' )
			->with( $previous_webhook_data, 'sk_live_new-12345' )
			->willReturn( true );
		$mock_account->expects( $this->never() )->method( 'should_decommission_webhook' );

		$controller = new WC_REST_Stripe_Account_Keys_Controller( $mock_account );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'secret_key', 'sk_live_new-12345' );

		$controller->set_account_keys( $request );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [], $settings['webhook_data'] );
		$this->assertSame( '', $settings['webhook_secret'] );
	}
	/**
	 * Key changes must authenticate cleanup before the old account key is overwritten.
	 *
	 * @dataProvider provide_key_change_cleanup
	 */
	public function test_key_change_cleanup_authentication( $mode, $change, $legacy_secret, $failure ) {
		$prefix  = 'test' === $mode ? 'test_' : '';
		$old_key = 'sk_' . $mode . '_old';
		$new_key = 'same' === $change ? $old_key : ( 'remove' === $change ? '' : 'sk_' . $mode . '_new' );
		$data    = [
			'id'  => 'we_old',
			'url' => 'https://example.com',
		];
		if ( null !== $legacy_secret ) {
			$data['secret'] = $legacy_secret;
		}
		$settings                               = WC_Stripe_Helper::get_stripe_settings();
		$settings[ $prefix . 'secret_key' ]     = $old_key;
		$settings[ $prefix . 'webhook_data' ]   = $data;
		$settings[ $prefix . 'webhook_secret' ] = 'whsec_old';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$requests   = [];
		$filter     = function ( $preempt, $args, $url ) use ( &$requests, $prefix, &$failure ) {
			if ( 'https://api.stripe.com/v1/webhook_endpoints/we_old' !== $url ) {
				return $preempt;
			}
			$requests[] = [ $args['method'], $args['headers']['Authorization'], WC_Stripe_Helper::get_stripe_settings()[ $prefix . 'secret_key' ] ];
			if ( 'exception' === $failure ) {
				throw new Exception( 'Network error' );
			}
			if ( 'rate_limit' === $failure ) {
				return [
					'response' => [ 'code' => 429 ],
					'body'     => wp_json_encode( [ 'error' => [ 'code' => 'rate_limit' ] ] ),
				];
			}
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'deleted' => true ] ),
			];
		};
		$account    = $this->getMockBuilder( WC_Stripe_Account::class )
			->setConstructorArgs( [ WC_Stripe::get_instance()->connect, WC_Stripe_API::class ] )
			->onlyMethods( [ 'get_cached_account_data', 'clear_cache' ] )->getMock();
		$controller = new WC_REST_Stripe_Account_Keys_Controller( $account );
		$request    = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( $prefix . 'secret_key', $new_key );
		add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			$response = $controller->set_account_keys( $request );
		} finally {
			remove_filter( 'pre_http_request', $filter );
			WC_Stripe_API::set_secret_key( '' );
		}
		$auth_key       = ! empty( $legacy_secret ) ? $legacy_secret : $old_key;
		$decommissioned = $new_key !== $auth_key;
		$this->assertSame( $decommissioned ? [ [ 'DELETE', 'Basic ' . base64_encode( $auth_key . ':' ), $old_key ] ] : [], $requests );
		if ( $failure && $decommissioned ) {
			$data['secret'] = $auth_key;
		}
		$stored = get_option( 'woocommerce_stripe_settings' );
		$this->assertSame( $new_key, $stored[ $prefix . 'secret_key' ] );
		$this->assertSame( $decommissioned && ! $failure ? [] : $data, $stored[ $prefix . 'webhook_data' ] );
		$this->assertSame( $decommissioned && ! $failure ? '' : 'whsec_old', $stored[ $prefix . 'webhook_secret' ] );
		$this->assertSame( 200, $response->get_status() );

		if ( $failure ) {
			$failure = false;
			add_filter( 'pre_http_request', $filter, 10, 3 );
			try {
				$controller->set_account_keys( $request );
			} finally {
				remove_filter( 'pre_http_request', $filter );
				WC_Stripe_API::set_secret_key( '' );
			}
			$this->assertCount( 2, $requests );
			$this->assertSame( 'Basic ' . base64_encode( $auth_key . ':' ), $requests[1][1] );
			$this->assertSame( [], get_option( 'woocommerce_stripe_settings' )[ $prefix . 'webhook_data' ] );
		}
	}

	public function provide_key_change_cleanup() {
		$cases = [];
		foreach ( [ 'live', 'test' ] as $mode ) {
			$cases[ $mode . ' replacement' ]             = [ $mode, 'replace', null, false ];
			$cases[ $mode . ' disconnect' ]              = [ $mode, 'remove', null, false ];
			$cases[ $mode . ' unchanged' ]               = [ $mode, 'same', null, false ];
			$cases[ $mode . ' failed disconnect' ]       = [ $mode, 'remove', null, 'exception' ];
			$cases[ $mode . ' failed cleanup' ]          = [ $mode, 'replace', null, 'exception' ];
			$cases[ $mode . ' legacy key' ]              = [ $mode, 'replace', 'sk_' . $mode . '_original', false ];
			$cases[ $mode . ' empty stored key' ]        = [ $mode, 'replace', '', false ];
			$cases[ $mode . ' empty key failure' ]       = [ $mode, 'replace', '', 'exception' ];
			$cases[ $mode . ' API rejection' ]           = [ $mode, 'replace', null, 'rate_limit' ];
			$cases[ $mode . ' empty key API rejection' ] = [ $mode, 'replace', '', 'rate_limit' ];
		}
		return $cases;
	}
	/**
	 * Manual setup must use the requested mode even when checkout uses the other mode.
	 *
	 * @dataProvider provide_webhook_setup_modes
	 */
	public function test_webhook_setup_uses_requested_mode( $mode ) {
		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']        = 'live' === $mode ? 'yes' : 'no';
		$settings['secret_key']      = 'sk_live_current';
		$settings['test_secret_key'] = 'sk_test_current';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		WC_Stripe_API::set_secret_key( '' );
		$used_key = null;
		$account  = $this->createMock( WC_Stripe_Account::class );
		$account->expects( $this->once() )->method( 'configure_webhooks' )->with( $mode )
			->willReturnCallback(
				function () use ( &$used_key ) {
					$used_key = WC_Stripe_API::get_secret_key();
					return (object) [
						'url'    => 'https://example.com',
						'secret' => 'whsec_new',
					];
				}
			);
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/webhook' );
		$request->set_param( 'live_mode', 'live' === $mode );
		$controller = new WC_REST_Stripe_Account_Keys_Controller( $account );
		$response   = $controller->configure_webhooks( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'sk_' . $mode . '_current', $used_key );
		$this->assertSame( 'live' === $mode ? 'sk_test_current' : 'sk_live_current', WC_Stripe_API::get_secret_key() );
		WC_Stripe_API::set_secret_key( '' );
	}

	public function provide_webhook_setup_modes() {
		return [
			'live' => [ 'live' ],
			'test' => [ 'test' ],
		];
	}

	/**
	 * A missing requested-mode key must not cause setup to use the active mode's key.
	 *
	 * @dataProvider provide_webhook_setup_modes
	 */
	public function test_webhook_setup_rejects_missing_requested_mode_key( $mode ) {
		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']        = 'live' === $mode ? 'yes' : 'no';
		$settings['secret_key']      = 'live' === $mode ? '' : 'sk_live_current';
		$settings['test_secret_key'] = 'test' === $mode ? '' : 'sk_test_current';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$account = $this->createMock( WC_Stripe_Account::class );
		$account->expects( $this->never() )->method( 'configure_webhooks' );
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/webhook' );
		$request->set_param( 'live_mode', 'live' === $mode );

		$controller = new WC_REST_Stripe_Account_Keys_Controller( $account );
		$response   = $controller->configure_webhooks( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Cannot configure webhooks: no Stripe API secret key is saved for the requested mode.', $response->get_data()['message'] );
	}
}
