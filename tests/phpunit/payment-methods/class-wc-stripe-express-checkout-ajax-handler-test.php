<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Express_Checkout_Ajax_Handler;
use WC_Stripe_Express_Checkout_Helper;
use WC_Stripe_Helper;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Ajax_Handler.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Ajax_Handler
 *
 * WC_Stripe_Express_Checkout_Ajax_Handler_Test class.
 */
class WC_Stripe_Express_Checkout_Ajax_Handler_Test extends WP_UnitTestCase {

	/**
	 * Express checkout helper instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Ajax handler instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Ajax_Handler
	 */
	private $ajax_handler;

	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->express_checkout_helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->getMock();
		$this->ajax_handler            = new WC_Stripe_Express_Checkout_Ajax_Handler( $this->express_checkout_helper );
	}


	/**
	 * Test modify_country_locale_for_express_checkout method.
	 *
	 * @dataProvider provide_test_modify_country_locale_for_express_checkout
	 */
	public function test_modify_country_locale_for_express_checkout( $is_express_context, $base_locale, $expected_state_required ) {
		$this->express_checkout_helper->expects( $this->any() )
			->method( 'is_express_checkout_context' )
			->willReturn( $is_express_context );

		$result = $this->ajax_handler->modify_country_locale_for_express_checkout( $base_locale );

		$this->assertEquals( $expected_state_required, $result['AF']['state']['required'] );
		// Countries with states should remain unchanged.
		$this->assertTrue( $result['US']['state']['required'] );
	}

	/**
	 * Data provider for test_modify_country_locale_for_express_checkout.
	 *
	 * @return array
	 */
	public function provide_test_modify_country_locale_for_express_checkout() {
		$base_locale = [
			'US' => [
				'state' => [
					'required' => true,
				],
			],
			'GB' => [
				'state' => [
					'required' => true,
				],
			],
			'AF' => [
				'state' => [
					'required' => true,
				],
			],
			'RO' => [
				'state' => [
					'required' => true,
				],
			],
		];

		return [
			'Not express checkout context - locale unchanged' => [
				'is_express_context'      => false,
				'input_locale'            => $base_locale,
				'expected_state_required' => true,
			],
			'Express checkout context - locale modified for countries without states' => [
				'is_express_context'      => true,
				'input_locale'            => $base_locale,
				'expected_state_required' => false,
			],
		];
	}
}
