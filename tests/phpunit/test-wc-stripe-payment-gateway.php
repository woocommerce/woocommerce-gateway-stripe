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

	public function test_are_keys_set_returns_true_in_test_mode() {
		$this->gateway->testmode        = true;
		$this->gateway->publishable_key = 'pk_test_key';
		$this->gateway->secret_key      = 'sk_test_key';

		$this->assertTrue( $this->gateway->are_keys_set() );
	}

	public function test_are_keys_set_returns_false_when_invalid_in_test_mode() {
		$this->gateway->testmode        = true;
		$this->gateway->publishable_key = 'pk_invalid_key';
		$this->gateway->secret_key      = 'sk_invalid_key';

		$this->assertFalse( $this->gateway->are_keys_set() );
	}

	public function test_are_keys_set_returns_true_in_live_mode() {
		$this->gateway->testmode        = false;
		$this->gateway->publishable_key = 'pk_live_key';
		$this->gateway->secret_key      = 'sk_live_key';

		$this->assertTrue( $this->gateway->are_keys_set() );
	}

	public function test_are_keys_set_returns_false_when_invalid_in_live_mode() {
		$this->gateway->testmode        = false;
		$this->gateway->publishable_key = 'pk_invalid_key';
		$this->gateway->secret_key      = 'sk_invalid_key';

		$this->assertFalse( $this->gateway->are_keys_set() );
	}

	public function test_is_available_returns_true_in_live_mode_with_ssl() {
		$this->gateway->testmode        = false;
		$this->gateway->enabled         = 'yes';
		$this->gateway->publishable_key = 'pk_live_key';
		$this->gateway->secret_key      = 'sk_live_key';

		// Using this to manipulate is_ssl().
		$_SERVER['HTTPS'] = 'on';

		$this->assertTrue( $this->gateway->is_available() );
	}

	public function test_is_available_returns_false_in_live_mode_with_no_ssl() {
		$this->gateway->testmode        = false;
		$this->gateway->enabled         = 'yes';
		$this->gateway->publishable_key = 'pk_live_key';
		$this->gateway->secret_key      = 'sk_live_key';

		// Using this to manipulate is_ssl().
		$_SERVER['HTTPS'] = false;

		$this->assertFalse( $this->gateway->is_available() );
	}

	public function test_is_available_returns_true_in_test_mode_with_no_ssl() {
		$this->gateway->testmode        = true;
		$this->gateway->enabled         = 'yes';
		$this->gateway->publishable_key = 'pk_test_key';
		$this->gateway->secret_key      = 'sk_test_key';

		// Using this to manipulate is_ssl().
		$_SERVER['HTTPS'] = false;

		$this->assertTrue( $this->gateway->is_available() );
	}

	public function test_add_payment_method_succeeds_with_source_object() {
		wp_set_current_user( 1 );
		$source_object_id       = 'le_source_object_id';
		$_POST['stripe_source'] = $source_object_id;

		$mock_source_object = (object) [
			'id'    => '123',
			'usage' => 'reusable',
		];

		$methods = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->once() )
			->method( 'get_source_object' )
			->with( $source_object_id )
			->willReturn( $mock_source_object );

		$mock_gateway
			->expects( $this->once() )
			->method( 'save_payment_method' )
			->with( $mock_source_object );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'success', $result );
	}

	public function test_add_payment_method_succeeds_with_stripe_token() {
		wp_set_current_user( 1 );
		$stripe_token          = 'le_stripe_token';
		$_POST['stripe_token'] = $stripe_token;

		$mock_source_object = (object) [
			'id'    => '123',
			'usage' => 'reusable',
		];

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->once() )
			->method( 'get_source_object' )
			->with( $stripe_token )
			->willReturn( $mock_source_object );

		$mock_gateway
			->expects( $this->once() )
			->method( 'save_payment_method' )
			->with( $mock_source_object );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'success', $result );
	}

	public function test_add_payment_method_fails_when_no_logged_in_user() {
		$_POST['stripe_token'] = 'le_stripe_token';

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->never() )
			->method( 'get_source_object' );

		$mock_gateway
			->expects( $this->never() )
			->method( 'save_payment_method' );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'failure', $result );
	}

	public function test_add_payment_method_fails_when_no_token_or_source_in_post() {
		wp_set_current_user( 1 );

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->never() )
			->method( 'get_source_object' );

		$mock_gateway
			->expects( $this->never() )
			->method( 'save_payment_method' );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'failure', $result );
	}

	public function test_add_payment_method_fails_when_stripe_returns_an_error() {
		wp_set_current_user( 1 );
		$stripe_token          = 'le_stripe_token';
		$_POST['stripe_token'] = $stripe_token;

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->once() )
			->method( 'get_source_object' )
			->with( $stripe_token )
			->will( $this->throwException( new WC_Stripe_Exception() ) );

		$mock_gateway
			->expects( $this->never() )
			->method( 'save_payment_method' );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'failure', $result );
	}

	public function test_add_payment_method_fails_when_source_object_is_wp_error() {
		wp_set_current_user( 1 );
		$stripe_token          = 'le_stripe_token';
		$_POST['stripe_token'] = $stripe_token;

		$wp_error_source_object = new WP_Error( 'Something went wrong' );

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->once() )
			->method( 'get_source_object' )
			->with( $stripe_token )
			->willReturn( $wp_error_source_object );

		$mock_gateway
			->expects( $this->never() )
			->method( 'save_payment_method' );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'failure', $result );
	}

	public function test_add_payment_method_fails_when_source_object_is_empty() {
		wp_set_current_user( 1 );
		$stripe_token          = 'le_stripe_token';
		$_POST['stripe_token'] = $stripe_token;

		$mock_source_object = (object) [];

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->once() )
			->method( 'get_source_object' )
			->with( $stripe_token )
			->willReturn( $mock_source_object );

		$mock_gateway
			->expects( $this->never() )
			->method( 'save_payment_method' );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'failure', $result );
	}

	public function test_add_payment_method_fails_when_payment_method_is_not_reusable() {
		wp_set_current_user( 1 );
		$stripe_token          = 'le_stripe_token';
		$_POST['stripe_token'] = $stripe_token;

		$mock_source_object = (object) [
			'id'    => '123',
			'usage' => 'not-reusable',
		];

		$methods      = [
			'get_source_object',
			'save_payment_method',
		];
		$mock_gateway = $this->get_partial_mock_for_gateway( $methods );

		$mock_gateway
			->expects( $this->once() )
			->method( 'get_source_object' )
			->with( $stripe_token )
			->willReturn( $mock_source_object );

		$mock_gateway
			->expects( $this->never() )
			->method( 'save_payment_method' )
			->with( $mock_source_object );

		$result = $mock_gateway->add_payment_method();

		$this->assertArrayHasKey( 'result', $result );
		$this->assertContains( 'failure', $result );
	}

	/**
	 * Create a partial mock for WC_Gateway_Stripe class.
	 *
	 * @param array $methods Method names that need to be mocked.
	 * @return MockObject|WC_Gateway_Stripe
	 */
	private function get_partial_mock_for_gateway( array $methods = [] ) {
		return $this->getMockBuilder( WC_Gateway_Stripe::class )
			->disableOriginalConstructor()
			->setMethods( $methods )
			->getMock();
	}
}
