<?php

namespace WooCommerce\Stripe\Tests\Admin;

use WC_Stripe_Account;
use WC_Stripe_Helper;
use WC_Stripe_Intent_Status;
use WC_Stripe_Order_Helper;
use WC_Stripe_Settings_Controller;
use WC_Stripe_UPE_Payment_Gateway;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;
use WP_UnitTestCase;

/**
 * This test makes assertions against the class WC_Stripe_Settings_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Settings_Controller
 *
 * WC_Stripe_Settings_Controller unit tests.
 */
class WC_Stripe_Settings_Controller_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Settings_Controller
	 */
	private $controller;

	/**
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	public function set_up() {
		parent::set_up();

		$this->account = $this->getMockBuilder( 'WC_Stripe_Account' )
									->disableOriginalConstructor()
									->getMock();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-settings-controller.php';
		$this->gateway    = new WC_Stripe_UPE_Payment_Gateway();
		$this->controller = new WC_Stripe_Settings_Controller( $this->account, $this->gateway );
	}

	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-account-settings-container'
	 */
	public function test_admin_options_when_stripe_is_connected() {
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		ob_start();
		$this->controller->admin_options( $this->gateway );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-account-settings-container"%a', $output );
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-new-account-container'
	 */
	public function test_admin_options_when_stripe_is_not_connected() {
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = '';
		$stripe_settings['test_secret_key']      = '';
		$stripe_settings['publishable_key']      = '';
		$stripe_settings['secret_key']           = '';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		ob_start();
		$this->controller->admin_options( $this->gateway );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-new-account-container"%a', $output );
	}

	/**
	 * Test if `display_order_fee` and `display_order_payout` are called when viewing an order on the admin panel.
	 *
	 * @return void
	 */
	public function test_add_buttons_action_is_called_on_order_admin_page() {
		$order = WC_Helper_Order::create_order();

		$intent_id = 'pi_mock';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $intent_id );
		$order->save_meta_data();

		$intent = (object) [
			'id'     => 'pi_123',
			'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
		];

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setMethods( [ 'get_intent_from_order' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->with( $order )
			->willReturn( $intent );

		$controller = new WC_Stripe_Settings_Controller( $this->account, $gateway );

		ob_start();
		$controller->hide_refund_button_for_uncaptured_orders( $order );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aclass="button button-disabled"%a', $output );
	}
}
