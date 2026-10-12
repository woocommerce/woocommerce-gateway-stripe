<?php

/**
 * These tests make assertions against class WC_REST_Stripe_Account_Keys_Controller.
 *
 * @package WooCommerce/Stripe/WC_REST_Stripe_Account_Keys_Controller
 *
 * WC_REST_Stripe_Account_Keys_Controller unit tests.
 */
class WC_REST_Stripe_Account_Keys_Controller_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	use WC_Stripe_Tracks_Capture_Trait;

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

		$controller = new WC_REST_Stripe_Account_Keys_Controller( $mock_account );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_param( 'secret_key', 'sk_live_new-12345' );

		$controller->set_account_keys( $request );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [], $settings['webhook_data'] );
		$this->assertSame( '', $settings['webhook_secret'] );
	}

	/**
	 * Disconnecting must report the account that was connected. The ID has to be read before the
	 * emptied keys are saved, because afterwards the store no longer resolves to any account.
	 */
	public function test_disconnecting_records_track_event_with_previously_connected_account_id() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'publishable_key'      => 'pk_live_123',
				'secret_key'           => 'sk_live_123',
				'test_publishable_key' => '',
				'test_secret_key'      => '',
				'testmode'             => 'no',
			]
		);
		$controller = new WC_REST_Stripe_Account_Keys_Controller( $this->mock_account_resolving_by_saved_keys() );
		$this->start_capturing_tracks_events();

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		foreach ( [ 'publishable_key', 'secret_key', 'test_publishable_key', 'test_secret_key' ] as $key ) {
			$request->set_param( $key, '' );
		}
		$response = $controller->set_account_keys( $request );

		$this->stop_capturing_tracks_events();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( WC_Stripe_Helper::is_connected( 'live' ) );

		$events = $this->get_captured_tracks_event_properties( 'wcadmin_wcstripe_stripe_disconnected' );
		$this->assertCount( 1, $events );
		$event_properties = $events[0];
		$this->assertArrayHasKey( 'stripe_account_id', $event_properties );
		$this->assertSame( 'acct_live_123', $event_properties['stripe_account_id'] );
		$this->assertFalse( $event_properties['is_test_mode'] );
	}

	/**
	 * Replacing keys must report the account the new keys belong to, for the mode whose keys
	 * changed rather than the active mode, with one event per changed mode.
	 *
	 * @param string $testmode        Active mode setting before the request, 'yes' or 'no'.
	 * @param array  $request_params  Key fields submitted.
	 * @param array  $expected_events Expected [ is_test_mode, stripe_account_id ] pairs, oldest first.
	 *
	 * @dataProvider provide_keys_updated_track_event_scenarios
	 */
	public function test_updating_keys_records_track_event_with_newly_connected_account_id( string $testmode, array $request_params, array $expected_events ) {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'publishable_key'      => 'pk_live_123',
				'secret_key'           => 'sk_live_123',
				'test_publishable_key' => 'pk_test_123',
				'test_secret_key'      => 'sk_test_123',
				'testmode'             => $testmode,
			]
		);
		$controller = new WC_REST_Stripe_Account_Keys_Controller( $this->mock_account_resolving_by_saved_keys() );
		$this->start_capturing_tracks_events();

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		foreach ( $request_params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = $controller->set_account_keys( $request );

		$this->stop_capturing_tracks_events();

		$this->assertSame( 200, $response->get_status() );

		$events = $this->get_captured_tracks_event_properties( 'wcadmin_wcstripe_stripe_keys_updated' );
		$this->assertCount( count( $expected_events ), $events );
		foreach ( $expected_events as $i => list( $expected_is_test_mode, $expected_account_id ) ) {
			$this->assertSame( $expected_is_test_mode, $events[ $i ]['is_test_mode'] );
			$this->assertSame( $expected_account_id, $events[ $i ]['stripe_account_id'] );
		}
	}

	/**
	 * @return array
	 */
	public function provide_keys_updated_track_event_scenarios(): array {
		return [
			'live keys replaced in live mode'              => [
				'no',
				[
					'publishable_key' => 'pk_live_456',
					'secret_key'      => 'sk_live_456',
				],
				[ [ false, 'acct_live_456' ] ],
			],
			'test keys replaced while live mode is active' => [
				'no',
				[
					'test_publishable_key' => 'pk_test_456',
					'test_secret_key'      => 'sk_test_456',
				],
				[ [ true, 'acct_test_456' ] ],
			],
			'both modes replaced in test mode'             => [
				'yes',
				[
					'publishable_key'      => 'pk_live_456',
					'secret_key'           => 'sk_live_456',
					'test_publishable_key' => 'pk_test_456',
					'test_secret_key'      => 'sk_test_456',
				],
				[ [ false, 'acct_live_456' ], [ true, 'acct_test_456' ] ],
			],
			'only a webhook secret submitted in test mode' => [
				'yes',
				[ 'test_webhook_secret' => 'whsec_test_456' ],
				[ [ true, 'acct_test_123' ] ],
			],
		];
	}

	/**
	 * Account mock that, like the real service, resolves nothing once the keys for the mode are
	 * gone, and resolves 'acct_live_123' for saved secret key 'sk_live_123'.
	 *
	 * @return WC_Stripe_Account&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_account_resolving_by_saved_keys() {
		$mock_account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account->method( 'get_cached_account_data' )->willReturnCallback(
			function ( $mode = null ) {
				if ( ! WC_Stripe_Helper::is_connected( $mode ) ) {
					return [];
				}

				$settings   = WC_Stripe_Helper::get_stripe_settings();
				$mode       = $mode ?? ( WC_Stripe_Mode::is_test() ? 'test' : 'live' );
				$secret_key = 'test' === $mode ? $settings['test_secret_key'] : $settings['secret_key'];

				return [ 'id' => str_replace( 'sk_', 'acct_', $secret_key ) ];
			}
		);

		return $mock_account;
	}
}
