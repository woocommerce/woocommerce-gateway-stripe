<?php
/**
 * These tests make assertions against abstract class WC_Stripe_Payment_Gateway
 *
 */


class WC_Stripe_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * Stripe Gateway under test.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * giropay Gateway under test.
	 *
	 * @var WC_Gateway_Stripe_Giropay
	 */
	private $giropay_gateway;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->gateway         = new WC_Gateway_Stripe();
		$this->giropay_gateway = new WC_Gateway_Stripe_Giropay();
	}

	/**
	 * Helper function to update test order meta data
	 */
	private function updateOrderMeta( $order, $key, $value ) {
		$order->update_meta_data( $key, $value );
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-payment-gateway-container'
	 */
	public function test_admin_options_when_stripe_is_connected() {
		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		ob_start();
		$this->giropay_gateway->admin_options();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-payment-gateway-container"%a', $output );
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
		$this->giropay_gateway->admin_options();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-new-account-container"%a', $output );
	}

	/**
	 * Tests false is returned if payment intent is not set in the order.
	 */
	public function test_default_get_payment_intent_from_order() {
		$order  = WC_Helper_Order::create_order();
		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );
	}

	/**
	 * Tests if payment intent is fetched from Stripe API.
	 */
	public function test_success_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();
		$this->updateOrderMeta( $order, '_stripe_intent_id', 'pi_123' );
		$expected_intent = (object) [ 'id' => 'pi_123' ];
		$callback        = function( $preempt, $request_args, $url ) use ( $expected_intent ) {
			$response = [
				'headers'  => [],
				'body'     => wp_json_encode( $expected_intent ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			];

			$this->assertEquals( 'GET', $request_args['method'] );
			$this->assertStringEndsWith( 'payment_intents/pi_123?expand[]=payment_method', $url );

			return $response;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

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
		$response_error = (object) [
			'error' => [
				'code'    => 'resource_missing',
				'message' => 'error_message',
			],
		];
		$callback       = function( $preempt, $request_args, $url ) use ( $response_error ) {
			$response = [
				'headers'  => [],
				'body'     => wp_json_encode( $response_error ),
				'response' => [
					'code'    => 404,
					'message' => 'ERR',
				],
			];

			$this->assertEquals( 'GET', $request_args['method'] );
			$this->assertStringEndsWith( 'payment_intents/pi_123?expand[]=payment_method', $url );

			return $response;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );

		remove_filter( 'pre_http_request', $callback );
	}
}
