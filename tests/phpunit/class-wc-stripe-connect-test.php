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
		WC_Stripe::write_settings_option( [] );
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		delete_option( 'wc_stripe_optimized_checkout_default_on' );
		WC_Stripe::write_settings_option( [] );

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

		$settings = WC_Stripe::read_settings_option();

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

		WC_Stripe::write_settings_option(
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

		$settings = WC_Stripe::read_settings_option();
		$this->assertSame( [], $settings['test_webhook_data'] );
		$this->assertSame( '', $settings['test_webhook_secret'] );
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
