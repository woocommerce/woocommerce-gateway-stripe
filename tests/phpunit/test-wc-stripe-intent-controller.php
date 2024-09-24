<?php
/**
 * These tests make assertions against class WC_Stripe_Intent_Controller
 *
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
			->setMethods( [ 'maybe_process_upe_redirect' ] )
			->getMock();
		$this->mock_controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();
		$this->mock_controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $this->gateway );
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
			'capture_method'                => 'automattic',
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
}
