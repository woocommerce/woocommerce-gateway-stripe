<?php

/**
 * These tests make assertions against class WC_Stripe_Intent_Controller
 */
class WC_Stripe_Intent_Controller_Test extends WP_UnitTestCase {
	/**
	 * Mocked controller under test.
	 *
	 * @var WC_Stripe_Intent_Controller
	 */
	private $mock_controller;

	/**
	 * Gateway
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Order
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$mock_account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->getMock();

		$this->order           = WC_Helper_Order::create_order();
		$this->gateway         = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->setConstructorArgs( [ $mock_account ] )
			->setMethods( [ 'maybe_process_upe_redirect', 'has_subscription' ] )
			->getMock();
		$this->mock_controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();
		$this->mock_controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $this->gateway );
		$this->gateway->expects( $this->any() )
			->method( 'has_subscription' )
			->willReturn( true );
	}

	public function test_wether_default_capture_method_is_set_in_the_intent() {
		$test_request = function ( $preempt, $parsed_args, $url ) {
			$this->assertArrayHasKey( 'capture_method', $parsed_args['body'] );
			$this->assertEquals( 'automatic', $parsed_args['body']['capture_method'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 1,
						'client_secret' => '123',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_payment_intent( $this->order->get_id() );
	}

	public function test_manual_capture_from_the_settings() {
		$this->gateway->settings['capture'] = 'no';
		$test_request                       = function ( $preempt, $parsed_args, $url ) {
			$this->assertArrayHasKey( 'capture_method', $parsed_args['body'] );
			$this->assertEquals( 'manual', $parsed_args['body']['capture_method'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 1,
						'client_secret' => '123',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_payment_intent( $this->order->get_id() );
	}

	public function test_automatic_capture_from_the_settings() {
		$this->gateway->settings['capture'] = 'yes';
		$test_request                       = function ( $preempt, $parsed_args, $url ) {
			$this->assertArrayHasKey( 'capture_method', $parsed_args['body'] );
			$this->assertEquals( 'automatic', $parsed_args['body']['capture_method'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 1,
						'client_secret' => '123',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_payment_intent( $this->order->get_id() );
	}

	/**
	 * Test for `update_and_confirm_payment_intent` method.
	 *
	 * @param array $payment_information Payment information.
	 * @param object $payment_intent Payment intent.
	 * @param string|null $expected Expected result.
	 * @param string|null $expected_exception Expected exception.
	 * @return void
	 * @dataProvider provide_test_update_and_confirm_payment_intent
	 * @throws WC_Stripe_Exception If invalid payment method type is passed.
	 */
	public function test_update_and_confirm_payment_intent( $payment_information, $payment_intent, $expected = null, $expected_exception = null ) {
		$payment_information = array_merge( $payment_information, [ 'order' => $this->order ] );

		if ( $expected_exception ) {
			$this->expectException( $expected_exception );
		}

		$test_request = function () use ( $payment_intent ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( $payment_intent ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$actual = $this->mock_controller->update_and_confirm_payment_intent( $payment_intent, $payment_information );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Provider for `test_update_and_confirm_payment_intent` method.
	 *
	 * @return array
	 */
	public function provide_test_update_and_confirm_payment_intent() {
		$payment_information_missing_params = [
			'capture_method'               => 'automatic',
			'shipping'                     => [],
			'selected_payment_type'        => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'         => [ WC_Stripe_Payment_Methods::CARD ],
			'level3'                       => [
				'line_items' => [
					[
						'product_code'        => '123',
						'product_description' => 'test',
						'unit_cost'           => 100,
						'quantity'            => 1,
					],
				],
			],
			'save_payment_method_to_store' => true,
		];

		$payment_information_regular = array_merge(
			$payment_information_missing_params,
			[
				'payment_method' => 'pm_123',
			]
		);

		$payment_intent_regular = [ 'id' => 'pi_123' ];
		$payment_intent_error   = (object) array_merge(
			$payment_intent_regular,
			[
				'error' => (object) [
					'message' => 'error',
				],
			]
		);
		return [
			'missing params'       => [
				'payment information' => $payment_information_missing_params,
				'payment intent'      => (object) $payment_intent_regular,
				'expected'            => null,
				'expected exception'  => WC_Stripe_Exception::class,
			],
			'payment intent error' => [
				'payment information' => $payment_information_regular,
				'payment intent'      => $payment_intent_error,
				'expected'            => $payment_intent_error,
			],
			'success'              => [
				'payment information' => $payment_information_regular,
				'payment intent'      => (object) $payment_intent_regular,
				'expected'            => (object) $payment_intent_regular,
			],
		];
	}

	/**
	 * Test for setting the `setup_future_usage` parameter in the
	 *  create_and_confirm_payment_intent intent creation request.
	 */
	public function test_intent_creation_request_setup_future_usage() {
		$payment_information = [
			'amount'                        => 100,
			'capture_method'                => 'automatic',
			'currency'                      => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			'customer'                      => 'cus_mock',
			'level3'                        => [
				'line_items' => [
					[
						'product_code'        => 'ABC123',
						'product_description' => 'Test Product',
						'unit_cost'           => 100,
						'quantity'            => 1,
					],
				],
			],
			'metadata'                      => [ '_stripe_metadata' => '123' ],
			'order'                         => $this->order,
			'payment_method'                => 'pm_mock',
			'shipping'                      => [],
			'selected_payment_type'         => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'          => [ WC_Stripe_Payment_Methods::CARD ],
			'is_using_saved_payment_method' => false,
		];

		$payment_information['save_payment_method_to_store'] = true;
		$payment_information['has_subscription']             = false;
		$this->check_setup_future_usage_off_session( $payment_information );

		// If order has subscription, setup_future_usage should be off_session,
		// regardless of save_payment_method_to_store, which may be false
		// if using an already saved payment method.
		$payment_information['save_payment_method_to_store'] = false;
		$payment_information['has_subscription']             = true;
		$this->check_setup_future_usage_off_session( $payment_information );
	}

	private function check_setup_future_usage_off_session( $payment_information ) {
		$test_request = function ( $preempt, $parsed_args, $url ) {
			$this->assertEquals( 'off_session', $parsed_args['body']['setup_future_usage'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_and_confirm_payment_intent( $payment_information );
	}

	/**
	 * Test presence of idempotency key when sending the payment intent request.
	 */
	public function test_idempotency_key_for_create_and_confirm_payment_intent() {
		$payment_information = [
			'amount'                        => 100,
			'capture_method'                => 'automatic',
			'currency'                      => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			'customer'                      => 'cus_mock',
			'level3'                        => [
				'line_items' => [
					[
						'product_code'        => 'ABC123',
						'product_description' => 'Test Product',
						'unit_cost'           => 100,
						'quantity'            => 1,
					],
				],
			],
			'metadata'                      => [ '_stripe_metadata' => '123' ],
			'order'                         => $this->order,
			'payment_method'                => 'pm_mock',
			'shipping'                      => [],
			'selected_payment_type'         => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'          => [ WC_Stripe_Payment_Methods::CARD ],
			'is_using_saved_payment_method' => false,
			'save_payment_method_to_store'  => true,
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			$this->assertNotEmpty( $parsed_args['headers']['Idempotency-Key'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_and_confirm_payment_intent( $payment_information );
	}

	/**
	 * Test for create_and_confirm_setup_intent method.
	 */
	public function test_create_and_confirm_setup_intent() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::CARD ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify the request is made to the setup_intents endpoint
			$this->assertStringContainsString( 'setup_intents', $url );

			// Verify required parameters
			$this->assertEquals( 'pm_mock', $parsed_args['body']['payment_method'] );
			$this->assertEquals( 'cus_mock', $parsed_args['body']['customer'] );
			$this->assertEquals( 'true', $parsed_args['body']['confirm'] );
			$this->assertEquals( 'true', $parsed_args['body']['use_stripe_sdk'] );
			// Return URL should not be included for card payment.
			$this->assertArrayNotHasKey( 'return_url', $parsed_args['body'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'succeeded',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'seti_mock', $result->id );
		$this->assertEquals( 'secret_mock', $result->client_secret );
		$this->assertEquals( 'succeeded', $result->status );
	}

	/**
	 * Test that SEPA setup intents include mandate data.
	 */
	public function test_create_and_confirm_setup_intent_with_sepa() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::SEPA_DEBIT,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::SEPA_DEBIT ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify mandate data is included for SEPA
			$this->assertArrayHasKey( 'mandate_data', $parsed_args['body'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'succeeded',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'seti_mock', $result->id );
	}

	/**
	 * Test that Boleto setup intents have delayed confirmation.
	 */
	public function test_create_and_confirm_setup_intent_with_boleto() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::BOLETO,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::BOLETO ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify confirmation is delayed for Boleto
			$this->assertEquals( 'false', $parsed_args['body']['confirm'] );
			// Return URL should not be included when confirm is false
			$this->assertArrayNotHasKey( 'return_url', $parsed_args['body'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'requires_confirmation',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'requires_confirmation', $result->status );
	}

	/**
	 * Test error handling in setup intent creation.
	 */
	public function test_create_and_confirm_setup_intent_error() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'             => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
			'return_url'           => 'https://example.com/return',
			'order'               => $this->order,
			'use_stripe_sdk'      => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'error' => [
							'message' => 'Invalid payment method',
						],
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->expectException( WC_Stripe_Exception::class );
		$this->mock_controller->create_and_confirm_setup_intent( $payment_information );
	}

	/**
	 * Test mandate options for card payment method in setup intent for subscription.
	 */
	public function test_mandate_options_for_card_setup_intent_for_subscription() {
		// create a subscription
		$subscription = new WC_Subscription();
		$subscription->set_status( 'active' );
		$subscription->set_total( 100 );
		$subscription->set_currency( 'USD' );
		$subscription->set_customer_id( 'cus_mock' );
		$subscription->set_payment_method( 'pm_mock' );
		$subscription->save();

		WC_Subscriptions_Switcher::$cart_contains_switches         = false;
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order = [ $subscription ];

		// Manually add the subscription filter that would normally be added by maybe_init_subscriptions()
		add_filter( 'wc_stripe_generate_create_intent_request', [ $this->gateway, 'add_subscription_information_to_intent' ], 10, 4 );

		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::CARD ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $subscription,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify card mandate options are present
			$this->assertArrayHasKey( 'payment_method_options', $parsed_args['body'] );
			$this->assertArrayHasKey( WC_Stripe_Payment_Methods::CARD, $parsed_args['body']['payment_method_options'] );

			// Verify mandate options for card include currency
			$this->assertArrayHasKey( 'mandate_options', $parsed_args['body']['payment_method_options'][ WC_Stripe_Payment_Methods::CARD ] );
			$this->assertArrayHasKey( 'currency', $parsed_args['body']['payment_method_options'][ WC_Stripe_Payment_Methods::CARD ]['mandate_options'] );

			// Verify currency matches order currency
			$this->assertEquals(
				strtolower( $this->order->get_currency() ),
				$parsed_args['body']['payment_method_options'][ WC_Stripe_Payment_Methods::CARD ]['mandate_options']['currency']
			);

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'succeeded',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'succeeded', $result->status );
	}

	/**
	 * Test that rate limiting works after a failed attempt.
	 */
	public function test_rate_limiting_on_consecutive_failed_calls() {
		Ajax_Test_Helper::init_hooks();

		wp_set_current_user( 1 );
		$_POST['wc-stripe-payment-method'] = 'pm_test_123';
		$_POST['wc-stripe-payment-type']   = WC_Stripe_Payment_Methods::CARD;
		// First call with invalid nonce - should fail and trigger rate limiting
		$_POST['_ajax_nonce'] = 'invalid_nonce';

		ob_start();
		$this->mock_controller->create_and_confirm_setup_intent_ajax();
		$output = ob_get_clean();
		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'error', $response['data'] );
		$this->assertEquals( 'Unable to verify your request. Please refresh the page and try again.', $response['data']['error']['message'] );

		// Second call should fail due to rate limiting, regardless of nonce.
		$_POST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_create_and_confirm_setup_intent_nonce' );

		ob_start();
		$this->mock_controller->create_and_confirm_setup_intent_ajax();
		$output = ob_get_clean();

		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'error', $response['data'] );
		$this->assertEquals( 'You cannot add a new payment method so soon after the previous one.', $response['data']['error']['message'] );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Create a subscription and configure the wcs_get_subscription mock to return it.
	 *
	 * @param int $owner_id User ID who owns the subscription.
	 * @return WC_Subscription
	 */
	private function create_mock_subscription( int $owner_id ): WC_Subscription {
		$subscription = new WC_Subscription();
		$subscription->set_customer_id( $owner_id );
		$subscription->set_status( 'active' );
		$subscription->save();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return ( (int) $id === $subscription->get_id() ) ? $subscription : false;
			}
		);

		return $subscription;
	}

	/**
	 * Test that confirm_change_payment rejects requests from users who do not own the subscription.
	 */
	public function test_confirm_change_payment_rejects_non_owner() {
		Ajax_Test_Helper::init_hooks();

		$owner        = $this->factory->user->create( [ 'role' => 'customer' ] );
		$subscription = $this->create_mock_subscription( $owner );

		// Log in as a different user.
		$non_owner = $this->factory->user->create( [ 'role' => 'customer' ] );
		wp_set_current_user( $non_owner );

		$_POST['order_id']       = $subscription->get_id();
		$_POST['intent_id']      = 'seti_mock_123';
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$this->mock_controller->confirm_change_payment_from_setup_intent_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'permission', strtolower( $response['data']['error']['message'] ) );

		WC_Subscriptions::set_wcs_get_subscription( null );
		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Test that confirm_change_payment allows the subscription owner to proceed past the ownership check.
	 */
	public function test_confirm_change_payment_allows_owner(): void {
		Ajax_Test_Helper::init_hooks();

		$owner        = $this->factory->user->create( [ 'role' => 'customer' ] );
		$subscription = $this->create_mock_subscription( $owner );

		wp_set_current_user( $owner );

		$_POST['order_id']       = $subscription->get_id();
		$_POST['intent_id']      = 'seti_mock_123';
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$this->mock_controller->confirm_change_payment_from_setup_intent_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		// Should not fail with a permission error.
		if ( ! $response['success'] ) {
			$this->assertStringNotContainsString( 'permission', strtolower( $response['data']['error']['message'] ) );
		}

		WC_Subscriptions::set_wcs_get_subscription( null );
		Ajax_Test_Helper::remove_hooks();
	}
}
