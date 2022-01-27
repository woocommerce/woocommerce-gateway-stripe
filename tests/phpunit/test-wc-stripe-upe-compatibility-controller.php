<?php
/**
 * This test makes assertions against the class WC_Stripe_UPE_Compatibility_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_UPE_Compatibility_Controller
 */

/**
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
		parent::tear_down();

		// restore the overwritten values
		global $wp_version;
		$wp_version = $this->initial_wp_version;
	}

	protected function overwrite_wp_version( $version ) {
		global $wp_version;
		$wp_version = $version;
	}

	protected function overwrite_wc_version( $version ) {
		$this->controller->method( 'get_wc_version' )->willReturn( $version );
	}

	public function test_should_not_add_a_notice_when_the_wp_and_wc_versions_are_satisfied() {
		$this->overwrite_wc_version( '5.7.0' );
		$this->overwrite_wp_version( '5.7.0' );

		$this->expectOutputString( '' );

		$this->controller->add_compatibility_notice();
	}

	public function test_should_add_a_notice_when_the_wc_version_is_not_satisfied() {
		$this->overwrite_wp_version( '5.7.0' );
		$this->overwrite_wc_version( '5.2.0' );

		$this->expectOutputRegex( '/Stripe requires WooCommerce 5.5 or greater to be installed and active. Your version of WooCommerce 5.2.0 is no longer supported/' );

		$this->controller->add_compatibility_notice();
	}

	public function test_should_add_a_notice_when_the_wp_version_is_not_satisfied() {
		$this->overwrite_wp_version( '5.5.0' );
		$this->overwrite_wc_version( '5.7.0' );

		$this->expectOutputRegex( '/Stripe requires WordPress 5.6 or greater. Your version of WordPress 5.5.0 is no longer supported/' );

		$this->controller->add_compatibility_notice();
	}

	public function test_should_add_a_notice_when_the_wp_and_wc_versions_are_not_satisfied() {
		$this->overwrite_wp_version( '5.5.0' );
		$this->overwrite_wc_version( '5.2.1' );

		$this->expectOutputRegex( '/Stripe requires WordPress 5.6 or greater and WooCommerce 5.5 or greater to be installed and active. Your versions of WordPress 5.5.0 and WooCommerce 5.2.1 are no longer supported/' );

		$this->controller->add_compatibility_notice();
	}
}
