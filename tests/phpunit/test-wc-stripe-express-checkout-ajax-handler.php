<?php

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Ajax_Handler.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Express_Checkout_Ajax_Handler
 * /

/**
 * Class WC_Stripe_Express_Checkout_Ajax_Handler_Test
 */
class WC_Stripe_Express_Checkout_Ajax_Handler_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Express_Checkout_Ajax_Handler
	 */
	private $handler;

	/**
	 * Setup test.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->getMock();

		$this->handler = new WC_Stripe_Express_Checkout_Ajax_Handler( $helper );
	}

	/**
	 * Test for `ajax_get_cart_details`.
	 *
	 * @return void
	 * @dataProvider provide_test_ajax_get_cart_details
	 */
	public function test_ajax_get_cart_details( $is_cart, $expected ) {
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc-stripe-get-cart-details' );

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->setMethods( [ 'build_display_items' ] )
			->getMock();

		$helper->expects( $this->any() )
			->method( 'build_display_items' )
			->willReturn( [] );

		$handler = new WC_Stripe_Express_Checkout_Ajax_Handler( $helper );

		ob_start();
		$handler->ajax_get_cart_details();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( wp_json_encode( $expected ), $output );
	}

	/**
	 * Provider for `test_ajax_get_cart_details`.
	 *
	 * @return array
	 */
	public function provide_test_ajax_get_cart_details() {
		return [
			'not cart' => [
				'is cart'  => false,
				'expected' => [],
			],
			'cart'     => [
				'is cart'  => true,
				'expected' => [],
			],
		];
	}

	/**
	 * Test for `ajax_get_cart_totals`.
	 *
	 * @return void
	 */
	public function test_ajax_add_to_cart() {
		$this->markTestIncomplete();

		ob_start();
		$this->handler->ajax_add_to_cart();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '', $output );
	}

	/**
	 * Test for `ajax_get_cart_totals`.
	 *
	 * @return void
	 */
	public function test_ajax_clear_cart() {
		$this->markTestIncomplete();

		add_action(
			'wc-booking-remove-inactive-cart',
			function() {
				echo 'wc-booking-remove-inactive-cart';
			}
		);

		ob_start();
		$this->handler->ajax_clear_cart();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( 'wc-booking-remove-inactive-cart', $output );
	}

	/**
	 * Test for `ajax_get_cart_totals`.
	 *
	 * @return void
	 */
	public function test_ajax_get_shipping_options() {
		$this->markTestIncomplete();

		ob_start();
		$this->handler->ajax_get_shipping_options();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( wp_json_encode( [] ), $output );
	}

	/**
	 * Test for `ajax_update_shipping_method`.
	 *
	 * @return void
	 * @dataProvider provide_test_ajax_update_shipping_method
	 */
	public function test_ajax_update_shipping_method( $expected ) {
		$this->markTestIncomplete();

		ob_start();
		$this->handler->ajax_update_shipping_method();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( $expected, $output );
	}

	/**
	 * Provider for `test_ajax_update_shipping_method`.
	 *
	 * @return array
	 */
	public function provide_test_ajax_update_shipping_method() {
		return [];
	}

	/**
	 * Test for `ajax_get_cart_totals`.
	 *
	 * @return void
	 * @dataProvider provide_test_ajax_get_selected_product_data
	 */
	public function test_ajax_get_selected_product_data( $expected ) {
		$this->markTestIncomplete();

		ob_start();
		$this->handler->ajax_get_selected_product_data();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( $expected, $output );
	}

	/**
	 * Provider for `test_ajax_get_selected_product_data`.
	 *
	 * @return array
	 */
	public function provide_test_ajax_get_selected_product_data() {
		return [];
	}

	/**
	 * Test for `ajax_create_order`.
	 *
	 * @return void
	 * @dataProvider provide_test_ajax_create_order
	 */
	public function test_ajax_create_order() {
		$this->markTestIncomplete();

		$this->handler->ajax_create_order();
	}

	/**
	 * Provider for `test_ajax_create_order`.
	 *
	 * @return array
	 */
	public function provide_test_ajax_create_order() {
		return [];
	}

	/**
	 * Test for `ajax_log_errors`.
	 *
	 * @return void
	 */
	public function test_ajax_log_errors() {
		$this->markTestIncomplete();

		$this->handler->ajax_log_errors();
	}

	/**
	 * Test for `ajax_pay_for_order`.
	 *
	 * @return void
	 * @dataProvider provide_test_ajax_pay_for_order
	 * @throws WC_Data_Exception If unable to save the order.
	 */
	public function test_ajax_pay_for_order( $payment_method, $valid_order, $needs_payment, $process_payment, $expected ) {
		$this->markTestIncomplete();

		if ( $payment_method ) {
			$_POST['payment_method'] = $payment_method;
		}
		if ( $valid_order ) {
			$order = wc_create_order();
			if ( $needs_payment ) {
				$order->set_total( 10 );
				$order->save();
			}
			$_POST['order_id'] = $order->get_id();
		}

		ob_start();
		$this->handler->ajax_pay_for_order();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( $expected, $output );
	}

	/**
	 * Provider for `test_ajax_pay_for_order`.
	 *
	 * @return array
	 */
	public function provide_test_ajax_pay_for_order() {
		return [
			'incomplete request'                  => [
				'payment method'      => null,
				'valid order'         => true,
				'order needs payment' => true,
				'process payment'     => true,
				'expected'            => [],
			],
			'invalid order'                       => [
				'payment method'      => 'stripe',
				'valid order'         => false,
				'order needs payment' => true,
				'process payment'     => true,
				'expected'            => [],
			],
			'order does not require payment'      => [
				'payment method'      => 'stripe',
				'valid order'         => true,
				'order needs payment' => false,
				'process payment'     => true,
				'expected'            => [],
			],
			'unable to determine payment success' => [
				'payment method'      => 'stripe',
				'valid order'         => true,
				'order needs payment' => true,
				'process payment'     => false,
				'expected'            => [],
			],
			'success'                             => [
				'payment method'      => 'stripe',
				'valid order'         => true,
				'order needs payment' => true,
				'process payment'     => true,
				'expected'            => [],
			],
		];
	}
}
