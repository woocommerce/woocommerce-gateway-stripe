<?php
/**
 * These tests make assertions against abstract class WC_Stripe_Payment_Gateway
 *
 */


class WC_Stripe_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * Gateway under test.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();

		$this->gateway = new WC_Gateway_Stripe();
	}

	/**
	 * Helper function to update test order meta data
	 */
	private function updateOrderMeta( $order, $key, $value ) {
		$order->update_meta_data( $key, $value );
	}

	/**
	 * Tests false is returned if payment intent is not set in the order.
	 */
	public function test_default_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );
	}

	/**
	 * Tests if payment intent is fetched from Stripe API.
	 */
	public function test_success_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$this->updateOrderMeta( $order, '_stripe_intent_id', 'pi_123' );
		$expected_intent = ( object ) [ 'id' => 'pi_123' ];
		$callback = function( $preempt, $request_args, $url ) use ( $expected_intent ) {
			$response = [
				'headers' 	=> [],
				'body'		=> json_encode( $expected_intent ),
				'response'	=> [
					'code' 		=> 200,
					'message' 	=> 'OK',
				],
			];

			$this->assertEquals( 'GET', $request_args['method'] );
			$this->assertStringEndsWith( 'payment_intents/pi_123', $url );

			return $response;
		};

		add_filter( 'pre_http_request', $callback, 10, 3);

		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertEquals( $expected_intent, $intent );

		remove_filter( 'pre_http_request', $callback );
	}

	/**
	 * Tests if false is returned when error is returned from Stripe API.
	 */
	public function test_error_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$this->updateOrderMeta( $order, '_stripe_intent_id', 'pi_123' );
		$response_error = ( object ) [
			'error' => [
				'code' 		=> 'resource_missing',
				'message' 	=> 'error_message'
			]
		];
		$callback = function( $preempt, $request_args, $url ) use ( $response_error ) {
			$response = [
				'headers' 	=> [],
				'body'		=> json_encode( $response_error ),
				'response'	=> [
					'code' 		=> 404,
					'message' 	=> 'ERR',
				],
			];

			$this->assertEquals( 'GET', $request_args['method'] );
			$this->assertStringEndsWith( 'payment_intents/pi_123', $url );

			return $response;
		};

		add_filter( 'pre_http_request', $callback, 10, 3);

		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );

		remove_filter( 'pre_http_request', $callback );
	}

	public function test_get_icon_with_us_store() {
		$location_callback = function() {
			return [
				'country' => 'US',
				'state'   => 'CA'
			];
		};

		$currency_callback = function() {
			return 'USD';
		};

		add_filter( 'wc_get_base_location', $location_callback );
		add_filter( 'get_woocommerce_currency', $currency_callback );

		$payment_icons = $this->gateway->get_icon();

		$this->assertContains( 'visa.svg', $payment_icons );
		$this->assertContains( 'amex.svg', $payment_icons );
		$this->assertContains( 'mastercard.svg', $payment_icons );
		$this->assertContains( 'diners.svg', $payment_icons );
		$this->assertContains( 'discover.svg', $payment_icons );
		$this->assertContains( 'jcb.svg', $payment_icons );
	}

	public function test_get_icon_with_br_store() {
		$location_callback = function() {
			return [
				'country' => 'BR',
				'state'   => ''
			];
		};

		$currency_callback = function() {
			return 'USD';
		};

		add_filter( 'wc_get_base_location', $location_callback );
		add_filter( 'get_woocommerce_currency', $currency_callback );

		$payment_icons = $this->gateway->get_icon();

		$this->assertContains( 'visa.svg', $payment_icons );
		$this->assertContains( 'mastercard.svg', $payment_icons );
		$this->assertContains( 'diners.svg', $payment_icons );
		$this->assertContains( 'discover.svg', $payment_icons );
		$this->assertContains( 'jcb.svg', $payment_icons );
		$this->assertNotContains( 'amex.svg', $payment_icons );
	}
	
	
	public function test_get_icon_with_non_dollar_currency() {
		$location_callback = function() {
			return [
				'country' => 'US',
				'state'   => 'CA'
			];
		};

		$currency_callback = function() {
			return 'GBP';
		};

		add_filter( 'wc_get_base_location', $location_callback );
		add_filter( 'get_woocommerce_currency', $currency_callback );

		$payment_icons = $this->gateway->get_icon();

		$this->assertContains( 'visa.svg', $payment_icons );
		$this->assertContains( 'mastercard.svg', $payment_icons );
		$this->assertContains( 'amex.svg', $payment_icons );
		$this->assertNotContains( 'diners.svg', $payment_icons );
		$this->assertNotContains( 'discover.svg', $payment_icons );
		$this->assertNotContains( 'jcb.svg', $payment_icons );
	}

	public function test_get_icon_with_ca_store() {
		$location_callback = function() {
			return [
				'country' => 'CA',
				'state'   => ''
			];
		};

		$currency_callback = function() {
			return 'USD';
		};

		add_filter( 'wc_get_base_location', $location_callback );
		add_filter( 'get_woocommerce_currency', $currency_callback );

		$payment_icons = $this->gateway->get_icon();

		$this->assertContains( 'visa.svg', $payment_icons );
		$this->assertContains( 'amex.svg', $payment_icons );
		$this->assertContains( 'mastercard.svg', $payment_icons );
		$this->assertContains( 'diners.svg', $payment_icons );
		$this->assertContains( 'discover.svg', $payment_icons );
		$this->assertContains( 'jcb.svg', $payment_icons );
	}

	public function test_get_icon_with_jp_non_jpy_store() {
		$location_callback = function() {
			return [
				'country' => 'JP',
				'state'   => ''
			];
		};

		$currency_callback = function() {
			return 'JPY';
		};

		add_filter( 'wc_get_base_location', $location_callback );
		add_filter( 'get_woocommerce_currency', $currency_callback );

		$payment_icons = $this->gateway->get_icon();

		// JCP only accepts JPY in Japanese stores:
		$this->assertContains( 'visa.svg', $payment_icons );
		$this->assertContains( 'amex.svg', $payment_icons );
		$this->assertContains( 'mastercard.svg', $payment_icons );
		$this->assertNotContains( 'diners.svg', $payment_icons );
		$this->assertNotContains( 'discover.svg', $payment_icons );
		$this->assertContains( 'jcb.svg', $payment_icons );
	}

	public function test_get_icon_with_jp_non_jpy_store() {
		$location_callback = function() {
			return [
				'country' => 'JP',
				'state'   => ''
			];
		};

		$currency_callback = function() {
			return 'USD';
		};

		add_filter( 'wc_get_base_location', $location_callback );
		add_filter( 'get_woocommerce_currency', $currency_callback );

		$payment_icons = $this->gateway->get_icon();

		// JCP only accepts JPY in Japanese stores:
		$this->assertContains( 'visa.svg', $payment_icons );
		$this->assertContains( 'amex.svg', $payment_icons );
		$this->assertContains( 'mastercard.svg', $payment_icons );
		$this->assertNotContains( 'diners.svg', $payment_icons );
		$this->assertNotContains( 'discover.svg', $payment_icons );
		$this->assertNotContains( 'jcb.svg', $payment_icons );
	}
}
