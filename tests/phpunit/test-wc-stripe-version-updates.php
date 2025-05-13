<?php
/**
 * These tests make assertions related to version updates driven by
 * the `woocommerce_stripe_updated` action.
 *
 * @package WooCommerce_Stripe/Tests/Version_Updates
 */

/**
 * WC_Stripe_Version_Updates_Test class.
 */
class WC_Stripe_Version_Updates_Test extends WP_UnitTestCase {

	/**
	 * Variable to stash initial settings.
	 * @var array
	 */
	private static $stripe_settings;

	/**
	 * Variable to stash Stripe version.
	 * @var string
	 */
	private static $stripe_version;

	/**
	 * Stash existing settings from before tests.
	 */
	public static function setUpBeforeClass(): void {
		self::$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		self::$stripe_version  = get_option( 'wc_stripe_version', '' );
	}

	/**
	 * Restore settings from before tests.
	 */
	public static function tearDownAfterClass(): void {
		WC_Stripe_Helper::update_main_stripe_settings( self::$stripe_settings );
		update_option( 'wc_stripe_version', self::$stripe_version );

		self::$stripe_settings = null;
		self::$stripe_version  = null;
	}

	/**
	 * Ensure settings are clean before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Resets settings.
		WC_Stripe_Helper::delete_main_stripe_settings();
		delete_option( 'wc_stripe_version' );
	}

	public function test_payment_request_button_size_is_not_migrated_when_new_install() {
		$this->run_test();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayNotHasKey( 'payment_request_button_size', $stripe_settings );
	}

	/**
	 * Test runner/helper.
	 *
	 * @param string|null $stripe_version The value to store in the `wc_stripe_version` option.
	 * @param array|null  $stripe_settings Data to store in the Stripe settings.
	 * @return array      The Stripe settings after running the `woocommerce_stripe_updated` action.
	 */
	protected function run_test( $stripe_version = null, $stripe_settings = null ) {
		if ( null !== $stripe_version ) {
			update_option( 'wc_stripe_version', $stripe_version );
		}

		if ( null !== $stripe_settings ) {
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

			$wc_stripe_payment_request = WC_Stripe_Payment_Request::instance();
			$wc_stripe_payment_request->stripe_settings = $stripe_settings;
		}

		do_action( 'woocommerce_stripe_updated' );

		return WC_Stripe_Helper::get_stripe_settings();
	}

	public function test_payment_request_button_size_default_is_migrated_when_version_lt_7_8_0() {
		$mock_stripe_settings = [
			'payment_request_button_size' => 'default',
		];

		$stripe_settings = $this->run_test( '7.7.0', $mock_stripe_settings );

		$this->assertArrayHasKey( 'payment_request_button_size', $stripe_settings );
		$this->assertEquals( 'small', $stripe_settings['payment_request_button_size'] );
	}

	public function test_payment_request_button_size_default_is_not_migrated_when_version_is_7_8_0() {
		$mock_stripe_settings = [
			'payment_request_button_size' => 'default',
		];

		$stripe_settings = $this->run_test( '7.8.0', $mock_stripe_settings );

		$this->assertArrayHasKey( 'payment_request_button_size', $stripe_settings );
		$this->assertEquals( 'default', $stripe_settings['payment_request_button_size'] );
	}

	public function test_payment_request_button_size_default_is_not_migrated_when_version_gt_7_8_0() {
		$mock_stripe_settings = [
			'payment_request_button_size' => 'default',
		];

		$stripe_settings = $this->run_test( '7.9.0', $mock_stripe_settings );

		$this->assertArrayHasKey( 'payment_request_button_size', $stripe_settings );
		$this->assertEquals( 'default', $stripe_settings['payment_request_button_size'] );
	}

	public function test_payment_request_button_size_medium_is_migrated_when_version_lt_7_8_0() {
		$mock_stripe_settings = [
			'payment_request_button_size' => 'medium',
		];

		$stripe_settings = $this->run_test( '7.7.0', $mock_stripe_settings );

		$this->assertArrayHasKey( 'payment_request_button_size', $stripe_settings );
		$this->assertEquals( 'default', $stripe_settings['payment_request_button_size'] );
	}

	public function test_payment_request_button_size_medium_is_migrated_when_version_gt_7_8_0() {
		$mock_stripe_settings = [
			'payment_request_button_size' => 'medium',
		];

		$stripe_settings = $this->run_test( '7.9.0', $mock_stripe_settings );

		$this->assertArrayHasKey( 'payment_request_button_size', $stripe_settings );
		$this->assertEquals( 'default', $stripe_settings['payment_request_button_size'] );
	}
}
