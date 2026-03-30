<?php

use Automattic\WooCommerce\Enums\ProductType;

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
			'Not express checkout context - locale unchanged'                         => [
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

	/**
	 * Test ajax_add_to_cart sends wp_send_json_error payload on failure.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_add_to_cart_returns_error_for_invalid_product() {
		Ajax_Test_Helper::init_hooks();

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-add-to-cart' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = 0;
			$_POST['qty']         = 1;

			WC()->session->init();
			WC()->cart->empty_cart();

			ob_start();
			$this->ajax_handler->ajax_add_to_cart();
			$output = ob_get_clean();

			$response = json_decode( $output, true );
		} finally {
			WC()->cart->empty_cart();
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'success', $response );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'message', $response['data'] );
	}

	/**
	 * Test ajax_add_to_cart returns success payload for a supported simple product.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_add_to_cart_returns_success_for_simple_product() {
		Ajax_Test_Helper::init_hooks();

		$product = WC_Helper_Product::create_simple_product();

		$this->express_checkout_helper->expects( $this->once() )
			->method( 'supported_product_types' )
			->willReturn( [ ProductType::SIMPLE ] );

		$display_items = [
			'displayItems' => [
				[
					'label'  => $product->get_name(),
					'amount' => 1000,
				],
			],
			'total'        => [
				'label'  => 'Total',
				'amount' => 1000,
			],
		];

		$this->express_checkout_helper->expects( $this->once() )
			->method( 'build_display_items' )
			->willReturn( $display_items );

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-add-to-cart' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = 1;

			WC()->session->init();
			WC()->cart->empty_cart();

			ob_start();
			$this->ajax_handler->ajax_add_to_cart();
			$output = ob_get_clean();
		} finally {
			WC()->cart->empty_cart();
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		$response = json_decode( $output, true );

		$this->assertIsArray( $response );
		$this->assertSame( 'success', $response['result'] );
		$this->assertSame( $display_items['displayItems'], $response['displayItems'] );
		$this->assertSame( $display_items['total'], $response['total'] );
	}
}
