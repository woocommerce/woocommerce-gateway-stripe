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
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		ob_start();
		$this->giropay_gateway->admin_options();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-payment-gateway-container"%a', $output );
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
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

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

		$methods      = [
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
	 * Tests for `needs_setup` method.
	 *
	 * @param bool   $is_test_mode         Whether the gateway is in test mode.
	 * @param string $test_publishable_key Test publishable key.
	 * @param string $test_secret_key      Test secret key.
	 * @param string $publishable_key      Live publishable key.
	 * @param string $secret_key           Live secret key.
	 * @param bool   $expected             Expected result.
	 * @return void
	 * @dataProvider provide_test_needs_setup
	 */
	public function test_needs_setup( $is_test_mode, $test_publishable_key, $test_secret_key, $publishable_key, $secret_key, $expected ) {
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = $is_test_mode ? 'yes' : 'no';
		$stripe_settings['test_publishable_key'] = $test_publishable_key;
		$stripe_settings['test_secret_key']      = $test_secret_key;
		$stripe_settings['publishable_key']      = $publishable_key;
		$stripe_settings['secret_key']           = $secret_key;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$gateway = new WC_Gateway_Stripe();
		$this->assertSame( $expected, $gateway->needs_setup() );
	}

	/**
	 * Provider for `test_needs_setup` method.
	 *
	 * @return array[]
	 */
	public function provide_test_needs_setup() {
		return [
			'test mode, missing keys' => [
				'is test mode'         => true,
				'test_publishable_key' => null,
				'test_secret_key'      => null,
				'publishable_key'      => null,
				'secret_key'           => null,
				'expected'             => true,
			],
			'test mode, filled keys'  => [
				'is test mode'         => true,
				'test_publishable_key' => 'pk_test_key',
				'test_secret_key'      => 'sk_test_key',
				'publishable_key'      => null,
				'secret_key'           => null,
				'expected'             => false,
			],
			'live mode, missing keys' => [
				'is test mode'         => false,
				'test_publishable_key' => null,
				'test_secret_key'      => null,
				'publishable_key'      => null,
				'secret_key'           => null,
				'expected'             => true,
			],
			'live mode, filled keys'  => [
				'is test mode'         => false,
				'test_publishable_key' => null,
				'test_secret_key'      => null,
				'publishable_key'      => 'pk_live_key',
				'secret_key'           => 'sk_live_key',
				'expected'             => false,
			],
		];
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

	public function test_get_balance_transaction_id_from_charge() {
		$expected_balance_transaction_id = 'txn_test123';
		$balance_transaction_object      = (object) [
			'id' => $expected_balance_transaction_id,
		];

		$charge_expanded = (object) [
			'id'                  => 'ch_test123',
			'balance_transaction' => $balance_transaction_object,
		];
		$this->assertEquals( $expected_balance_transaction_id, $this->gateway->get_balance_transaction_id_from_charge( $charge_expanded ) );

		$charge_non_expanded             = (object) [
			'id' => 'ch_test123',
			'balance_transaction' => $expected_balance_transaction_id,
		];
		$this->assertEquals( $expected_balance_transaction_id, $this->gateway->get_balance_transaction_id_from_charge( $charge_non_expanded ) );

		/**
		 * ------------------------------------
		 * Test invalid cases.
		 * ------------------------------------
		 */
		$charge_no_balance_transaction_id = (object) [
			'id' => 'ch_test123',
		];
		$this->assertEquals( null, $this->gateway->get_balance_transaction_id_from_charge( $charge_no_balance_transaction_id ) );

		$charge_no_balance_transaction = (object) [
			'id'                  => 'ch_test123',
			'balance_transaction' => null,
		];
		$this->assertEquals( null, $this->gateway->get_balance_transaction_id_from_charge( $charge_no_balance_transaction ) );

		$charge_no_balance_transaction_object = (object) [
			'id'                  => 'ch_test123',
			'balance_transaction' => (object) [],
		];
		$this->assertEquals( null, $this->gateway->get_balance_transaction_id_from_charge( $charge_no_balance_transaction_object ) );

		$this->assertEquals( null, $this->gateway->get_balance_transaction_id_from_charge( null ) );
	}

	/**
	 * Tests for Card brand and last 4 digits are displayed correctly for subscription.
	 *
	 * @see WC_Stripe_Subscriptions_Trait::maybe_render_subscription_payment_method()
	 */
	public function test_render_subscription_payment_method() {
		$mock_subscription = WC_Helper_Order::create_order(); // We can use an order as a subscription.
		$mock_subscription->set_payment_method( 'stripe' );

		$mock_subscription->update_meta_data( '_stripe_source_id', 'src_mock' );
		$mock_subscription->update_meta_data( '_stripe_customer_id', 'cus_mock' );
		$mock_subscription->save();

		// This is the key the customer's payment methods are stored under in the transient.
		$transient_key = WC_Stripe_Customer::PAYMENT_METHODS_TRANSIENT_KEY . 'cardcus_mock';

		$mock_payment_method       = new stdClass();
		$mock_payment_method->id   = 'src_mock';
		$mock_payment_method->type = 'card';
		$mock_payment_method->card = new stdClass();

		// VISA ending in 4242
		$mock_payment_method->card->brand = 'visa';
		$mock_payment_method->card->last4 = '4242';

		set_transient( $transient_key, [ $mock_payment_method ], DAY_IN_SECONDS );
		$this->assertEquals( 'Via Visa card ending in 4242', $this->gateway->maybe_render_subscription_payment_method( 'N/A', $mock_subscription ) );

		// MasterCard ending in 1234
		$mock_payment_method->card->brand = 'mastercard';
		$mock_payment_method->card->last4 = '1234';

		set_transient( $transient_key, [ $mock_payment_method ], DAY_IN_SECONDS );
		$this->assertEquals( 'Via MasterCard card ending in 1234', $this->gateway->maybe_render_subscription_payment_method( 'N/A', $mock_subscription ) );

		// American Express ending in 5678
		$mock_payment_method->card->brand = 'amex';
		$mock_payment_method->card->last4 = '5678';

		set_transient( $transient_key, [ $mock_payment_method ], DAY_IN_SECONDS );
		$this->assertEquals( 'Via Amex card ending in 5678', $this->gateway->maybe_render_subscription_payment_method( 'N/A', $mock_subscription ) );

		// JCB ending in 9012'
		$mock_payment_method->card->brand = 'jcb';
		$mock_payment_method->card->last4 = '9012';

		set_transient( $transient_key, [ $mock_payment_method ], DAY_IN_SECONDS );

		// Unknown card type
		$mock_payment_method->card->brand = 'dummy';
		$mock_payment_method->card->last4 = '0000';

		set_transient( $transient_key, [ $mock_payment_method ], DAY_IN_SECONDS );
		// Card brands that WC core doesn't recognize will be displayed as ucwords.
		$this->assertEquals( 'Via Dummy card ending in 0000', $this->gateway->maybe_render_subscription_payment_method( 'N/A', $mock_subscription ) );
	}

	/**
	 * Tests for `lock_order_payment` method.
	 */
	public function test_lock_order_payment() {
		$order_1 = WC_Helper_Order::create_order();
		$locked  = $this->gateway->lock_order_payment( $order_1 );

		$this->assertFalse( $locked );
		$current_lock = $order_1->get_meta( '_stripe_lock_payment' );
		$this->assertEqualsWithDelta( (int) $current_lock, ( time() + 5 * MINUTE_IN_SECONDS ), 3 );

		$locked = $this->gateway->lock_order_payment( $order_1 );
		$this->assertTrue( $locked );

		// lock with an intent ID.
		$order_2   = WC_Helper_Order::create_order();
		$intent_id = 'pi_123intent';

		$locked       = $this->gateway->lock_order_payment( $order_2, $intent_id );
		$current_lock = $order_2->get_meta( '_stripe_lock_payment' );

		$this->assertFalse( $locked );
		$locked = $this->gateway->lock_order_payment( $order_2, $intent_id );
		$this->assertTrue( $locked );
		$locked = $this->gateway->lock_order_payment( $order_2 ); // test that you don't need to pass the intent ID to check lock.
		$this->assertTrue( $locked );

		// test expired locks.
		$order_3 = WC_Helper_Order::create_order();
		$order_3->update_meta_data( '_stripe_lock_payment', time() - 1 );
		$order_3->save_meta_data();

		$locked       = $this->gateway->lock_order_payment( $order_3, $intent_id );
		$current_lock = $order_3->get_meta( '_stripe_lock_payment' );

		$this->assertFalse( $locked );
		$this->assertEqualsWithDelta( (int) $current_lock, ( time() + 5 * MINUTE_IN_SECONDS ), 3 );

		// test two instances of the same order, one locked and one not.
		$order_4   = WC_Helper_Order::create_order();
		$dup_order = wc_get_order( $order_4->get_id() );

		$this->gateway->lock_order_payment( $order_4 );
		$dup_locked = $this->gateway->lock_order_payment( $dup_order );
		$this->assertTrue( $dup_locked ); // Confirms lock from $order_4 prevents payment on $dup_order.
	}
}
