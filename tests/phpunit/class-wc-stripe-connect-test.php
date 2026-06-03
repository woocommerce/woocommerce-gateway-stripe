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
	 * marker is present and the connection type is 'connect', both Optimized Checkout
	 * and Adaptive Pricing are defaulted to 'yes'. The marker is always cleaned up.
	 *
	 * @param bool   $marker_set       Whether the OCS install marker is set before save.
	 * @param string $type             Connection type ('connect' or 'app').
	 * @param string $expected_oc      Expected value of optimized_checkout_element after save.
	 * @param string $expected_ap      Expected value of adaptive_pricing after save.
	 *
	 * @dataProvider provide_save_stripe_keys_defaults_ocs_for_new_installs
	 */
	public function test_save_stripe_keys_defaults_ocs_and_adaptive_pricing_for_new_installs( bool $marker_set, string $type, string $expected_oc, string $expected_ap ): void {
		if ( $marker_set ) {
			update_option( 'wc_stripe_optimized_checkout_default_on', 'yes' );
		} else {
			delete_option( 'wc_stripe_optimized_checkout_default_on' );
		}

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
				'marker_set'  => true,
				'type'        => 'connect',
				'expected_oc' => 'yes',
				'expected_ap' => 'yes',
			],
			'marker set + app type leaves both unset'                          => [
				'marker_set'  => true,
				'type'        => 'app',
				'expected_oc' => '',
				'expected_ap' => '',
			],
			'no marker + connect type leaves both unset'                       => [
				'marker_set'  => false,
				'type'        => 'connect',
				'expected_oc' => '',
				'expected_ap' => '',
			],
		];
	}
}
