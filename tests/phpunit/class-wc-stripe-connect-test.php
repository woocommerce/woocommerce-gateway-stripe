<?php

/**
 * Tests for the WC_Stripe_Connect class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Connect
 */
class WC_Stripe_Connect_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * @var WC_Stripe_Connect
	 */
	private $connect;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

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
		delete_option( 'wc_stripe_optimized_checkout_default_on' );
		delete_option( 'wc_stripe_oauth_failed_attempts' );
		WC_Stripe_Helper::update_main_stripe_settings( [] );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wc_stripe_refresh_connection', [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );
		}

		parent::tear_down();
	}

	/**
	 * Asserts that the OCS install marker is consumed during Connect OAuth: when the
	 * marker is present and the connection type is 'connect', Optimized Checkout is
	 * defaulted to 'yes' and Adaptive Pricing is defaulted to 'yes' unless the connected
	 * account is India-based, where Adaptive Pricing is not supported. The marker is
	 * always cleaned up.
	 *
	 * @param bool   $marker_set       Whether the OCS install marker is set before save.
	 * @param string $type             Connection type ('connect' or 'app').
	 * @param string $account_country  Country of the connected Stripe account.
	 * @param string $expected_oc      Expected value of optimized_checkout_element after save.
	 * @param string $expected_ap      Expected value of adaptive_pricing after save.
	 *
	 * @dataProvider provide_save_stripe_keys_defaults_ocs_for_new_installs
	 */
	public function test_save_stripe_keys_defaults_ocs_and_adaptive_pricing_for_new_installs( bool $marker_set, string $type, string $account_country, string $expected_oc, string $expected_ap ): void {
		if ( $marker_set ) {
			update_option( 'wc_stripe_optimized_checkout_default_on', 'yes' );
		} else {
			delete_option( 'wc_stripe_optimized_checkout_default_on' );
		}

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache', 'is_webhook_enabled' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => $account_country ] );
		$account->method( 'maybe_decommission_webhook' )->willReturn( false );
		$account->method( 'is_webhook_enabled' )->willReturn( true );
		WC_Stripe::get_instance()->account = $account;

		$result                 = new stdClass();
		$result->publishableKey = 'pk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey      = 'sk_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( 'app' === $type ) {
			$result->refreshToken = 'rt_test_123'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$method->invoke( $this->connect, $result, $type, 'test' );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$this->assertSame( $expected_oc, $settings['optimized_checkout_element'] ?? '' );
		$this->assertSame( $expected_ap, $settings['adaptive_pricing'] ?? '' );

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
	 * Asserts that save_stripe_keys() decommissions the webhook configured on the
	 * previously connected account before saving the new keys, clearing the stored
	 * webhook data/secret for the connected mode.
	 */
	public function test_save_stripe_keys_decommissions_previous_webhook_before_saving() {
		$previous_webhook_data = [
			'id'     => 'wh_old',
			'url'    => 'https://old.example.com',
			'secret' => 'sk_test_old_account',
		];

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

		$account = $this->mock_stripe_account();
		$account->expects( $this->once() )
			->method( 'maybe_decommission_webhook' )
			->with( $previous_webhook_data, 'sk_test_123' )
			->willReturn( true );

		$this->invoke_save_stripe_keys( 'pk_test_123', 'sk_test_123' );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [], $settings['test_webhook_data'] );
		$this->assertSame( '', $settings['test_webhook_secret'] );
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
	 * Builds a WC_Stripe_Account mock with the methods save_stripe_keys() relies on, and
	 * registers it on the plugin instance.
	 *
	 * @return WC_Stripe_Account&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_stripe_account() {
		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data', 'maybe_decommission_webhook', 'configure_webhooks', 'clear_cache' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );

		WC_Stripe::get_instance()->account = $account;

		return $account;
	}

	/**
	 * Invokes the private save_stripe_keys() with a minimal OAuth result for test mode.
	 *
	 * @param string $publishable_key The publishable key to save.
	 * @param string $secret_key      The secret key to save.
	 */
	private function invoke_save_stripe_keys( $publishable_key, $secret_key ) {
		$result                 = new stdClass();
		$result->publishableKey = $publishable_key; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result->secretKey      = $secret_key; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$method = new ReflectionMethod( WC_Stripe_Connect::class, 'save_stripe_keys' );
		$method->setAccessible( true );
		$method->invoke( $this->connect, $result, 'connect', 'test' );
	}
}
