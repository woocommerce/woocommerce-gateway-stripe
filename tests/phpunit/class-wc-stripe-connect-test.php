<?php

/**
 * Tests for the WC_Stripe_Connect class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Connect
 */
class WC_Stripe_Connect_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	use WC_Stripe_Tracks_Capture_Trait;

	/**
	 * @var WC_Stripe_Connect
	 */
	private $connect;

	/**
	 * REQUEST_URI as the test bootstrap left it, restored in tear_down().
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Snapshot restored verbatim in tear_down(); unslashing or sanitizing here would restore a different value than the bootstrap set.
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;

		// The settings-update filter merges defaults when saving settings, which would
		// inject `optimized_checkout_element` / `adaptive_pricing` defaults and obscure
		// the Connect consumer's explicit writes.
		remove_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ] );

		$api_mock      = $this->getMockBuilder( WC_Stripe_Connect_API::class )->disableOriginalConstructor()->getMock();
		$this->connect = new WC_Stripe_Connect( $api_mock );

		delete_option( 'wc_stripe_optimized_checkout_default_on' );
		WC_Stripe_Helper::update_main_stripe_settings( [] );
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		// PHPUnit runs with backupGlobals disabled, so a REQUEST_URI left pointing at
		// wp-admin leaks into every later test in the process: wp_validate_redirect()
		// resolves schemeless relative redirects against dirname( REQUEST_URI ).
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		delete_option( 'wc_stripe_optimized_checkout_default_on' );
		delete_option( 'wc_stripe_oauth_failed_attempts' );
		WC_Stripe_Helper::update_main_stripe_settings( [] );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wc_stripe_refresh_connection', [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );
		}

		parent::tear_down();
	}

	/**
	 * Installation persists settings before OAuth. First-connection defaults must
	 * still apply without overwriting saved preferences or bypassing AP eligibility.
	 *
	 * @param bool   $marker_set       Whether the OCS install marker is set before save.
	 * @param string $type             Connection type ('connect' or 'app').
	 * @param string $account_country  Country of the connected Stripe account.
	 * @param string $expected_oc      Expected value of optimized_checkout_element after save.
	 * @param string $expected_ap      Expected value of adaptive_pricing after save.
	 * @param array  $preferences      Preferences saved before the first connection.
	 * @param bool   $webhook_enabled  Whether the configured webhook is enabled.
	 *
	 * @dataProvider provide_save_stripe_keys_defaults_ocs_for_new_installs
	 */
	public function test_save_stripe_keys_defaults_ocs_and_adaptive_pricing_for_new_installs( bool $marker_set, string $type, string $account_country, string $expected_oc, string $expected_ap, array $preferences = [], bool $webhook_enabled = true ): void {
		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache', 'is_webhook_enabled', 'maybe_reconfigure_webhooks_on_update' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => $account_country ] );
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		$account->method( 'is_webhook_enabled' )->willReturn( $webhook_enabled );
		WC_Stripe::get_instance()->account = $account;

		$result                 = new stdClass();
		$result->publishableKey = 'pk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey      = 'sk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( 'app' === $type ) {
			$result->refreshToken = 'rt_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$stripe = WC_Stripe::get_instance();
		add_filter( 'pre_update_option_woocommerce_stripe_settings', [ $stripe, 'gateway_settings_update' ], 10, 2 );
		try {
			delete_option( WC_Stripe::SETTINGS_OPTION_NAME );
			delete_option( 'wc_stripe_version' );
			update_option( 'active_plugins', [ plugin_basename( WC_STRIPE_MAIN_FILE ) ] );
			$stripe->install();
			$installed_settings = $stripe->get_settings();
			$this->assertSame( 'yes', $installed_settings['upe_checkout_experience_enabled'] );
			$this->assertSame( 'yes', get_option( 'wc_stripe_optimized_checkout_default_on' ) );
			$stripe->update_settings( array_merge( $installed_settings, $preferences ) );
			if ( ! $marker_set ) {
				delete_option( 'wc_stripe_optimized_checkout_default_on' );
			}
			$method->invoke( $this->connect, $result, $type, 'test' );
		} finally {
			remove_filter( 'pre_update_option_woocommerce_stripe_settings', [ $stripe, 'gateway_settings_update' ] );
		}

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertSame( $expected_oc, $settings['optimized_checkout_element'] ?? '' );
		$this->assertSame( $expected_ap, $settings['adaptive_pricing'] ?? '' );
		$this->assertSame( [ 'card', 'link' ], $settings['upe_checkout_experience_accepted_payments'] );

		// Marker is always consumed, even when not applied.
		$this->assertEquals( false, get_option( 'wc_stripe_optimized_checkout_default_on' ) );
	}

	/**
	 * @return array
	 */
	public function provide_save_stripe_keys_defaults_ocs_for_new_installs(): array {
		return [
			'marker set + connect type defaults both OCS and Adaptive Pricing' => [
				'marker_set'      => true,
				'type'            => 'connect',
				'account_country' => 'US',
				'expected_oc'     => 'yes',
				'expected_ap'     => 'yes',
			],
			'marker set + connect type + India account defaults OCS only'      => [
				'marker_set'      => true,
				'type'            => 'connect',
				'account_country' => 'IN',
				'expected_oc'     => 'yes',
				'expected_ap'     => '',
			],
			'marker set + app type leaves both unset'                          => [
				'marker_set'      => true,
				'type'            => 'app',
				'account_country' => 'US',
				'expected_oc'     => '',
				'expected_ap'     => '',
			],
			'no marker + connect type leaves both unset'                       => [
				'marker_set'      => false,
				'type'            => 'connect',
				'account_country' => 'US',
				'expected_oc'     => '',
				'expected_ap'     => '',
			],
			'explicit AP off preserves OCS onboarding default'                 => [
				true,
				'connect',
				'US',
				'yes',
				'no',
				[ 'adaptive_pricing' => 'no' ],
			],
			'explicit OCS off and AP on are preserved'                         => [
				true,
				'connect',
				'US',
				'no',
				'yes',
				[
					'optimized_checkout_element' => 'no',
					'adaptive_pricing'           => 'yes',
				],
			],
			'explicit OCS and AP off are preserved'                            => [
				true,
				'connect',
				'US',
				'no',
				'no',
				[
					'optimized_checkout_element' => 'no',
					'adaptive_pricing'           => 'no',
				],
			],
			'disabled webhook prevents AP default'                             => [
				true,
				'connect',
				'US',
				'yes',
				'',
				[],
				false,
			],
			'manual capture prevents AP default'                               => [
				true,
				'connect',
				'US',
				'yes',
				'',
				[ 'capture' => 'no' ],
			],
		];
	}

	/**
	 * @dataProvider provide_reconnect_preferences
	 * @param array  $preferences   Existing saved preferences.
	 * @param string $previous_mode Mode with previously configured credentials.
	 * @param array  $account_data  Account data, empty when credentials have expired.
	 */
	public function test_reconnect_preserves_existing_preferences( array $preferences, string $previous_mode = 'test', array $account_data = [ 'country' => 'US' ] ): void {
		if ( ! empty( $account_data ) ) {
			$account_data['capabilities'] = [ 'card_payments' => 'active' ];
		}
		$this->mock_stripe_account( $account_data );
		$this->mock_payment_method_configurations( $preferences['upe_checkout_experience_accepted_payments'] ?? [ 'card' ] );
		$prefix   = 'test' === $previous_mode ? 'test_' : '';
		$settings = array_merge(
			[
				$prefix . 'publishable_key' => 'pk_' . $previous_mode . '_previous',
				$prefix . 'secret_key'      => 'sk_' . $previous_mode . '_previous',
				'pmc_enabled'               => 'yes',
			],
			$preferences
		);
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		update_option( 'wc_stripe_optimized_checkout_default_on', 'yes' );
		add_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ], 10, 2 );

		try {
			for ( $attempt = 0; $attempt < 2; $attempt++ ) {
				$this->invoke_save_stripe_keys( 'pk_test_previous', 'sk_test_previous' );
				$snapshots = [ 'OAuth' => WC_Stripe_Helper::get_stripe_settings() ];
				// A settings-page reload constructs the gateway from persisted options.
				$controller = new WC_REST_Stripe_Settings_Controller( new WC_Stripe_UPE_Payment_Gateway() );
				$response   = $controller->get_settings();
				$this->assertSame( 200, $response->get_status() );
				$snapshots['GET'] = WC_Stripe_Helper::get_stripe_settings();
				foreach ( $snapshots as $stage => $saved ) {
					foreach ( [ 'upe_checkout_experience_accepted_payments', 'express_checkout_button_locations', 'express_checkout_button_type', 'express_checkout_button_theme', 'express_checkout_button_size', 'optimized_checkout_element', 'adaptive_pricing' ] as $key ) {
						if ( array_key_exists( $key, $preferences ) ) {
							$this->assertSame( $preferences[ $key ], $saved[ $key ], $stage . ': ' . $key );
						} else {
							$this->assertArrayNotHasKey( $key, $saved, $stage . ': ' . $key );
						}
					}
				}
				foreach ( [ 'express_checkout_button_locations', 'express_checkout_button_type', 'express_checkout_button_theme', 'express_checkout_button_size' ] as $key ) {
					if ( array_key_exists( $key, $preferences ) ) {
						$this->assertSame( $preferences[ $key ], $response->get_data()[ $key ], $key );
					}
				}
				$this->assertSame( 'yes' === ( $preferences['optimized_checkout_element'] ?? '' ), $response->get_data()['is_oc_enabled'] );
				$this->assertSame( 'yes' === ( $preferences['adaptive_pricing'] ?? '' ), $response->get_data()['is_ap_enabled'] );
				$this->assertFalse( get_option( 'wc_stripe_optimized_checkout_default_on' ) );
			}
		} finally {
			remove_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ] );
		}
	}

	public function provide_reconnect_preferences(): array {
		return [
			'missing settings stay missing'            => [ [ 'upe_checkout_experience_enabled' => 'yes' ] ],
			'previous live connection'                 => [ [ 'upe_checkout_experience_enabled' => 'yes' ], 'live' ],
			'expired credentials without account data' => [
				[
					'upe_checkout_experience_enabled' => 'yes',
					'test_secret_key'                 => 'sk_test_expired',
					'test_connection_type'            => 'app',
					'test_refresh_token'              => 'rt_test_expired',
				],
				'test',
				[],
			],
			'explicit empty and off'                   => [
				[
					'upe_checkout_experience_accepted_payments' => [],
					'express_checkout_button_locations'         => [],
					'optimized_checkout_element'                => 'no',
					'adaptive_pricing'                          => 'no',
				],
			],
			'custom selections'                        => [
				[
					'upe_checkout_experience_accepted_payments' => [ 'card', 'klarna' ],
					'express_checkout_button_locations'         => [ 'checkout' ],
					'express_checkout_button_type'              => 'donate',
					'express_checkout_button_theme'             => 'light-outline',
					'express_checkout_button_size'              => 'large',
					'optimized_checkout_element'                => 'no',
					'adaptive_pricing'                          => 'no',
				],
			],
		];
	}

	/**
	 * When the OAuth return URL carries an invalid nonce, the merchant must be
	 * redirected back to the settings page with an explicit error marker.
	 */
	public function test_maybe_handle_redirect_redirects_with_error_on_invalid_nonce(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		set_current_screen( 'dashboard' ); // Makes is_admin() return true.

		$_GET['wcs_stripe_code']  = 'ac_123';
		$_GET['wcs_stripe_state'] = 'state_123';
		$_GET['wcs_stripe_mode']  = 'live';
		$_GET['_wpnonce']         = 'invalid-nonce';
		$_SERVER['REQUEST_URI']   = '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&wcs_stripe_code=ac_123&wcs_stripe_state=state_123&wcs_stripe_mode=live&_wpnonce=invalid-nonce';

		$redirected_to      = '';
		$wp_redirect_filter = function ( string $url ) use ( &$redirected_to ) {
			$redirected_to = $url;

			// Throw to prevent exit() from being called after wp_safe_redirect().
			throw new \Exception();
		};
		add_filter( 'wp_redirect', $wp_redirect_filter );

		try {
			$this->connect->maybe_handle_redirect();
		} catch ( \Exception $e ) {
			unset( $e );
		}

		remove_filter( 'wp_redirect', $wp_redirect_filter );

		$this->assertStringContainsString( 'wc_stripe_connect_error=expired_nonce', $redirected_to );
		$this->assertStringNotContainsString( 'wcs_stripe_code', $redirected_to );
		$this->assertStringNotContainsString( 'wcs_stripe_state', $redirected_to );

		unset(
			$_GET['wcs_stripe_code'],
			$_GET['wcs_stripe_state'],
			$_GET['wcs_stripe_mode'],
			$_GET['_wpnonce']
		);
	}

	/**
	 * The OAuth return handler must report the error code on failure and, on success or on a failure
	 * that left the store connected, the account the store ends up with, along with the mode and
	 * connection type of the request.
	 *
	 * @param bool        $exchange_fails      WCC rejects the OAuth code, so no keys are saved.
	 * @param bool        $webhook_setup_fails Keys are saved, but configuring the webhook throws.
	 * @param string      $expected_event      Expected prefixed Tracks event name.
	 * @param string|null $expected_account_id Expected account_id property, or null when it must be absent.
	 * @param string|null $expected_error_code Expected error_code property, or null when it must be absent.
	 *
	 * @dataProvider provide_oauth_return_track_event_scenarios
	 */
	public function test_maybe_handle_redirect_records_account_on_tracks_event( bool $exchange_fails, bool $webhook_setup_fails, string $expected_event, ?string $expected_account_id, ?string $expected_error_code ): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		set_current_screen( 'dashboard' ); // Makes is_admin() return true.
		$wc_stripe       = WC_Stripe::get_instance();
		$initial_account = $wc_stripe->account;
		$this->mock_account_resolving_by_saved_keys( $webhook_setup_fails );
		$exchange_result = $exchange_fails ? new WP_Error( 'oauth_failed', 'Exchange failed' ) : $this->get_mock_oauth_test_keys();

		$this->start_capturing_tracks_events();
		$redirected_to = $this->handle_oauth_return( $exchange_result );
		$this->stop_capturing_tracks_events();

		$wc_stripe->account = $initial_account;

		// A redirect proves the real return path ran to completion before the event was captured.
		$this->assertNotSame( '', $redirected_to );
		if ( $exchange_fails || $webhook_setup_fails ) {
			$this->assertStringNotContainsString( 'wc_stripe_connected=true', $redirected_to );
		} else {
			$this->assertStringContainsString( 'wc_stripe_connected=true', $redirected_to );
		}

		$events = $this->get_captured_tracks_event_properties( $expected_event );
		$this->assertCount( 1, $events );
		$event_properties = $events[0];
		$this->assertSame( $expected_account_id, $event_properties['account_id'] ?? null );
		$this->assertSame( $expected_error_code, $event_properties['error_code'] ?? null );
		$this->assertTrue( $event_properties['is_test_mode'] );
		$this->assertSame( 'connect', $event_properties['connection_type'] );
		$this->assertSame( WC_STRIPE_VERSION, $event_properties['stripe_version'] );
	}

	/**
	 * With tracking off, the OAuth return must not look the account up. A failed key exchange
	 * is used because nothing else on that path touches the account service.
	 */
	public function test_maybe_handle_redirect_does_not_resolve_the_account_when_tracking_is_disabled(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		set_current_screen( 'dashboard' ); // Makes is_admin() return true.
		delete_option( 'woocommerce_allow_tracking' );

		$wc_stripe       = WC_Stripe::get_instance();
		$initial_account = $wc_stripe->account;

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data' ] )
			->getMock();
		$account->expects( $this->never() )->method( 'get_cached_account_data' );
		$wc_stripe->account = $account;

		$redirected_to = $this->handle_oauth_return( new WP_Error( 'oauth_failed', 'Exchange failed' ) );

		$wc_stripe->account = $initial_account;

		$this->assertNotSame( '', $redirected_to );
	}

	/**
	 * @return array
	 */
	public function provide_oauth_return_track_event_scenarios(): array {
		return [
			'connected'                           => [ false, false, 'wcadmin_wcstripe_stripe_connected', 'acct_123', null ],
			'exchange failed, nothing connected'  => [ true, false, 'wcadmin_wcstripe_stripe_connect_error', null, 'oauth_failed' ],
			'keys saved but webhook setup failed' => [ false, true, 'wcadmin_wcstripe_stripe_connect_error', 'acct_123', 'wc_stripe_webhook_error' ],
		];
	}

	/**
	 * Installs an account mock that, like the real service, resolves an account only while keys
	 * for the mode are saved, so the OAuth return can be observed taking the store from
	 * disconnected to connected.
	 *
	 * @param bool $webhook_setup_fails Whether configure_webhooks() should throw.
	 */
	private function mock_account_resolving_by_saved_keys( bool $webhook_setup_fails ): void {
		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturnCallback(
			function ( $mode = null ) {
				return WC_Stripe_Helper::is_connected( $mode ) ? [
					'id'      => 'acct_123',
					'country' => 'US',
				] : [];
			}
		);
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		if ( $webhook_setup_fails ) {
			$account->method( 'configure_webhooks' )->willThrowException( new Exception( 'Webhook setup failed' ) );
		}

		WC_Stripe::get_instance()->account = $account;
	}

	/**
	 * @return stdClass A minimal successful WCC key-exchange result for test mode.
	 */
	private function get_mock_oauth_test_keys(): stdClass {
		$keys                 = new stdClass();
		$keys->publishableKey = 'pk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$keys->secretKey      = 'sk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $keys;
	}

	/**
	 * Runs the OAuth return handler for a test-mode connection whose WCC key exchange yields
	 * $exchange_result, and returns the URL the handler redirected to.
	 *
	 * @param stdClass|WP_Error $exchange_result What get_stripe_oauth_keys() returns.
	 * @return string
	 */
	private function handle_oauth_return( $exchange_result ): string {
		$api_mock = $this->getMockBuilder( WC_Stripe_Connect_API::class )->disableOriginalConstructor()->getMock();
		$api_mock->method( 'get_stripe_oauth_keys' )->willReturn( $exchange_result );
		$connect = new WC_Stripe_Connect( $api_mock );

		WC_Stripe_Database_Cache::set_with_mode( 'oauth_connect_state', 'state_123', HOUR_IN_SECONDS, 'test' );
		$_GET['wcs_stripe_code']  = 'ac_123';
		$_GET['wcs_stripe_state'] = 'state_123';
		$_GET['wcs_stripe_mode']  = 'test';
		$_GET['_wpnonce']         = wp_create_nonce( 'wcs_stripe_connected' );
		$_SERVER['REQUEST_URI']   = '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe';

		$redirected_to      = '';
		$wp_redirect_filter = function ( string $url ) use ( &$redirected_to ) {
			$redirected_to = $url;

			// Throw to prevent exit() from being called after wp_safe_redirect().
			throw new \Exception();
		};
		add_filter( 'wp_redirect', $wp_redirect_filter );

		try {
			$connect->maybe_handle_redirect();
		} catch ( \Exception $e ) {
			unset( $e );
		}

		remove_filter( 'wp_redirect', $wp_redirect_filter );
		unset( $_GET['wcs_stripe_code'], $_GET['wcs_stripe_state'], $_GET['wcs_stripe_mode'], $_GET['_wpnonce'] );

		return $redirected_to;
	}

	/**
	 * Asserts that save_stripe_keys() decommissions the webhook configured on the
	 * previously connected account before saving the new keys, clearing the stored
	 * webhook data/secret for the connected mode.
	 *
	 * @dataProvider provide_legacy_webhook_secrets
	 */
	public function test_save_stripe_keys_decommissions_previous_webhook_before_saving( $legacy_secret, $fails, $expected_secret ) {
		$previous_webhook_data = [
			'id'  => 'wh_old',
			'url' => 'https://old.example.com',
		];
		if ( null !== $legacy_secret ) {
			$previous_webhook_data['secret'] = $legacy_secret;
		}

		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'             => 'yes',
				'test_secret_key'      => 'sk_test_old_account',
				'test_publishable_key' => 'pk_test_old',
				'test_webhook_data'    => $previous_webhook_data,
				'test_webhook_secret'  => 'whsec_old',
				'pmc_enabled'          => 'yes',
			]
		);

		$expected_webhook_data           = $previous_webhook_data;
		$expected_webhook_data['secret'] = $expected_secret;
		$account                         = $this->mock_stripe_account();
		$account->expects( $this->once() )
			->method( 'maybe_decommission_webhook' )
			->with( $expected_webhook_data, 'sk_test_123' )
			->willReturn( ! $fails );
		if ( $fails ) {
			$account->method( 'configure_webhooks' )->willThrowException( new Exception( 'Network error' ) );
		}

		$this->invoke_save_stripe_keys( 'pk_test_123', 'sk_test_123' );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $fails ? $expected_webhook_data : [], $settings['test_webhook_data'] );
		$this->assertSame( $fails ? 'whsec_old' : '', $settings['test_webhook_secret'] );
		$this->assertSame( 'sk_test_123', $settings['test_secret_key'] );
	}

	public function provide_legacy_webhook_secrets() {
		return [
			'without stored key'               => [ null, false, 'sk_test_old_account' ],
			'legacy matching key'              => [ 'sk_test_old_account', false, 'sk_test_old_account' ],
			'legacy different key'             => [ 'sk_test_original_account', false, 'sk_test_original_account' ],
			'failed cleanup and configuration' => [ null, true, 'sk_test_old_account' ],
			'empty stored key'                 => [ '', false, 'sk_test_old_account' ],
			'empty key with failed cleanup'    => [ '', true, 'sk_test_old_account' ],
		];
	}

	/**
	 * A failed cleanup retains the old key for retry, but verbose OAuth logs must mask it.
	 *
	 * @dataProvider provide_webhook_log_modes
	 */
	public function test_failed_webhook_cleanup_redacts_retained_key_in_verbose_log( $mode ) {
		$prefix  = 'test' === $mode ? 'test_' : '';
		$old_key = 'sk_' . $mode . '_old_account';
		$new_key = 'sk_' . $mode . '_new_account';
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'               => 'test' === $mode ? 'yes' : 'no',
				$prefix . 'secret_key'   => $old_key,
				$prefix . 'webhook_data' => [
					'id'     => 'we_old',
					'secret' => '',
				],
				'pmc_enabled'            => 'yes',
			]
		);

		$account = $this->mock_stripe_account();
		$account->expects( $this->once() )->method( 'maybe_decommission_webhook' )
			->with(
				[
					'id'     => 'we_old',
					'secret' => $old_key,
				],
				$new_key
			)
			->willReturn( false );
		$account->method( 'configure_webhooks' )->willThrowException( new Exception( 'Network error' ) );

		$logged_options = [];
		$logger         = $this->createMock( WC_Logger::class );
		$logger->method( 'debug' )->willReturnCallback(
			function ( $message, $context ) use ( &$logged_options ) {
				if ( 'OAuth: Plugin settings updated' === $message ) {
					$logged_options[] = $context['options'];
				}
			}
		);
		$previous_logger          = WC_Stripe_Logger::$logger;
		WC_Stripe_Logger::$logger = $logger;
		add_filter( 'wc_stripe_is_verbose_debug_mode_enabled', '__return_true' );

		try {
			$this->invoke_save_stripe_keys( 'pk_' . $mode . '_new_account', $new_key, $mode );
		} finally {
			remove_filter( 'wc_stripe_is_verbose_debug_mode_enabled', '__return_true' );
			WC_Stripe_Logger::$logger = $previous_logger;
			WC_Stripe_API::set_secret_key( '' );
		}

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $old_key, $settings[ $prefix . 'webhook_data' ]['secret'] );
		$this->assertCount( 1, $logged_options );
		$this->assertSame( WC_Stripe_Connect::redact_string( $old_key ), $logged_options[0][ $prefix . 'webhook_data' ]['secret'] );
		$this->assertStringNotContainsString( $old_key, wp_json_encode( $logged_options[0] ) );
	}

	public function provide_webhook_log_modes() {
		return [
			'live' => [ 'live' ],
			'test' => [ 'test' ],
		];
	}

	/**
	 * Live onboarding must delete the old test webhook with the old test key.
	 *
	 * @dataProvider provide_cleanup_failures
	 */
	public function test_dual_fetch_decommissions_test_webhook_with_previous_key( $fails, $legacy_secret ) {
		$webhook_data = [ 'id' => 'we_old_test' ];
		if ( null !== $legacy_secret ) {
			$webhook_data['secret'] = $legacy_secret;
		}
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'          => 'no',
				'test_secret_key'   => 'sk_test_old',
				'test_webhook_data' => $webhook_data,
			]
		);
		$account = $this->mock_stripe_account();
		$account->expects( $this->once() )->method( 'maybe_decommission_webhook' )
			->with(
				[
					'id'     => 'we_old_test',
					'secret' => 'sk_test_old',
				],
				'sk_test_new'
			)
			->willReturn( ! $fails );
		if ( $fails ) {
			$account->method( 'configure_webhooks' )->willThrowException( new Exception( 'Network error' ) );
		}
		$result = (object) [
			'testPublishableKey' => 'pk_test_new',
			'testSecretKey'      => 'sk_test_new',
		];
		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_oauth_test_keys_alongside_live' );
		$method->setAccessible( true );
		$method->invoke( $this->connect, $result );
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'sk_test_new', $settings['test_secret_key'] );
		$this->assertSame(
			$fails ? [
				'id'     => 'we_old_test',
				'secret' => 'sk_test_old',
			] : [],
			$settings['test_webhook_data']
		);
		$this->assertSame( 'no', $settings['testmode'] );
	}

	public function provide_cleanup_failures() {
		return [
			'success'           => [ false, null ],
			'failure'           => [ true, null ],
			'empty key'         => [ false, '' ],
			'empty key failure' => [ true, '' ],
		];
	}

	/**
	 * On a live + connect onboarding, when the Connect server additionally returns the
	 * account's test keys (testPublishableKey/testSecretKey), save_stripe_keys() must
	 * persist them under the test_ prefix and configure the test webhook WITHOUT flipping
	 * testmode. When those fields are absent (old server / test-fetch failed), only the
	 * live environment is written and no notices are raised.
	 *
	 * @param bool $with_test_keys Whether the OAuth result carries the test key fields.
	 *
	 * @dataProvider provide_dual_fetch_cases
	 */
	public function test_save_stripe_keys_persists_test_keys_alongside_live( bool $with_test_keys ): void {
		$configured_modes  = [];
		$keys_at_configure = [];

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		// Capture the API secret key active at the moment each webhook is configured.
		// configure_webhooks() authenticates with whatever key is set on WC_Stripe_API,
		// so the test webhook must be created with the test key — not the live key that
		// get_secret_key() would otherwise resolve from testmode ('no').
		$account->method( 'configure_webhooks' )->willReturnCallback(
			function ( $mode ) use ( &$configured_modes, &$keys_at_configure ) {
				$configured_modes[]         = $mode;
				$keys_at_configure[ $mode ] = WC_Stripe_API::get_secret_key();
			}
		);
		WC_Stripe::get_instance()->account = $account;

		$result                 = new stdClass();
		$result->publishableKey = 'pk_live_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey      = 'sk_live_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( $with_test_keys ) {
			$result->testPublishableKey = 'pk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$result->testSecretKey      = 'sk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$method->invoke( $this->connect, $result, 'connect', 'live' );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		// Live is always saved; testmode never flips on a live onboarding.
		$this->assertSame( 'sk_live_123', $settings['secret_key'] );
		$this->assertSame( 'no', $settings['testmode'] );
		$this->assertContains( 'live', $configured_modes );

		if ( $with_test_keys ) {
			$this->assertSame( 'sk_test_123', $settings['test_secret_key'] );
			$this->assertSame( 'pk_test_123', $settings['test_publishable_key'] );
			$this->assertSame( 'connect', $settings['test_connection_type'] );
			$this->assertContains( 'test', $configured_modes );
			// The test webhook must be created against the test account, i.e. with the
			// test secret key active — not the live key.
			$this->assertSame( 'sk_test_123', $keys_at_configure['test'] );
		} else {
			// Default gateway config injects test_secret_key with an empty string, so the
			// key exists but must not hold a real test key value.
			$this->assertEmpty( $settings['test_secret_key'] ?? '' );
			$this->assertNotContains( 'test', $configured_modes );
		}
	}

	/**
	 * A dead refresh token must stop the 55-minute refresh loop immediately
	 * and clear the account cache so the merchant is prompted to reconnect, rather than burning
	 * the full 10-attempt retry budget. A transient failure, or a failure from an older Connect Server that forwards no Stripe
	 * error code, must keep rescheduling as before.
	 *
	 * @param object|string $error_data         The `data` payload carried by the WCC WP_Error.
	 * @param bool          $expect_rescheduled  Whether the refresh should be rescheduled.
	 * @param int           $expect_clear_cache  Expected number of account cache clears.
	 *
	 * @dataProvider provide_refresh_connection_terminal_handling
	 */
	public function test_refresh_connection_stops_retrying_on_terminal_error( $error_data, bool $expect_rescheduled, int $expect_clear_cache ): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		// App OAuth live connection so refresh_connection() proceeds past its guard.
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'        => 'no',
				'publishable_key' => 'pk_live_123',
				'secret_key'      => 'sk_live_123',
				'connection_type' => 'app',
				'refresh_token'   => 'rt_live_123',
			]
		);
		delete_option( 'wc_stripe_oauth_failed_attempts' );
		as_unschedule_all_actions( 'wc_stripe_refresh_connection', [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );

		// Mirror WC_Stripe_Connect_API::request(): the Stripe error code (when the Connect
		// Server forwards one) rides in the WP_Error's data payload.
		$error    = new WP_Error( 'wcc_server_error_response', 'Error', $error_data );
		$api_mock = $this->getMockBuilder( WC_Stripe_Connect_API::class )->disableOriginalConstructor()->getMock();
		$api_mock->method( 'refresh_stripe_app_oauth_keys' )->willReturn( $error );

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'clear_cache' ] )
			->getMock();
		$account->expects( $this->exactly( $expect_clear_cache ) )->method( 'clear_cache' );
		WC_Stripe::get_instance()->account = $account;

		$connect = new WC_Stripe_Connect( $api_mock );
		$connect->refresh_connection();

		$this->assertSame(
			$expect_rescheduled,
			(bool) as_has_scheduled_action( 'wc_stripe_refresh_connection', [], WC_Stripe_Action_Scheduler_Service::GROUP_ID )
		);
	}

	/**
	 * @return array
	 */
	public function provide_dual_fetch_cases(): array {
		return [
			'server returns test keys'        => [ true ],
			'server omits test keys (legacy)' => [ false ],
		];
	}

	/**
	 * @return array
	 */
	public function provide_refresh_connection_terminal_handling(): array {
		return [
			'terminal invalid_grant stops retrying and clears cache'  => [
				'error_data'         => (object) [ 'stripe_error_code' => 'invalid_grant' ],
				'expect_rescheduled' => false,
				'expect_clear_cache' => 1,
			],
			'transient failure from old server (no code) reschedules' => [
				'error_data'         => '',
				'expect_rescheduled' => true,
				'expect_clear_cache' => 0,
			],
		];
	}

	/**
	 * Test-key persistence must not be coupled to live-webhook success. When
	 * configure_webhooks('live') throws (e.g. Stripe rejects webhook creation on the
	 * connected account), save_stripe_keys() still surfaces the webhook WP_Error, but
	 * the dual-fetched test keys must already be persisted.
	 */
	public function test_save_stripe_keys_persists_test_keys_even_when_live_webhook_fails(): void {
		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		$account->method( 'configure_webhooks' )->willReturnCallback(
			function ( $mode ) {
				if ( 'live' === $mode ) {
					throw new Exception( 'not permitted to configure webhook endpoints on a connected account' );
				}
			}
		);
		WC_Stripe::get_instance()->account = $account;

		$result                     = new stdClass();
		$result->publishableKey     = 'pk_live_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey          = 'sk_live_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->testPublishableKey = 'pk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->testSecretKey      = 'sk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$return = $method->invoke( $this->connect, $result, 'connect', 'live' );

		// The live-webhook failure is still surfaced to the caller.
		$this->assertInstanceOf( WP_Error::class, $return );

		// ...but the dual-fetched test keys were persisted regardless of it.
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'sk_test_123', $settings['test_secret_key'] );
		$this->assertSame( 'pk_test_123', $settings['test_publishable_key'] );
		$this->assertSame( 'connect', $settings['test_connection_type'] );
	}

	/**
	 * The test webhook setup during dual-fetch is best-effort: when
	 * configure_webhooks('test') throws, the failure is swallowed so it cannot abort the
	 * live onboarding. The live keys, the live webhook, and the dual-fetched test keys
	 * must all still be persisted, and save_stripe_keys() must not surface a WP_Error.
	 */
	public function test_save_stripe_keys_persists_all_keys_when_only_test_webhook_fails(): void {
		$configured_modes = [];

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		// Only the test webhook fails; the live webhook must still be configured.
		$account->method( 'configure_webhooks' )->willReturnCallback(
			function ( $mode ) use ( &$configured_modes ) {
				$configured_modes[] = $mode;
				if ( 'test' === $mode ) {
					throw new Exception( 'not permitted to configure webhook endpoints on a connected account' );
				}
			}
		);
		WC_Stripe::get_instance()->account = $account;

		$result                     = new stdClass();
		$result->publishableKey     = 'pk_live_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey          = 'sk_live_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->testPublishableKey = 'pk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->testSecretKey      = 'sk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$return = $method->invoke( $this->connect, $result, 'connect', 'live' );

		$this->assertSame( [ 'test', 'live' ], $configured_modes );

		// A swallowed test-webhook failure must not surface as an error from the live flow.
		$this->assertNotInstanceOf( WP_Error::class, $return );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		// Live environment is fully saved despite the test-webhook failure.
		$this->assertSame( 'sk_live_123', $settings['secret_key'] );
		$this->assertSame( 'no', $settings['testmode'] );

		// The dual-fetched test keys are persisted; the webhook failure happens after the
		// settings write, so it does not roll them back.
		$this->assertSame( 'sk_test_123', $settings['test_secret_key'] );
		$this->assertSame( 'pk_test_123', $settings['test_publishable_key'] );
		$this->assertSame( 'connect', $settings['test_connection_type'] );
	}

	/**
	 * Ensure that partial key returns are validated correctly.
	 *
	 * @param array  $result_fields OAuth result properties.
	 * @param string $type          Connection type ('connect' or 'app').
	 *
	 * @dataProvider provide_save_stripe_keys_invalid_credentials
	 */
	public function test_save_stripe_keys_returns_invalid_credentials_error( array $result_fields, string $type ): void {
		$wc_stripe       = WC_Stripe::get_instance();
		$initial_account = $wc_stripe->account;

		$account = $this->mock_stripe_account();
		$account->expects( $this->never() )->method( 'configure_webhooks' );

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$return = $method->invoke( $this->connect, (object) $result_fields, $type, 'test' );

		$wc_stripe->account = $initial_account;

		$this->assertInstanceOf( WP_Error::class, $return );
		$this->assertSame( 'invalid_credentials', $return->get_error_code() );
		$this->assertSame( 'Invalid credentials received from WooCommerce Connect server', $return->get_error_message() );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayNotHasKey( 'test_secret_key', $settings );
	}

	/**
	 * Data provider for {@see test_save_stripe_keys_returns_invalid_credentials_error()}.
	 *
	 * @return array
	 */
	public function provide_save_stripe_keys_invalid_credentials(): array {
		return [
			'connect, missing publishable key' => [ [ 'secretKey' => 'sk_test_123' ], 'connect' ],
			'connect, missing secret key'      => [ [ 'publishableKey' => 'pk_test_123' ], 'connect' ],
			'app, missing refresh token'       => [
				[
					'publishableKey' => 'pk_test_123',
					'secretKey'      => 'sk_test_123',
				],
				'app',
			],
		];
	}

	/**
	 * Ensure that webhook configuration failures are logged correctly.
	 *
	 * @param string $type Connection type ('connect' or 'app').
	 * @param string $mode Connection mode ('live' or 'test').
	 *
	 * @dataProvider provide_save_stripe_keys_webhook_failure_cases
	 */
	public function test_save_stripe_keys_logs_webhook_configuration_failure( string $type, string $mode ): void {
		$wc_stripe       = WC_Stripe::get_instance();
		$initial_account = $wc_stripe->account;

		$account = $this->mock_stripe_account();
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		$account->method( 'configure_webhooks' )->willThrowException( new Exception( 'webhook endpoint unreachable' ) );

		$logged_errors = [];
		$mock_logger   = $this->createMock( WC_Logger::class );
		$mock_logger->method( 'error' )->willReturnCallback(
			function ( $message, $context ) use ( &$logged_errors ) {
				$logged_errors[] = [ $message, $context ];
			}
		);

		$original_logger          = WC_Stripe_Logger::$logger;
		WC_Stripe_Logger::$logger = $mock_logger;

		$result                 = new stdClass();
		$result->publishableKey = 'pk_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey      = 'sk_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->refreshToken   = 'rt_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		try {
			$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
			$method->setAccessible( true );
			$return = $method->invoke( $this->connect, $result, $type, $mode );
		} finally {
			WC_Stripe_Logger::$logger = $original_logger;
			$wc_stripe->account       = $initial_account;
		}

		$this->assertInstanceOf( WP_Error::class, $return );
		$this->assertSame( 'wc_stripe_webhook_error', $return->get_error_code() );
		$this->assertSame( 'webhook endpoint unreachable', $return->get_error_message() );

		$webhook_errors = array_values(
			array_filter(
				$logged_errors,
				function ( $entry ) {
					return 'OAuth: Failed to configure webhooks after saving keys' === $entry[0];
				}
			)
		);
		$this->assertCount( 1, $webhook_errors );

		$context = $webhook_errors[0][1];
		$this->assertSame( 'webhook endpoint unreachable', $context['exception_message'] );
		$this->assertSame( $mode, $context['mode'] );
		$this->assertSame( $type, $context['connection_type'] );
	}

	/**
	 * Data provider for {@see test_save_stripe_keys_logs_webhook_configuration_failure()}.
	 *
	 * @return array
	 */
	public function provide_save_stripe_keys_webhook_failure_cases(): array {
		return [
			'connect, live' => [ 'connect', 'live' ],
			'connect, test' => [ 'connect', 'test' ],
			'app, live'     => [ 'app', 'live' ],
			'app, test'     => [ 'app', 'test' ],
		];
	}

	/**
	 * Builds a WC_Stripe_Account mock with the methods save_stripe_keys() relies on, and
	 * registers it on the plugin instance.
	 *
	 * @param array $account_data Cached account data.
	 * @return WC_Stripe_Account&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_stripe_account( array $account_data = [ 'country' => 'US' ] ) {
		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( $account_data );

		WC_Stripe::get_instance()->account = $account;

		return $account;
	}

	/**
	 * Invokes the private save_stripe_keys() with a minimal OAuth result.
	 *
	 * @param string $publishable_key The publishable key to save.
	 * @param string $secret_key      The secret key to save.
	 * @param string $mode            The mode to connect to.
	 */
	private function invoke_save_stripe_keys( $publishable_key, $secret_key, $mode = 'test' ) {
		$result                 = new stdClass();
		$result->publishableKey = $publishable_key; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey      = $secret_key; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$method->invoke( $this->connect, $result, 'connect', $mode );
	}
}
