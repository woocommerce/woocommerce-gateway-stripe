<?php
/**
 * This test makes assertions against the class WC_Stripe_Settings_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Settings_Controller
 */

/**
 * WC_Stripe_Settings_Controller unit tests.
 */
class WC_Stripe_Settings_Controller_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Settings_Controller
	 */
	private $controller;

	/**
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	public function set_up() {
		parent::set_up();

		$mock_account = $this->getMockBuilder( 'WC_Stripe_Account' )
									->disableOriginalConstructor()
									->getMock();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-settings-controller.php';
		$this->controller = new WC_Stripe_Settings_Controller( $mock_account );
		$this->gateway    = new WC_Gateway_Stripe();

	}

	public function tear_down() {
		delete_option( 'woocommerce_stripe_settings' );

		parent::tear_down();
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-account-settings-container'
	 */
	public function test_admin_options_when_stripe_is_connected() {
		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		ob_start();
		$this->controller->admin_options( $this->gateway );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-account-settings-container"%a', $output );
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-new-account-container'
	 */
	public function test_admin_options_when_stripe_is_not_connected() {
		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = '';
		$stripe_settings['test_secret_key']      = '';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		ob_start();
		$this->controller->admin_options( $this->gateway );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-new-account-container"%a', $output );
	}
}
