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

		$this->order           = WC_Helper_Order::create_order();
		$this->gateway         = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
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
	 * @param string|null $expected_exception Expected exception.
	 * @return void
	 * @dataProvider provide_test_update_and_confirm_payment_intent
	 * @throws WC_Stripe_Exception If invalid payment method type is passed.
	 */
	public function test_update_and_confirm_payment_intent( $payment_information, $payment_intent, $expected_exception = null ) {
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
		$this->assertNull( $actual );
	}

	/**
	 * Provider for `test_update_and_confirm_payment_intent` method.
	 *
	 * @return array
	 */
	public function provide_test_update_and_confirm_payment_intent() {
		$payment_information_missing_params = [
			'capture_method'        => 'automatic',
			'shipping'              => [],
			'selected_payment_type' => 'card',
			'payment_method_types'  => [ 'card' ],
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
				'expected exception'  => WC_Stripe_Exception::class,
			],
			'payment intent error' => [
				'payment information' => $payment_information_regular,
				'payment intent'      => $payment_intent_error,
				'expected exception'  => WC_Stripe_Exception::class,
			],
			'success'              => [
				'payment information' => $payment_information_regular,
				'payment intent'      => (object) $payment_intent_regular,
			],
		];
	}
}
