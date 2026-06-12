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
		WC_Stripe_Helper::update_main_stripe_settings( [] );

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

		$this->set_stripe_account_data( [ 'country' => $account_country ] );

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
	 * redirected back to the settings page with an explicit error marker rather
	 * than the failure being silently swallowed by `admin_init`.
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
}
