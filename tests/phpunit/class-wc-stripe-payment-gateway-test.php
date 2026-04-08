<?php

use PHPUnit\Framework\MockObject\MockObject;

/**
 * These tests make assertions against abstract class WC_Stripe_Payment_Gateway
 */
class WC_Stripe_Payment_Gateway_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Stripe Gateway under test.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->gateway = new WC_Stripe_UPE_Payment_Gateway();

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD ] );
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
		$order  = WC_Helper_Order::create_order();
		$intent = $this->gateway->get_intent_from_order( $order );
		$this->assertFalse( $intent );
	}

	/**
	 * Tests if payment intent is fetched from Stripe API.
	 */
	public function test_success_get_payment_intent_from_order() {
		$order = WC_Helper_Order::create_order();

		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, 'pi_123' );

		$expected_intent = (object) [ 'id' => 'pi_123' ];
		$callback        = function ( $preempt, $request_args, $url ) use ( $expected_intent ) {
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

		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, 'pi_123' );

		$response_error = (object) [
			'error' => [
				'code'    => 'resource_missing',
				'message' => 'error_message',
			],
		];
		$callback       = function ( $preempt, $request_args, $url ) use ( $response_error ) {
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
		\WC_Stripe_Database_Cache::delete( \WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Test for `are_keys_set`.
	 *
	 * @param bool   $testmode       Whether the gateway is in test mode.
	 * @param string $publishable_key The publishable key.
	 * @param string $secret_key      The secret key.
	 * @param bool   $expected        Whether the keys should be considered set.
	 * @return void
	 * @dataProvider provide_test_are_keys_set
	 */
	public function test_are_keys_set( bool $testmode, string $publishable_key, string $secret_key, bool $expected ) {
		$this->gateway->testmode        = $testmode;
		$this->gateway->publishable_key = $publishable_key;
		$this->gateway->secret_key      = $secret_key;

		$this->assertSame( $expected, $this->gateway->are_keys_set() );
	}

	/**
	 * Data provider for `test_are_keys_set`.
	 *
	 * @return array
	 */
	public function provide_test_are_keys_set(): array {
		return [
			'test mode with valid keys'   => [ true, 'pk_test_key', 'sk_test_key', true ],
			'test mode with invalid keys' => [ true, 'pk_invalid_key', 'sk_invalid_key', false ],
			'live mode with valid keys'   => [ false, 'pk_live_key', 'sk_live_key', true ],
			'live mode with invalid keys' => [ false, 'pk_invalid_key', 'sk_invalid_key', false ],
		];
	}

	public function test_is_available_returns_true_in_live_mode_with_ssl() {
		$this->gateway->testmode        = false;
		$this->gateway->enabled         = 'yes';
		$this->gateway->publishable_key = 'pk_live_key';
		$this->gateway->secret_key      = 'sk_live_key';

		// Mocking the card payment method to be available and enabled, as UPE checks for that in is_available().
		$mocked_card_pm = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_CC::class )
			->disableOriginalConstructor()
			->getMock();

		$mocked_card_pm->method( 'is_available' )
			->willReturn( true );

		$mocked_card_pm->method( 'is_enabled' )
			->willReturn( true );

		$this->gateway->payment_methods = [
			WC_Stripe_Payment_Methods::CARD => $mocked_card_pm,
		];

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

		// Mocking the card payment method to be available and enabled, as UPE checks for that in is_available().
		$mocked_card_pm = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_CC::class )
			->disableOriginalConstructor()
			->getMock();

		$mocked_card_pm->method( 'is_available' )
			->willReturn( true );

		$mocked_card_pm->method( 'is_enabled' )
			->willReturn( true );

		$this->gateway->payment_methods = [
			WC_Stripe_Payment_Methods::CARD => $mocked_card_pm,
		];

		// Using this to manipulate is_ssl().
		$_SERVER['HTTPS'] = false;

		$this->assertTrue( $this->gateway->is_available() );
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

		$gateway = new WC_Stripe_UPE_Payment_Gateway();
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
	 * Create a partial mock for WC_Stripe_UPE_Payment_Gateway class.
	 *
	 * @param array $methods Method names that need to be mocked.
	 * @return MockObject|WC_Stripe_UPE_Payment_Gateway
	 */
	private function get_partial_mock_for_gateway( array $methods = [] ) {
		return $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
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

		$charge_non_expanded = (object) [
			'id'                  => 'ch_test123',
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

	public function provide_test_render_subscription_payment_method_cases(): array {
		return [
			'VISA card ending in 4242'                => [
				'payment_method_type'   => 'card',
				'payment_method_fields' => [
					'brand' => 'visa',
					'last4' => '4242',
				],
				'expected_result'       => 'Via Visa card ending in 4242',
			],
			'MasterCard ending in 1234'               => [
				'payment_method_type'   => 'card',
				'payment_method_fields' => [
					'brand' => 'mastercard',
					'last4' => '1234',
				],
				'expected_result'       => 'Via MasterCard card ending in 1234',
			],
			'American Express card ending in 5678'    => [
				'payment_method_type'   => 'card',
				'payment_method_fields' => [
					'brand' => 'amex',
					'last4' => '5678',
				],
				'expected_result'       => 'Via Amex card ending in 5678',
			],
			'JCB card ending in 9012'                 => [
				'payment_method_type'   => 'card',
				'payment_method_fields' => [
					'brand' => 'jcb',
					'last4' => '9012',
				],
				'expected_result'       => 'Via JCB card ending in 9012',
			],
			'Unknown card type ending in 0000'        => [
				'payment_method_type'   => 'card',
				'payment_method_fields' => [
					'brand' => 'dummy',
					'last4' => '0000',
				],
				'expected_result'       => 'Via Dummy card ending in 0000',
			],
			'SEPA Debit ending in 1234'               => [
				'payment_method_type'   => 'sepa_debit',
				'payment_method_fields' => [
					'last4' => '1234',
				],
				'expected_result'       => 'Via SEPA Direct Debit ending in 1234',
			],
			'Cash App Pay with cashtag TEST321'       => [
				'payment_method_type'   => 'cashapp',
				'payment_method_fields' => [
					'cashtag' => 'TEST321',
				],
				'expected_result'       => 'Via Cash App Pay (TEST321)',
			],
			'Stripe Link with email test@example.com' => [
				'payment_method_type'   => 'link',
				'payment_method_fields' => [
					'email' => 'test@example.com',
				],
				'expected_result'       => 'Via Stripe Link (test@example.com)',
			],
			'ACH checking ending in 1357'             => [
				'payment_method_type'   => 'us_bank_account',
				'payment_method_fields' => [
					'account_type' => 'checking',
					'last4'        => '1357',
				],
				'expected_result'       => 'Via Checking Account ending in 1357',
			],
			'ACH savings ending in 2468'              => [
				'payment_method_type'   => 'us_bank_account',
				'payment_method_fields' => [
					'account_type' => 'savings',
					'last4'        => '2468',
				],
				'expected_result'       => 'Via Savings Account ending in 2468',
			],
			'BECS Debit ending in 3579'               => [
				'payment_method_type'   => 'au_becs_debit',
				'payment_method_fields' => [
					'last4' => '3579',
				],
				'expected_result'       => 'BECS Direct Debit ending in 3579',
			],
			'ACSS Debit ending in 4680'               => [
				'payment_method_type'   => 'acss_debit',
				'payment_method_fields' => [
					'bank_name' => 'Test Bank',
					'last4'     => '4680',
				],
				'expected_result'       => 'Via Test Bank ending in 4680',
			],
			'BACS Debit ending in 5791'               => [
				'payment_method_type'   => 'bacs_debit',
				'payment_method_fields' => [
					'last4' => '5791',
				],
				'expected_result'       => 'Via Bacs Direct Debit ending in (5791)',
			],
			'Amazon Pay with email test@example.com'  => [
				'payment_method_type'   => 'amazon_pay',
				'payment_method_fields' => [],
				'expected_result'       => 'Via Amazon Pay (test@example.com)',
				'additional_fields'     => [
					'billing_details' => [
						'email' => 'test@example.com',
					],
				],
			],
			'Unknown payment method'                  => [
				'payment_method_type'   => 'unknown',
				'payment_method_fields' => [],
				'expected_result'       => 'N/A',
			],
			'Payment method with customer mismatch'   => [
				'payment_method_type'   => 'card',
				'payment_method_fields' => [
					'brand' => 'visa',
					'last4' => '9753',
				],
				'expected_result'       => 'N/A',
				'additional_fields'     => [
					'customer' => 'cus_other',
				],
			],
		];
	}

	/**
	 * Tests for Card brand and last 4 digits are displayed correctly for subscription.
	 *
	 * @see WC_Stripe_Subscriptions_Trait::maybe_render_subscription_payment_method()
	 * @dataProvider provide_test_render_subscription_payment_method_cases
	 */
	public function test_render_subscription_payment_method( string $payment_method_type, array $payment_method_fields, string $expected_result, ?array $additional_fields = null ) {
		$mock_subscription = WC_Helper_Order::create_order(); // We can use an order as a subscription.
		$mock_subscription->set_payment_method( 'stripe' );

		static $mock_payment_method_id_counter = 0;
		++$mock_payment_method_id_counter;

		$id_suffix              = isset( $payment_method_fields['last4'] ) ? $payment_method_fields['last4'] : (string) $mock_payment_method_id_counter;
		$mock_payment_method_id = 'pm_mock' . $payment_method_type . '_' . $id_suffix;

		$mock_subscription->update_meta_data( '_stripe_source_id', $mock_payment_method_id );
		$mock_subscription->update_meta_data( '_stripe_customer_id', 'cus_mock' );
		$mock_subscription->save();

		$mock_payment_method_data                         = [
			'id'       => $mock_payment_method_id,
			'type'     => $payment_method_type,
			'customer' => 'cus_mock',
		];
		$mock_payment_method_data[ $payment_method_type ] = $payment_method_fields;

		if ( is_array( $additional_fields ) ) {
			$mock_payment_method_data = array_merge( $mock_payment_method_data, $additional_fields );
		}

		$expected_url = '/v1/payment_methods/' . $mock_payment_method_id;

		// Mock the Stripe API payment method response
		$mock_payment_method_api = function ( $preempt, $request_args, $url ) use ( $expected_url, $mock_payment_method_data ) {
			if ( str_ends_with( $url, $expected_url ) ) {
				$response = [
					'headers'  => [],
					'body'     => wp_json_encode( $mock_payment_method_data ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
				return $response;
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $mock_payment_method_api, 10, 3 );

		$result = $this->gateway->maybe_render_subscription_payment_method( 'N/A', $mock_subscription );

		remove_filter( 'pre_http_request', $mock_payment_method_api );

		$this->assertEquals( $expected_result, $result );
	}

	/**
	 * Tests zero amount refunds.
	 */
	public function test_process_refund_on_zero_amount() {
		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( 'ch_123' ); // Set the charge ID as transaction ID
		$this->updateOrderMeta( $order, '_stripe_charge_captured', 'yes' );
		$order->save();
		$order_id = $order->get_id();

		$result = $this->gateway->process_refund( $order_id, 0 );
		$this->assertSame( true, $result );
	}

	/**
	 * Tests that process_refund returns false for negative amounts.
	 */
	public function test_process_refund_fails_on_negative_amount() {
		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( 'ch_123' );
		$order->save();
		$order_id = $order->get_id();

		$result = $this->gateway->process_refund( $order_id, -10 );
		$this->assertSame( null, $result );
	}

	/**
	 * Tests successful refund processing with a positive amount.
	 */
	public function test_process_refund_success() {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_transaction_id( 'ch_123' );
		$order->update_meta_data( '_stripe_charge_captured', 'yes' );
		$order->save();
		$order_id = $order->get_id();

		// Mock the Stripe API refund response
		$callback = function ( $preempt, $request_args, $url ) {
			if ( strpos( $url, 'refunds' ) !== false ) {
				$response = [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'       => 're_123',
							'object'   => 'refund',
							'amount'   => 1000, // $10.00
							'currency' => 'usd',
							'charge'   => 'ch_123',
							'status'   => 'succeeded',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
				return $response;
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		$result = $this->gateway->process_refund( $order_id, 10.00, 'Customer requested' );
		$this->assertTrue( $result );

		remove_filter( 'pre_http_request', $callback );
	}

	/**
	 * Tests that process_refund voids pre-authorization on cancel (uncaptured charge).
	 */
	public function test_process_refund_voids_pre_auth_on_cancel() {
		$order = WC_Helper_Order::create_order();

		$order->set_transaction_id( 'ch_123' );
		$this->updateOrderMeta( $order, '_stripe_charge_captured', 'no' );
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, 'pi_123' );
		$order->save();
		$order_id = $order->get_id();

		// Mock the Stripe API to simulate a successful void/cancel
		$callback = function ( $preempt, $request_args, $url ) {
			// Simulate a PaymentIntent cancel or charge refund for pre-auth
			if ( strpos( $url, 'payment_intents' ) !== false || strpos( $url, 'cancel' ) !== false ) {
				$response = [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'            => 'pi_123',
							'object'        => 'payment_intent',
							'status'        => 'requires_capture',
							'latest_charge' => 'ch_123',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
				return $response;
			}
			if ( strpos( $url, 'charges' ) !== false ) {
				$response = [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'id'      => 'ch_123',
							'object'  => 'charge',
							'status'  => 'succeeded',
							'refunds' => [
								'data' => [
									[
										'id'     => 're_123',
										'amount' => 1000,
										'status' => 'succeeded',
									],
								],
							],
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
				return $response;
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $callback, 10, 3 );

		// Should not return early, should attempt to void pre-auth
		$result = $this->gateway->process_refund( $order_id );

		// For uncaptured charges, process_refund returns false if refund was initiated by changing order status
		$this->assertFalse( $result );

		remove_filter( 'pre_http_request', $callback );
	}

	/**
	 * Tests for `payment_icons`.
	 *
	 * @param bool   $optimized_checkout_enabled Whether Optimized Checkout is enabled.
	 * @param mixed  $filter                     The filter to apply.
	 * @param array  $expected                   The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_payment_icons
	 */
	public function test_payment_icons( $optimized_checkout_enabled, $filter, $expected ) {
		if ( $optimized_checkout_enabled ) {
			OC_Test_Helper::enable_oc();
		}

		if ( $filter ) {
			add_filter( 'wc_stripe_payment_icons', $filter );
		}

		$gateway = new WC_Stripe_UPE_Payment_Gateway();
		$actual  = $gateway->payment_icons();
		// Clean up
		OC_Test_Helper::disable_oc();
		if ( $filter ) {
			remove_filter( 'wc_stripe_payment_icons', $filter );
		}

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_payment_icons`.
	 *
	 * @return array[]
	 */
	public function provide_test_payment_icons() {
		$mocked_filter = function () {
			return [];
		};

		return [
			'default'                    => [
				'optimized checkout enabled' => false,
				'filter'                     => null,
				'expected'                   => [
					'us_bank_account' => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="ACH" />',
					'acss_debit'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="Pre-Authorized Debit" />',
					'alipay'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/alipay.svg" class="stripe-alipay-icon stripe-icon" alt="Alipay" />',
					'au_becs_debit'   => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="BECS Direct Debit" />',
					'blik'            => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/blik.svg" class="stripe-blik-icon stripe-icon" alt="BLIK" />',
					'wechat_pay'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/wechat.svg" class="stripe-wechat-icon stripe-icon" alt="Wechat Pay" />',
					'bancontact'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bancontact.svg" class="stripe-bancontact-icon stripe-icon" alt="Bancontact" />',
					'ideal'           => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/ideal-wero.svg" class="stripe-ideal-icon stripe-icon" alt="' . esc_attr__( 'iDEAL | Wero', 'woocommerce-gateway-stripe' ) . '" />',
					'p24'             => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/p24.svg" class="stripe-p24-icon stripe-icon" alt="P24" />',
					'giropay'         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/giropay.svg" class="stripe-giropay-icon stripe-icon" alt="giropay" />',
					'klarna'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/klarna.svg" class="stripe-klarna-icon stripe-icon" alt="Klarna" />',
					'affirm'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/affirm.svg" class="stripe-affirm-icon stripe-icon" alt="Affirm" />',
					'eps'             => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/eps.svg" class="stripe-eps-icon stripe-icon" alt="EPS" />',
					'multibanco'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/multibanco.svg" class="stripe-multibanco-icon stripe-icon" alt="Multibanco" />',
					'sofort'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sofort.svg" class="stripe-sofort-icon stripe-icon" alt="Sofort" />',
					'sepa'            => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sepa.svg" class="stripe-sepa-icon stripe-icon" alt="SEPA" />',
					'boleto'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/boleto.svg" class="stripe-boleto-icon stripe-icon" alt="Boleto" />',
					'oxxo'            => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/oxxo.svg" class="stripe-oxxo-icon stripe-icon" alt="OXXO" />',
					'cards'           => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/cards.svg" class="stripe-cards-icon stripe-icon" alt="Credit / Debit Card" />',
					'cashapp'         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/cashapp.svg" class="stripe-cashapp-icon stripe-icon" alt="Cash App Pay" />',
				],
			],
			'Optimized Checkout enabled' => [
				'optimized checkout enabled' => true,
				'filter'                     => null,
				'expected'                   => [
					'us_bank_account' => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="ACH" />',
					'acss_debit'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="Pre-Authorized Debit" />',
					'alipay'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/alipay.svg" class="stripe-alipay-icon stripe-icon" alt="Alipay" />',
					'au_becs_debit'   => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="BECS Direct Debit" />',
					'blik'            => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/blik.svg" class="stripe-blik-icon stripe-icon" alt="BLIK" />',
					'wechat_pay'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/wechat.svg" class="stripe-wechat-icon stripe-icon" alt="Wechat Pay" />',
					'bancontact'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bancontact.svg" class="stripe-bancontact-icon stripe-icon" alt="Bancontact" />',
					'ideal'           => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/ideal-wero.svg" class="stripe-ideal-icon stripe-icon" alt="' . esc_attr__( 'iDEAL | Wero', 'woocommerce-gateway-stripe' ) . '" />',
					'p24'             => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/p24.svg" class="stripe-p24-icon stripe-icon" alt="P24" />',
					'giropay'         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/giropay.svg" class="stripe-giropay-icon stripe-icon" alt="giropay" />',
					'klarna'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/klarna.svg" class="stripe-klarna-icon stripe-icon" alt="Klarna" />',
					'affirm'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/affirm.svg" class="stripe-affirm-icon stripe-icon" alt="Affirm" />',
					'eps'             => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/eps.svg" class="stripe-eps-icon stripe-icon" alt="EPS" />',
					'multibanco'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/multibanco.svg" class="stripe-multibanco-icon stripe-icon" alt="Multibanco" />',
					'sofort'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sofort.svg" class="stripe-sofort-icon stripe-icon" alt="Sofort" />',
					'sepa'            => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sepa.svg" class="stripe-sepa-icon stripe-icon" alt="SEPA" />',
					'boleto'          => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/boleto.svg" class="stripe-boleto-icon stripe-icon" alt="Boleto" />',
					'oxxo'            => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/oxxo.svg" class="stripe-oxxo-icon stripe-icon" alt="OXXO" />',
					'cards'           => '',
					'cashapp'         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/cashapp.svg" class="stripe-cashapp-icon stripe-icon" alt="Cash App Pay" />',
				],
			],
			'filter applied'             => [
				'optimized checkout enabled' => false,
				'filter'                     => $mocked_filter,
				'expected'                   => [],
			],
		];
	}

	/**
	 * Test that non-retryable error codes return false
	 *
	 * @dataProvider non_retryable_error_codes_provider
	 */
	public function test_non_retryable_error_codes_return_false( $error_code ) {
		$error       = new \stdClass();
		$error->code = $error_code;
		$error->type = 'invalid_request_error';

		$result = $this->gateway->is_retryable_error( $error );

		$this->assertFalse( $result, "Error code '{$error_code}' should not be retryable" );
	}

	/**
	 * Data provider for non-retryable error codes
	 */
	public function non_retryable_error_codes_provider() {
		return [
			'payment_intent_mandate_invalid'   => [ 'payment_intent_mandate_invalid' ],
			'charge_exceeds_transaction_limit' => [ 'charge_exceeds_transaction_limit' ],
			'amount_too_small'                 => [ 'amount_too_small' ],
			'card_declined'                    => [ 'card_declined' ],
			'payment_method_provider_decline'  => [ 'payment_method_provider_decline' ],
		];
	}

	/**
	 * Test that retryable error types return true
	 *
	 * @dataProvider retryable_error_types_provider
	 */
	public function test_retryable_error_types_return_true( $error_type ) {
		$error       = new \stdClass();
		$error->type = $error_type;

		$result = $this->gateway->is_retryable_error( $error );

		$this->assertTrue( $result, "Error type '{$error_type}' should be retryable" );
	}

	/**
	 * Data provider for retryable error types
	 */
	public function retryable_error_types_provider() {
		return [
			'invalid_request_error' => [ 'invalid_request_error' ],
			'idempotency_error'     => [ 'idempotency_error' ],
			'rate_limit_error'      => [ 'rate_limit_error' ],
			'api_connection_error'  => [ 'api_connection_error' ],
			'api_error'             => [ 'api_error' ],
		];
	}

	/**
	 * Test that invalid_request_error with non-blocked error codes returns true
	 *
	 * This explicitly tests the case where we have an invalid_request_error type
	 * with an error code that is NOT in the non-retryable codes list.
	 */
	public function test_invalid_request_error_with_non_blocked_code_is_retryable() {
		$error       = new \stdClass();
		$error->type = 'invalid_request_error';
		$error->code = 'non_existent_code';

		$result = $this->gateway->is_retryable_error( $error );

		$this->assertTrue( $result, 'invalid_request_error with non-blocked code should be retryable' );
	}

	/**
	 * Tests for the `disable_subscription_edit_for_india` method.
	 *
	 * @param bool   $is_subscription            Whether the order is a subscription.
	 * @param bool   $is_subscriptions_edit_page Whether the current page is the subscriptions edit page.
	 * @param bool   $has_parent_order           Whether the subscription has a parent order.
	 * @param string $parent_mandate_id          The mandate ID of the parent order (if applicable).
	 * @param array  $payment_method             The payment method data to mock.
	 * @param bool   $expected                   The expected result.
	 * @return void
	 * @dataProvider provide_test_disable_subscription_edit_for_india
	 * @see \WC_Stripe_Subscriptions_Trait::disable_subscription_edit_for_india()
	 */
	public function test_disable_subscription_edit_for_india(
		bool $is_subscription,
		bool $is_subscriptions_edit_page,
		bool $has_parent_order,
		string $parent_mandate_id,
		array $payment_method,
		bool $expected
	): void {
		$subscription = new \WC_Subscription();
		$subscription->update_meta_data( '_stripe_source_id', $payment_method['id'] ?? '' );
		$subscription->save_meta_data();
		$subscription->save();

		if ( $has_parent_order ) {
			$order = WC_Helper_Order::create_order();
			if ( $parent_mandate_id ) {
				$order->update_meta_data( '_stripe_mandate_id', $parent_mandate_id );
				$order->save_meta_data();
			}
			$subscription->set_parent_id( $order->get_id() );
			$subscription->save();
		}

		WC_Subscriptions_Helpers::$wcs_is_subscription = $is_subscription;

		if ( $is_subscriptions_edit_page ) {
			$_REQUEST = [
				'post'   => $subscription,
				'action' => 'edit',
			];
		}

		// Mock response from Stripe API using request arguments.
		$mock_request = function ( $preempt, $parsed_args, $url ) use ( $payment_method ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/payment_methods/' ) ) {
				return $preempt;
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( $payment_method ),
			];
		};

		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		$actual = $this->gateway->disable_subscription_edit_for_india( true, $subscription );

		// Clean up.
		if ( ! empty( $payment_method['id'] ) ) {
			\WC_Stripe_Database_Cache::delete( 'payment_method_for_source_' . $payment_method['id'] );
		}
		remove_filter( 'pre_http_request', $mock_request, 10, 3 );
		WC_Subscriptions_Helpers::$wcs_is_subscription = null;
		unset( $_REQUEST );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_disable_subscription_edit_for_india` method.
	 *
	 * @return array
	 */
	public function provide_test_disable_subscription_edit_for_india(): array {
		return [
			'not a subscription'               => [
				'is subscription'   => false,
				'edit page'         => false,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [
					'id'   => 'pm_789',
					'type' => 'card',
					'card' => [
						'country' => 'IN',
					],
				],
				'expected'          => true,
			],
			'not subscriptions edit page'      => [
				'is subscription'   => true,
				'edit page'         => false,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [
					'id'   => 'pm_789',
					'type' => 'card',
					'card' => [
						'country' => 'IN',
					],
				],
				'expected'          => true,
			],
			'missing parent order'             => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => false,
				'parent mandate ID' => '',
				'payment method'    => [],
				'expected'          => true,
			],
			'parent order lacks mandate ID'    => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => true,
				'parent mandate ID' => '',
				'payment method'    => [],
				'expected'          => true,
			],
			'missing payment method ID meta'   => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [],
				'expected'          => true,
			],
			'payment method is sepa debit'     => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [
					'id'         => 'pm_123',
					'type'       => 'sepa_debit',
					'sepa_debit' => [
						'amount_type'     => '',
						'supported_types' => [],
					],
				],
				'expected'          => true,
			],
			'method is card, but not indian'   => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [
					'id'   => 'pm_456',
					'type' => 'card',
					'card' => [
						'country' => 'US',
					],
				],
				'expected'          => true,
			],
			'method is indian card'            => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [
					'id'   => 'pm_789',
					'type' => 'card',
					'card' => [
						'country' => 'IN',
					],
				],
				'expected'          => false,
			],
			'method is indian Google Pay card' => [
				'is subscription'   => true,
				'edit page'         => true,
				'has parent order'  => true,
				'parent mandate ID' => 'mandate_123',
				'payment method'    => [
					'id'     => 'pm_789',
					'type'   => 'card',
					'card'   => [
						'country' => 'IN',
					],
					'wallet' => [
						'type' => 'google_pay',
					],
				],
				'expected'          => false,
			],
		];
	}

	/**
	 * @dataProvider provide_update_fees_scenarios
	 */
	public function test_update_fees( $existing_fee, $existing_net, $api_fee, $api_net, $replace, $expected_fee, $expected_net ) {
		$order        = WC_Helper_Order::create_order();
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		if ( 0 !== $existing_fee ) {
			$order_helper->update_stripe_fee( $order, $existing_fee );
		}
		if ( 0 !== $existing_net ) {
			$order_helper->update_stripe_net( $order, $existing_net );
		}

		// Mock the Stripe API balance transaction response.
		$mock_response = [
			'headers'  => [],
			'body'     => wp_json_encode(
				[
					'fee'      => $api_fee,
					'net'      => $api_net,
					'currency' => 'usd',
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];

		$filter = function () use ( $mock_response ) {
			return $mock_response;
		};
		add_filter( 'pre_http_request', $filter );

		$this->gateway->update_fees( $order, 'txn_test123', $replace );

		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( $expected_fee, (float) $order_helper->get_stripe_fee( $order ) );
		$this->assertEquals( $expected_net, (float) $order_helper->get_stripe_net( $order ) );
	}

	/**
	 * Tests that update_fees leaves existing meta intact when the API returns an error.
	 */
	public function test_update_fees_with_api_error_leaves_meta_unchanged() {
		$order        = WC_Helper_Order::create_order();
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$order_helper->update_stripe_fee( $order, 1.50 );
		$order_helper->update_stripe_net( $order, 48.50 );

		$mock_response = [
			'headers'  => [],
			'body'     => wp_json_encode(
				[
					'error' => [
						'type'    => 'invalid_request_error',
						'message' => 'No such balance transaction',
					],
				]
			),
			'response' => [
				'code'    => 404,
				'message' => 'Not Found',
			],
		];

		$filter = function () use ( $mock_response ) {
			return $mock_response;
		};
		add_filter( 'pre_http_request', $filter );

		$this->gateway->update_fees( $order, 'txn_invalid', true );

		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( 1.50, (float) $order_helper->get_stripe_fee( $order ) );
		$this->assertEquals( 48.50, (float) $order_helper->get_stripe_net( $order ) );
	}

	public function provide_update_fees_scenarios() {
		// API fee/net values are in cents (Stripe smallest denomination).
		// format_balance_fee() converts to dollars (divides by 100 for USD).
		// Existing and expected values are in dollars (already formatted).
		return [
			'add mode - refund adjusts existing fees'       => [
				'existing_fee' => 1.50,
				'existing_net' => 48.50,
				'api_fee'      => -30,
				'api_net'      => -970,
				'replace'      => false,
				'expected_fee' => 1.20,
				'expected_net' => 38.80,
			],
			'replace mode - capture replaces existing fees' => [
				'existing_fee' => 1.50,
				'existing_net' => 48.50,
				'api_fee'      => 75,
				'api_net'      => 2425,
				'replace'      => true,
				'expected_fee' => 0.75,
				'expected_net' => 24.25,
			],
			'replace mode - works with no existing fees'    => [
				'existing_fee' => 0,
				'existing_net' => 0,
				'api_fee'      => 50,
				'api_net'      => 950,
				'replace'      => true,
				'expected_fee' => 0.50,
				'expected_net' => 9.50,
			],
			'add mode - first time fee setting'             => [
				'existing_fee' => 0,
				'existing_net' => 0,
				'api_fee'      => 100,
				'api_net'      => 4900,
				'replace'      => false,
				'expected_fee' => 1.00,
				'expected_net' => 49.00,
			],
		];
	}
}
