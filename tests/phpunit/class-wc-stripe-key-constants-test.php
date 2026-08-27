<?php

/**
 * These tests make assertions against the class WC_Stripe_Key_Constants.
 *
 * Class WC_Stripe_Key_Constants_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Key_Constants
 */
class WC_Stripe_Key_Constants_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * The system under test, with the constant-reading seam faked.
	 *
	 * @var WC_Stripe_Key_Constants_Fake
	 */
	private $key_constants;

	/**
	 * Pre-test setup: register a fake with no constants configured.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->key_constants = new WC_Stripe_Key_Constants_Fake();
		$this->key_constants->register_filters();

		update_option(
			WC_Stripe::SETTINGS_OPTION_NAME,
			[
				'enabled'         => 'yes',
				'secret_key'      => 'sk_live_stored',
				'test_secret_key' => 'sk_test_stored',
			]
		);
	}

	/**
	 * Post-test cleanup: drop the fake's filters so they cannot leak into other tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->key_constants->unregister_filters();
		delete_option( WC_Stripe::SETTINGS_OPTION_NAME );

		parent::tear_down();
	}

	/**
	 * With no constants defined, reads return the stored keys untouched.
	 *
	 * @return void
	 */
	public function test_reads_are_untouched_without_constants(): void {
		$settings = WC_Stripe::get_instance()->get_settings();

		$this->assertSame( 'sk_live_stored', $settings['secret_key'] );
		$this->assertSame( 'sk_test_stored', $settings['test_secret_key'] );
	}

	/**
	 * A defined constant overrides the stored key for its mode on every read,
	 * through the plugin accessor and through a raw get_option() alike.
	 *
	 * @dataProvider provide_constant_override_scenarios
	 *
	 * @param array  $constants       The constants the fake reports as defined.
	 * @param string $expected_live   Expected effective live secret key.
	 * @param string $expected_test   Expected effective test secret key.
	 * @return void
	 */
	public function test_constants_override_stored_keys_on_read( array $constants, string $expected_live, string $expected_test ): void {
		$this->key_constants->constants = $constants;

		$settings = WC_Stripe::get_instance()->get_settings();
		$this->assertSame( $expected_live, $settings['secret_key'] );
		$this->assertSame( $expected_test, $settings['test_secret_key'] );

		$raw_read = get_option( WC_Stripe::SETTINGS_OPTION_NAME );
		$this->assertSame( $expected_live, $raw_read['secret_key'] );
		$this->assertSame( $expected_test, $raw_read['test_secret_key'] );
	}

	/**
	 * Scenarios for test_constants_override_stored_keys_on_read().
	 *
	 * @return array
	 */
	public function provide_constant_override_scenarios(): array {
		return [
			'live only'                         => [
				[ 'WC_STRIPE_SECRET_KEY' => 'rk_live_constant' ],
				'rk_live_constant',
				'sk_test_stored',
			],
			'test only'                         => [
				[ 'WC_STRIPE_TEST_SECRET_KEY' => 'rk_test_constant' ],
				'sk_live_stored',
				'rk_test_constant',
			],
			'both modes'                        => [
				[
					'WC_STRIPE_SECRET_KEY'      => 'rk_live_constant',
					'WC_STRIPE_TEST_SECRET_KEY' => 'rk_test_constant',
				],
				'rk_live_constant',
				'rk_test_constant',
			],
			'surrounding whitespace is trimmed' => [
				[ 'WC_STRIPE_SECRET_KEY' => "  rk_live_constant\n" ],
				'rk_live_constant',
				'sk_test_stored',
			],
		];
	}

	/**
	 * Blank or non-string constants are ignored instead of blanking the key,
	 * which would silently disconnect the store.
	 *
	 * @dataProvider provide_ignored_constant_values
	 *
	 * @param mixed $constant_value The invalid constant value.
	 * @return void
	 */
	public function test_invalid_constant_values_are_ignored( $constant_value ): void {
		$this->key_constants->constants = [ 'WC_STRIPE_SECRET_KEY' => $constant_value ];

		$settings = WC_Stripe::get_instance()->get_settings();

		$this->assertSame( 'sk_live_stored', $settings['secret_key'] );
		$this->assertFalse( $this->key_constants->has_overrides() );
	}

	/**
	 * Scenarios for test_invalid_constant_values_are_ignored().
	 *
	 * @return array
	 */
	public function provide_ignored_constant_values(): array {
		return [
			'empty string'    => [ '' ],
			'whitespace only' => [ '   ' ],
			'boolean'         => [ true ],
			'integer'         => [ 123 ],
		];
	}

	/**
	 * A settings save that round-trips the constant-injected key must not
	 * persist the constant into the database; the stored key is kept as-is.
	 *
	 * @return void
	 */
	public function test_round_tripped_save_does_not_persist_constant(): void {
		$this->key_constants->constants = [ 'WC_STRIPE_SECRET_KEY' => 'rk_live_constant' ];

		// Simulate the read-modify-write every settings save performs.
		$settings            = WC_Stripe::get_instance()->get_settings();
		$settings['enabled'] = 'no';
		WC_Stripe::get_instance()->update_settings( $settings );

		$this->key_constants->constants = [];
		$stored                         = get_option( WC_Stripe::SETTINGS_OPTION_NAME );

		$this->assertSame( 'no', $stored['enabled'] );
		$this->assertSame( 'sk_live_stored', $stored['secret_key'] );
		$this->assertSame( 'sk_test_stored', $stored['test_secret_key'] );
	}

	/**
	 * A caller that deliberately writes a key different from the constant is
	 * not interfered with — only exact round-trips of the constant are reverted.
	 *
	 * @return void
	 */
	public function test_deliberate_key_change_is_persisted(): void {
		$this->key_constants->constants = [ 'WC_STRIPE_SECRET_KEY' => 'rk_live_constant' ];

		$settings               = WC_Stripe::get_instance()->get_settings();
		$settings['secret_key'] = 'sk_live_replacement';
		WC_Stripe::get_instance()->update_settings( $settings );

		$this->key_constants->constants = [];
		$stored                         = get_option( WC_Stripe::SETTINGS_OPTION_NAME );

		$this->assertSame( 'sk_live_replacement', $stored['secret_key'] );
	}

	/**
	 * A round-tripped save with no stored key for the overridden field falls
	 * back to blank instead of persisting the constant.
	 *
	 * @return void
	 */
	public function test_round_tripped_save_with_no_stored_key_persists_blank(): void {
		update_option( WC_Stripe::SETTINGS_OPTION_NAME, [ 'enabled' => 'yes' ] );
		$this->key_constants->constants = [ 'WC_STRIPE_SECRET_KEY' => 'rk_live_constant' ];

		$settings = WC_Stripe::get_instance()->get_settings();
		$this->assertSame( 'rk_live_constant', $settings['secret_key'] );
		WC_Stripe::get_instance()->update_settings( $settings );

		$this->key_constants->constants = [];
		$stored                         = get_option( WC_Stripe::SETTINGS_OPTION_NAME );

		$this->assertSame( '', $stored['secret_key'] );
	}

	/**
	 * WC_Stripe_API picks up the constant-defined key for the active mode.
	 *
	 * @return void
	 */
	public function test_api_uses_constant_key_for_mode(): void {
		$this->key_constants->constants = [
			'WC_STRIPE_SECRET_KEY'      => 'rk_live_constant',
			'WC_STRIPE_TEST_SECRET_KEY' => 'rk_test_constant',
		];

		WC_Stripe_API::set_secret_key_for_mode( 'live' );
		$this->assertSame( 'rk_live_constant', WC_Stripe_API::get_secret_key() );

		WC_Stripe_API::set_secret_key_for_mode( 'test' );
		$this->assertSame( 'rk_test_constant', WC_Stripe_API::get_secret_key() );

		WC_Stripe_API::set_secret_key( '' );
	}
}
