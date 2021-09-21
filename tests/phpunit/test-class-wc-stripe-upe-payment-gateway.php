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
		'object'  => 'payment_intent',
		'status'  => 'succeeded',
		'charges' => [
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

		$this->mock_payment_methods = [];
		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$mock_payment_method = $this->getMockBuilder( $payment_method_class )
				->setMethods( [ 'is_subscription_item_in_cart' ] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setMethods(
				[
					'generate_payment_request',
					'get_return_url',
					'get_stripe_customer_id',
					'get_upe_enabled_payment_method_ids',
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
	 * Helper function to mock subscriptions for internal UPE payment methods.
	 */
	private function set_cart_contains_subscription_items( $cart_contains_subscriptions ) {
		foreach ( $this->mock_payment_methods as $mock_payment_method ) {
			$mock_payment_method->expects( $this->any() )
				->method( 'is_subscription_item_in_cart' )
				->will(
					$this->returnValue( $cart_contains_subscriptions )
				);
		}
	}

	/**
	 * Test payment fields HTML output.
	 */
	public function test_payment_fields_outputs_fields() {
		$this->set_cart_contains_subscription_items( false );
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
		$total             = $order->get_total();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();
		$order_number      = $order->get_order_number();
		$order_key         = $order->get_order_key();
		$expected_request  = [
			'amount'      => WC_Stripe_Helper::get_stripe_amount( $total, $currency ),
			'currency'    => $currency,
			'description' => "Test Blog - Order $order_number",
			'customer'    => $customer_id,
			'metadata'    => [
				'customer_name'  => 'Jeroen Sormani',
				'customer_email' => 'admin@example.org',
				'site_url'       => 'http://example.org',
				'order_id'       => $order_id,
				'order_key'      => $order_key,
				'payment_type'   => 'single',
			],
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
	 * Test basic checkout with saved payment method.
	 */
	public function test_process_payment_with_saved_method_confirms_intent_immediately() {
		$this->set_cart_contains_subscription_items( false );
		$token = $this->set_postvars_for_saved_payment_method();

		$order             = WC_Helper_Order::create_order();
		$total             = $order->get_total();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();
		$order_number      = $order->get_order_number();
		$order_key         = $order->get_order_key();
		$amount            = WC_Stripe_Helper::get_stripe_amount( $total, $currency );
		$description       = "Test Blog - Order $order_number";
		$metadata          = [
			'customer_name'  => 'Jeroen Sormani',
			'customer_email' => 'admin@example.org',
			'site_url'       => 'http://example.org',
			'order_id'       => $order_id,
			'order_key'      => $order_key,
			'payment_type'   => 'single',
		];
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']     = $payment_intent_id;
		$payment_intent_mock['amount'] = $amount;
		$payment_intent_mock['charges']['data'][0]['payment_method_details'] = $payment_method_mock;

		$enabled_payment_method_ids = array_keys( $this->mock_payment_methods );

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->will(
				$this->returnValue( $enabled_payment_method_ids )
			);

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
		$this->assertRegExp( '/Charge ID: ch_mock/', $note->content );
	}
}
