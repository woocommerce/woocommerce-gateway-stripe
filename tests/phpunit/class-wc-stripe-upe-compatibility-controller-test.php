<?php

/**
 * This test makes assertions against the class WC_Stripe_UPE_Compatibility_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_UPE_Compatibility_Controller
 *
 * WC_Stripe_UPE_Compatibility_Controller unit tests.
 */
class WC_Stripe_UPE_Compatibility_Controller_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_UPE_Compatibility_Controller
	 */
	private $controller;

	/**
	 * @var string
	 */
	private $initial_wp_version;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// saving these values to that they can be restored after the test runs
		global $wp_version;
		$this->initial_wp_version = $wp_version;

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-upe-compatibility-controller.php';

		$this->controller = $this->getMockBuilder( WC_Stripe_UPE_Compatibility_Controller::class )
								 ->disableOriginalConstructor()
								 ->setMethods( [ 'get_wc_version' ] )
								 ->getMock();
	}

	public function tear_down() {
		// restore the overwritten values
		global $wp_version;
		$wp_version = $this->initial_wp_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	protected function overwrite_wp_version( $version ) {
		global $wp_version;
		$wp_version = $version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	protected function overwrite_wc_version( $version ) {
		$this->controller->method( 'get_wc_version' )->willReturn( $version );
	}

	/**
	 * Test for `add_compatibility_notice`.
	 *
	 * @param string  $wp_version      The WordPress version to set.
	 * @param string  $wc_version      The WooCommerce version to set.
	 * @param ?string $expected_regex  Regex pattern the output should match, or null if no output is expected.
	 * @return void
	 * @dataProvider provide_test_add_compatibility_notice
	 */
	public function test_add_compatibility_notice( string $wp_version, string $wc_version, ?string $expected_regex ) {
		$this->overwrite_wc_version( $wc_version );
		$this->overwrite_wp_version( $wp_version );

		if ( null === $expected_regex ) {
			$this->expectOutputString( '' );
		} else {
			$this->expectOutputRegex( $expected_regex );
		}

		$this->controller->add_compatibility_notice();
	}

	/**
	 * Data provider for `test_add_compatibility_notice`.
	 *
	 * @return array
	 */
	public function provide_test_add_compatibility_notice(): array {
		return [
			'both versions satisfied'       => [ '5.7.0', '5.7.0', null ],
			'WC version not satisfied'      => [ '5.7.0', '5.2.0', '/Stripe requires WooCommerce 5.5 or greater to be installed and active. Your version of WooCommerce 5.2.0 is no longer supported/' ],
			'WP version not satisfied'      => [ '5.5.0', '5.7.0', '/Stripe requires WordPress 5.6 or greater. Your version of WordPress 5.5.0 is no longer supported/' ],
			'both versions not satisfied'   => [ '5.5.0', '5.2.1', '/Stripe requires WordPress 5.6 or greater and WooCommerce 5.5 or greater to be installed and active. Your versions of WordPress 5.5.0 and WooCommerce 5.2.1 are no longer supported/' ],
		];
	}
}
