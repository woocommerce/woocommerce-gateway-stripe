<?php
/**
 * These tests make assertions against abstract class WC_Stripe_Payment_Gateway for non UPE legacy payment methods
 * Testing with Giropay as payment method
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Payment_Gateway
 */


class WC_Stripe_Payment_Gateway_Legacy_Methods_Test extends WP_UnitTestCase {
	/**
	 * Gateway under test.
	 *
	 * @var WC_Gateway_Stripe_Giropay
	 */
	private $gateway;

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();
		update_option( '_wcstripe_feature_upe_settings', 'yes' );

		$this->gateway = new WC_Gateway_Stripe_Giropay();
	}

	public function tearDown() {
		parent::tearDown();
		delete_option( '_wcstripe_feature_upe_settings' );
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-upe-opt-in-banner'
	 */
	public function test_admin_options_when_stripe_is_connected() {
		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		ob_start();
		$this->gateway->admin_options();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-upe-opt-in-banner"%a', $output );
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
		$this->gateway->admin_options();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-new-account-container"%a', $output );
	}
}
