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
	private $mock_return_url;

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
					'get_return_url',
					'get_stripe_customer_id',
					'get_upe_enabled_payment_method_ids',
					'stripe_request',
				]
			)
			->getMock();

		$this->mock_gateway
			->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( $this->return_url )
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

	public function test_payment_fields_outputs_fields() {
		$this->set_cart_contains_subscription_items( false );
		$this->mock_gateway->payment_fields();
		$this->expectOutputRegex( '/<div id="wc-stripe-upe-element"><\/div>/' );
	}

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
}
