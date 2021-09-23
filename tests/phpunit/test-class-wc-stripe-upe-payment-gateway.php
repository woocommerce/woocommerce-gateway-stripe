<?php
/**
 * Unit tests for the UPE payment gateway
 */
class WC_Stripe_UPE_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * Mock UPE Gateway
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $mock_gateway;

	/**
	 * Array mapping Stripe IDs to mock WC_Stripe_UPE_Payment_Methods.
	 *
	 * @var array
	 */
	private $mock_payment_methods;

	/**
	 * Mocked value of return_url.
	 *
	 * @var string
	 */
	const MOCK_RETURN_URL = 'test_url';

	/**
	 * Base template for Stripe card payment method.
	 */
	const MOCK_CARD_PAYMENT_METHOD_TEMPLATE = [
		'type' => 'card',
		'card' => [
			'brand'     => 'visa',
			'network'   => 'visa',
			'exp_month' => '7',
			'funding'   => 'credit',
			'last4'     => '4242',
		],
	];

	/**
	 * Base template for Stripe payment intent.
	 */
	const MOCK_CARD_PAYMENT_INTENT_TEMPLATE = [
		'object'             => 'payment_intent',
		'status'             => 'succeeded',
		'last_payment_error' => [],
		'client_secret'      => 'cs_mock',
		'charges'            => [
			'total_count' => 1,
			'data'        => [
				[
					'id'                     => 'ch_mock',
					'captured'               => true,
					'payment_method_details' => [],
					'status'                 => 'succeeded',
				],
			],
		],
	];

	/**
	 * Initial setup.
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setMethods(
				[
					'generate_payment_request',
					'get_return_url',
					'get_stripe_customer_id',
					'stripe_request',
				]
			)
			->getMock();

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( self::MOCK_RETURN_URL )
			);
	}

	/**
	 * Helper function to set $_POST vars for saved payment method.
	 */
	private function set_postvars_for_saved_payment_method() {
		$token = WC_Helper_Token::create_token( 'pm_mock' );
		$_POST = [
			'payment_method' => WC_Stripe_UPE_Payment_Gateway::ID,
			'wc-' . WC_Stripe_UPE_Payment_Gateway::ID . '-payment-token' => (string) $token->get_id(),
		];
		return $token;
	}

	/**
	 * Helper function to get amount, description, and metadata for Stripe requests.
	 *
	 * @param WC_Order $order Test WC Order.
	 *
	 * @return array
	 */
	private function get_order_details( $order ) {
		$total        = $order->get_total();
		$currency     = $order->get_currency();
		$order_id     = $order->get_id();
		$order_number = $order->get_order_number();
		$order_key    = $order->get_order_key();
		$amount       = WC_Stripe_Helper::get_stripe_amount( $total, $currency );
		$description  = "Test Blog - Order $order_number";
		$metadata     = [
			'customer_name'  => 'Jeroen Sormani',
			'customer_email' => 'admin@example.org',
			'site_url'       => 'http://example.org',
			'order_id'       => $order_id,
			'order_key'      => $order_key,
			'payment_type'   => 'single',
		];
		return [ $amount, $description, $metadata ];
	}

	/**
	 * Test payment fields HTML output.
	 */
	public function test_payment_fields_outputs_fields() {
		$this->mock_gateway->payment_fields();
		$this->expectOutputRegex( '/<div id="wc-stripe-upe-element"><\/div>/' );
	}

	/**
	 * Test basic checkout process_payment flow.
	 */
	public function test_process_payment_returns_valid_response() {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );

		$expected_request = [
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => $description,
			'customer'    => $customer_id,
			'metadata'    => $metadata,
		];

		$_POST = [ 'wc_payment_intent_id' => $payment_intent_id ];

		$this->mock_gateway->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $customer_id )
			);
		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with(
				"payment_intents/$payment_intent_id",
				$expected_request,
				wc_get_order( $order_id )
			)
			->will(
				$this->returnValue( [] )
			);

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertTrue( $response['payment_needed'] );
		$this->assertEquals( $order_id, $response['order_id'] );
		$this->assertRegExp( "/order_id=$order_id/", $response['redirect_url'] );
		$this->assertRegExp( '/wc_payment_method=stripe/', $response['redirect_url'] );
		$this->assertRegExp( '/save_payment_method=no/', $response['redirect_url'] );
	}

	/**
	 * Test basic redirect payment processed correctly.
	 */
	public function test_process_redirect_payment_returns_valid_response() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['charges']['data'][0]['payment_method_details'] = $payment_method_mock;

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					json_decode( wp_json_encode( $payment_intent_mock ) )
				)
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( 'Visa credit card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertRegExp( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test basic checkout with saved payment method.
	 */
	public function test_process_payment_with_saved_method_confirms_intent_immediately() {
		$token = $this->set_postvars_for_saved_payment_method();

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']     = $payment_intent_id;
		$payment_intent_mock['amount'] = $amount;
		$payment_intent_mock['charges']['data'][0]['payment_method_details'] = $payment_method_mock;

		$this->mock_gateway->expects( $this->once() )
			->method( 'generate_payment_request' )
			->will(
				$this->returnValue(
					[
						'description' => $description,
						'metadata'    => $metadata,
						'capture'     => 'true',
					]
				)
			);

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $payment_intent_mock ) )
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertRegExp( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test SCA 3DS flow with saved payment method.
	 */
	public function test_sca_checkout_with_saved_payment_method_redirects_client() {
		$token = $this->set_postvars_for_saved_payment_method();

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']     = $payment_intent_id;
		$payment_intent_mock['amount'] = $amount;
		$payment_intent_mock['status'] = 'requires_action';
		$payment_intent_mock['charges']['data'][0]['payment_method_details'] = $payment_method_mock;

		$this->mock_gateway->expects( $this->once() )
			->method( 'generate_payment_request' )
			->will(
				$this->returnValue(
					[
						'description' => $description,
						'metadata'    => $metadata,
						'capture'     => 'true',
					]
				)
			);

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $payment_intent_mock ) )
			);

		$response      = $this->mock_gateway->process_payment( $order_id );
		$final_order   = wc_get_order( $order_id );
		$client_secret = $payment_intent_mock['client_secret'];

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( 'pending', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertRegExp( "/#wc-stripe-confirm-pi:$order_id:$client_secret/", $response['redirect'] );
	}

	/**
	 * Test error state with fatal test during checkout with saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_non_retryable_error_throws_exception() {
		$token = $this->set_postvars_for_saved_payment_method();

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$failed_payment_intent_mock           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$failed_payment_intent_mock['id']     = $payment_intent_id;
		$failed_payment_intent_mock['amount'] = $amount;
		$failed_payment_intent_mock['error']  = [
			'type'    => 'completely_fatal_error',
			'code'    => '666',
			'message' => 'Oh my god',
		];

		$this->mock_gateway->expects( $this->once() )
			->method( 'generate_payment_request' )
			->will(
				$this->returnValue(
					[
						'description' => $description,
						'metadata'    => $metadata,
						'capture'     => 'true',
					]
				)
			);

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) )
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'fail', $response['result'] );
		$this->assertEquals( 'failed', $final_order->get_status() );
	}

	/**
	 * Tests retryable error during checkout using saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_retries_error_when_possible() {
		$token = $this->set_postvars_for_saved_payment_method();

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$successful_payment_intent_mock           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$successful_payment_intent_mock['id']     = $payment_intent_id;
		$successful_payment_intent_mock['amount'] = $amount;
		$successful_payment_intent_mock['charges']['data'][0]['payment_method_details'] = $payment_method_mock;

		$failed_payment_intent_mock          = $successful_payment_intent_mock;
		$failed_payment_intent_mock['error'] = [
			'type'    => 'api_connection_error',
			'code'    => '501',
			'message' => 'Owie server hurty',
		];

		$this->mock_gateway->expects( $this->any() )
			->method( 'generate_payment_request' )
			->will(
				$this->returnValue(
					[
						'description' => $description,
						'metadata'    => $metadata,
						'capture'     => 'true',
					]
				)
			);

		$this->mock_gateway->expects( $this->exactly( 4 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) ),
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $successful_payment_intent_mock ) )
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertRegExp( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Tests that retryable error fails after 6 attempts.
	 */
	public function test_checkout_with_saved_payment_method_fails_after_six_attempts() {
		$token = $this->set_postvars_for_saved_payment_method();

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$failed_payment_intent_mock           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$failed_payment_intent_mock['id']     = $payment_intent_id;
		$failed_payment_intent_mock['amount'] = $amount;
		$failed_payment_intent_mock['error']  = [
			'type'    => 'invalid_request_error',
			'code'    => '404',
			'message' => 'No such customer',
		];

		$this->mock_gateway->expects( $this->any() )
			->method( 'generate_payment_request' )
			->will(
				$this->returnValue(
					[
						'description' => $description,
						'metadata'    => $metadata,
						'capture'     => 'true',
					]
				)
			);

		$this->mock_gateway->expects( $this->exactly( 12 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) ),
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) ),
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) ),
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) ),
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) ),
				json_decode( wp_json_encode( $payment_method_mock ) ),
				json_decode( wp_json_encode( $failed_payment_intent_mock ) )
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'fail', $response['result'] );
		$this->assertEquals( 'failed', $final_order->get_status() );
		$this->assertEquals( '', $final_order->get_meta( '_stripe_customer_id', true ) );
	}
}
