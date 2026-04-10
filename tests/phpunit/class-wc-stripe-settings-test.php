<?php

/**
 * Class WC_Stripe_Settings_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Settings
 */
class WC_Stripe_Settings_Test extends WP_UnitTestCase {

	/**
	 * Reset settings before each test.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( WC_Stripe_Settings::SETTINGS_OPTION );
		// Reset the singleton so it re-reads from the DB.
		$reflection = new ReflectionClass( WC_Stripe_Settings::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Test get_instance returns the same instance.
	 */
	public function test_get_instance_returns_singleton() {
		$instance1 = WC_Stripe_Settings::get_instance();
		$instance2 = WC_Stripe_Settings::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test string getters return expected values or defaults.
	 *
	 * @dataProvider provide_string_getters
	 *
	 * @param string $setting_key    The settings array key.
	 * @param string $getter_method  The getter method name.
	 * @param string $default_value  The expected default when unset.
	 * @param string $test_value     A value to set and verify.
	 */
	public function test_string_getters( string $setting_key, string $getter_method, string $default_value, string $test_value ) {
		// Test default value when option is empty.
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( $default_value, $settings->$getter_method() );

		// Set a value and verify.
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ $setting_key => $test_value ] );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( $test_value, $settings->$getter_method() );
	}

	/**
	 * Data provider for string getter tests.
	 *
	 * @return array
	 */
	public function provide_string_getters(): array {
		return [
			'logging'                            => [ 'logging', 'get_logging', '', 'yes' ],
			'publishable_key'                    => [ 'publishable_key', 'get_publishable_key', '', 'pk_test_123' ],
			'secret_key'                         => [ 'secret_key', 'get_secret_key', '', 'sk_test_123' ],
			'statement_descriptor'               => [ 'statement_descriptor', 'get_statement_descriptor', '', 'MY STORE' ],
			'test_publishable_key'               => [ 'test_publishable_key', 'get_test_publishable_key', '', 'pk_test_abc' ],
			'test_secret_key'                    => [ 'test_secret_key', 'get_test_secret_key', '', 'sk_test_abc' ],
			'test_mode'                          => [ 'test_mode', 'get_test_mode', '', 'yes' ],
			'webhook_secret'                     => [ 'webhook_secret', 'get_webhook_secret', '', 'whsec_123' ],
			'test_webhook_secret'                => [ 'test_webhook_secret', 'get_test_webhook_secret', '', 'whsec_test_123' ],
			'connection_type'                    => [ 'connection_type', 'get_connection_type', '', 'connect' ],
			'test_connection_type'               => [ 'test_connection_type', 'get_test_connection_type', '', 'connect' ],
			'pmc_enabled'                        => [ 'pmc_enabled', 'get_pmc_enabled', '', 'yes' ],
			'payment_request'                    => [ 'payment_request', 'get_payment_request', '', 'yes' ],
			'express_checkout'                   => [ 'express_checkout', 'get_express_checkout', '', 'yes' ],
			'skip_pmc_express_checkout_defaults' => [ 'skip_pmc_express_checkout_defaults', 'get_skip_pmc_express_checkout_defaults', 'no', 'yes' ],
		];
	}

	/**
	 * Test set_pmc_enabled persists to the database.
	 */
	public function test_set_pmc_enabled() {
		$settings = WC_Stripe_Settings::get_instance();
		$settings->set_pmc_enabled( 'yes' );

		$stored = get_option( WC_Stripe_Settings::SETTINGS_OPTION );
		$this->assertSame( 'yes', $stored['pmc_enabled'] );

		$settings->set_pmc_enabled( 'no' );
		$stored = get_option( WC_Stripe_Settings::SETTINGS_OPTION );
		$this->assertSame( 'no', $stored['pmc_enabled'] );
	}

	/**
	 * Test set_stripe_upe_payment_method_order persists to the database.
	 */
	public function test_set_stripe_upe_payment_method_order() {
		$settings = WC_Stripe_Settings::get_instance();
		$order    = [ 'card', 'klarna', 'sepa_debit' ];
		$settings->set_stripe_upe_payment_method_order( $order );

		$stored = get_option( WC_Stripe_Settings::SETTINGS_OPTION );
		$this->assertSame( $order, $stored['stripe_upe_payment_method_order'] );
	}

	/**
	 * Test get_upe_checkout_experience_accepted_payments returns defaults when empty.
	 */
	public function test_get_upe_checkout_experience_accepted_payments_default() {
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], $settings->get_upe_checkout_experience_accepted_payments() );
	}

	/**
	 * Test get_upe_checkout_experience_accepted_payments returns stored value.
	 */
	public function test_get_upe_checkout_experience_accepted_payments_with_value() {
		$methods = [ 'card', 'klarna', 'sepa_debit' ];
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ 'upe_checkout_experience_accepted_payments' => $methods ] );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( $methods, $settings->get_upe_checkout_experience_accepted_payments() );
	}

	/**
	 * Test get_express_checkout_button_locations returns defaults when unset.
	 */
	public function test_get_express_checkout_button_locations_default() {
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( [ 'product', 'cart' ], $settings->get_express_checkout_button_locations() );
	}

	/**
	 * Test get_express_checkout_button_locations returns empty array for non-array value.
	 */
	public function test_get_express_checkout_button_locations_non_array() {
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ 'payment_request_button_locations' => '' ] );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( [], $settings->get_express_checkout_button_locations() );
	}

	/**
	 * Test get_express_checkout_button_locations returns stored array.
	 */
	public function test_get_express_checkout_button_locations_stored() {
		$locations = [ 'product', 'cart', 'checkout' ];
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ 'payment_request_button_locations' => $locations ] );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( $locations, $settings->get_express_checkout_button_locations() );
	}

	/**
	 * Test constructor handles non-array option gracefully.
	 */
	public function test_constructor_handles_non_array_option() {
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, 'invalid' );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertFalse( $settings->is_enabled() );
	}

	/**
	 * Test is_enabled returns correct boolean values.
	 *
	 * @dataProvider provide_is_enabled
	 *
	 * @param mixed $value    The stored setting value.
	 * @param bool  $expected The expected result.
	 */
	public function test_is_enabled( $value, bool $expected ) {
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ 'enabled' => $value ] );
		$this->reset_singleton();
		$this->assertSame( $expected, WC_Stripe_Settings::get_instance()->is_enabled() );
	}

	/**
	 * Data provider for is_enabled.
	 *
	 * @return array
	 */
	public function provide_is_enabled(): array {
		return [
			'yes'   => [ 'yes', true ],
			'no'    => [ 'no', false ],
			'empty' => [ '', false ],
			'null'  => [ null, false ],
		];
	}

	/**
	 * Test get() returns value for existing key.
	 */
	public function test_get_returns_value() {
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ 'some_key' => 'some_value' ] );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertSame( 'some_value', $settings->get( 'some_key' ) );
	}

	/**
	 * Test get() returns default for missing key.
	 */
	public function test_get_returns_default_for_missing_key() {
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertNull( $settings->get( 'nonexistent_key' ) );
		$this->assertSame( 'fallback', $settings->get( 'nonexistent_key', 'fallback' ) );
	}

	/**
	 * Test has() returns true for existing key and false for missing.
	 */
	public function test_has() {
		update_option( WC_Stripe_Settings::SETTINGS_OPTION, [ 'exists' => 'yes' ] );
		$this->reset_singleton();
		$settings = WC_Stripe_Settings::get_instance();
		$this->assertTrue( $settings->has( 'exists' ) );
		$this->assertFalse( $settings->has( 'does_not_exist' ) );
	}

	/**
	 * Reset the singleton instance to force re-reading from DB.
	 */
	private function reset_singleton() {
		$reflection = new ReflectionClass( WC_Stripe_Settings::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}
}
