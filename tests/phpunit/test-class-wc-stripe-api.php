<?php
/**
 * Class WC_Stripe_API
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_API
 */

/**
 * Class WC_Stripe_API tests.
 */
class WC_Stripe_API_Test extends WP_UnitTestCase {

	/**
	 * Secret key for test mode.
	 */
	const TEST_SECRET_KEY = 'sk_test_key_123';

	/**
	 * Secret key for live mode.
	 */
	const LIVE_SECRET_KEY = 'sk_live_key_123';

	/**
	 * Setup environment for tests.
	 */
	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['secret_key']           = self::LIVE_SECRET_KEY;
		$stripe_settings['test_secret_key']      = self::TEST_SECRET_KEY;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Tear down environment after tests.
	 */
	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();
		WC_Stripe_API::set_secret_key( null );
		parent::tear_down();
	}

	/**
	 * Test get_secret_key and set_secret_key.
	 */
	public function test_set_secret_key() {
		$secret_key = 'sk_test_key';
		WC_Stripe_API::set_secret_key( $secret_key );

		$this->assertEquals( $secret_key, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::set_secret_key_for_mode() with no parameter.
	 */
	public function test_set_secret_key_for_mode_no_parameter() {
		// Base test - current mode is test.
		WC_Stripe_API::set_secret_key_for_mode();

		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Enable live mode.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Stripe_API::set_secret_key_for_mode();

		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );
	}

	/**
	 * Test WC_Stripe_API::set_secret_key_for_mode() with mode parameters.
	 */
	public function test_set_secret_key_for_mode_with_parameter() {
		WC_Stripe_API::set_secret_key_for_mode( 'test' );
		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		WC_Stripe_API::set_secret_key_for_mode( 'live' );
		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Invalid parameters will set the secret key to the current mode.
		WC_Stripe_API::set_secret_key_for_mode( 'invalid' );
		$this->assertEquals( self::TEST_SECRET_KEY, WC_Stripe_API::get_secret_key() );

		// Set the mode to live and test the invalid parameter.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		WC_Stripe_API::set_secret_key_for_mode( 'invalid' );
		$this->assertEquals( self::LIVE_SECRET_KEY, WC_Stripe_API::get_secret_key() );
	}
}
