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
	 * Base template for SEPA Direct Debit payment method.
	 */
	const MOCK_SEPA_PAYMENT_METHOD_TEMPLATE = [
		'type'       => 'sepa_debit',
		'object'     => 'payment_method',
		'sepa_debit' => [
			'last4' => '7061',
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
	 * Base template for Stripe payment intent.
	 */
	const MOCK_CARD_SETUP_INTENT_TEMPLATE = [
		'object'           => 'setup_intent',
		'status'           => 'succeeded',
		'client_secret'    => 'cs_mock',
		'last_setup_error' => [],
	];

	/**
	 * Initial setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setMethods(
				[
					'create_and_confirm_intent_for_off_session',
					'generate_payment_request',
					'get_return_url',
					'get_stripe_customer_id',
					'has_subscription',
					'maybe_process_pre_orders',
					'mark_order_as_pre_ordered',
					'is_pre_order_item_in_cart',
					'is_pre_order_product_charged_upfront',
					'prepare_order_source',
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
	 * Convert response array to object.
	 */
	private function array_to_object( $array ) {
		return json_decode( wp_json_encode( $array ) );
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
	 * @dataProvider get_upe_available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods( $country, $available_payment_methods ) {
		$this->set_stripe_account_data( [ 'country' => $country ] );
		$this->assertSame( $available_payment_methods, $this->mock_gateway->get_upe_available_payment_methods() );
	}

	public function test_get_upe_enabled_at_checkout_payment_method_ids() {
		$available_payment_methods = [
			WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
			WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
		];
		$this->mock_gateway->update_option(
			'upe_checkout_experience_accepted_payments',
			[
				'card',
				'link',
			]
		);
		$this->assertSame( $available_payment_methods, $this->mock_gateway->get_upe_enabled_at_checkout_payment_method_ids() );
	}

	public function get_upe_available_payment_methods_provider() {
		return [
			[
				'US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Giropay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sofort::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
			[
				'NON_US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Giropay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sofort::STRIPE_ID,
				],
			],
		];
	}

	/**
	 * CLASSIC CHECKOUT TESTS.
	 */

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

		$order->update_meta_data( '_stripe_intent_id', $payment_intent_id );
		$order->update_meta_data( '_stripe_upe_payment_type', '' );
		$order->update_meta_data( '_stripe_upe_waiting_for_redirect', true );
		$order->save();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );

		$expected_request = [
			'amount'               => $amount,
			'currency'             => $currency,
			'description'          => $description,
			'customer'             => $customer_id,
			'metadata'             => $metadata,
			'statement_descriptor' => null,
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
		$this->assertMatchesRegularExpression( "/order_id=$order_id/", $response['redirect_url'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=stripe/', $response['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=no/', $response['redirect_url'] );
	}

	/**
	 * Test basic redirect payment processed correctly.
	 */
	public function test_process_redirect_payment_returns_valid_response() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

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
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( 'Credit card / debit card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertTrue( (bool) $final_order->get_meta( '_stripe_upe_redirect_processed', true ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test redirect payment processed only runs once.
	 */
	public function test_process_redirect_payment_only_runs_once() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

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
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$success_order = wc_get_order( $order_id );

		$note = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];

		// assert successful order processing
		$this->assertEquals( 'processing', $success_order->get_status() );
		$this->assertEquals( 'Credit card / debit card', $success_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $success_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertTrue( (bool) $success_order->get_meta( '_stripe_upe_redirect_processed', true ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );

		// simulate an order getting marked as failed as if from a webhook
		$order->set_status( 'failed' );
		$order->save();

		// attempt to reprocess the order and confirm status is unchanged
		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'failed', $final_order->get_status() );
	}

	/**
	 * Test checkout flow with setup intents.
	 */
	public function test_checkout_without_payment_uses_setup_intents() {
		$setup_intent_id   = 'seti_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$order->set_total( 0 );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$setup_intent_mock                   = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']             = $setup_intent_id;
		$setup_intent_mock['payment_method'] = $payment_method_mock;

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "setup_intents/$setup_intent_id?expand[]=payment_method&expand[]=latest_attempt" )
			->will(
				$this->returnValue(
					$this->array_to_object( $setup_intent_mock )
				)
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $setup_intent_id, true );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertEquals( 'Credit card / debit card', $final_order->get_payment_method_title() );
	}

	/**
	 * Test checkout flow while saving payment method.
	 */
	public function test_checkout_saves_payment_method_to_order() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

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
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, true );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
	}

	/**
	 * Test checkout flow while saving payment method with SEPA generated payment method.
	 */
	public function test_checkout_saves_sepa_generated_payment_method_to_order() {
		$payment_intent_id           = 'pi_mock';
		$payment_method_id           = 'pm_mock';
		$generated_payment_method_id = 'pm_gen_mock';
		$customer_id                 = 'cus_mock';
		$order                       = WC_Helper_Order::create_order();
		$order_id                    = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock             = self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']       = $payment_method_id;
		$payment_method_mock['customer'] = $customer_id;

		$generated_payment_method_mock       = $payment_method_mock;
		$generated_payment_method_mock['id'] = $generated_payment_method_id;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['charges']['data'][0]['payment_method_details'] = [
			'type'       => 'bancontact',
			'bancontact' => [
				'generated_sepa_debit' => $generated_payment_method_id,
			],
		];

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $payment_intent_mock ),
				$this->array_to_object( $generated_payment_method_mock )
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, true );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $generated_payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
	}

	/**
	 * Test checkout flow while saving payment method with SEPA generated payment method AND setup intents.
	 */
	public function test_setup_intent_checkout_saves_sepa_generated_payment_method_to_order() {
		$setup_intent_id             = 'seti_mock';
		$payment_method_id           = 'pm_mock';
		$generated_payment_method_id = 'pm_gen_mock';
		$customer_id                 = 'cus_mock';
		$order                       = WC_Helper_Order::create_order();
		$order_id                    = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_total( 0 );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock             = self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']       = $payment_method_id;
		$payment_method_mock['customer'] = $customer_id;

		$generated_payment_method_mock       = $payment_method_mock;
		$generated_payment_method_mock['id'] = $generated_payment_method_id;

		$setup_intent_mock                   = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']             = $setup_intent_id;
		$setup_intent_mock['payment_method'] = $payment_method_mock;
		$setup_intent_mock['latest_attempt'] = [
			'payment_method_details' => [
				'type'       => 'bancontact',
				'bancontact' => [
					'generated_sepa_debit' => $generated_payment_method_id,
				],
			],
		];

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $setup_intent_mock ),
				$this->array_to_object( $generated_payment_method_mock )
			);

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $setup_intent_id, true );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $generated_payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
	}

	/**
	 * Test errors on intent throw exceptions.
	 */
	public function test_intent_error_throws_exception() {
		$payment_intent_id = 'pi_mock';
		$setup_intent_id   = 'seti_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [ 'message' => 'Uh-oh, something went wrong...' ];

		$setup_intent_mock                     = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']               = $setup_intent_id;
		$setup_intent_mock['last_setup_error'] = [ 'message' => 'Uh-oh, something went wrong...' ];

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $payment_intent_mock ),
				$this->array_to_object( $setup_intent_mock )
			);

		$exception = null;
		try {
			$this->mock_gateway->process_order_for_confirmed_intent( $order, $payment_intent_id, false );
		} catch ( WC_Stripe_Exception $e ) {
			// Test exception thrown.
			$exception = $e;
		}
		$this->assertMatchesRegularExpression( '/not able to process this payment./', $exception->getMessage() );

		$exception = null;
		$order->set_total( 0 );
		$order->save();
		try {
			$this->mock_gateway->process_order_for_confirmed_intent( $order, $setup_intent_id, false );
		} catch ( WC_Stripe_Exception $e ) {
			// Test exception thrown.
			$exception = $e;
		}
		$this->assertMatchesRegularExpression( '/not able to process this payment./', $exception->getMessage() );
	}

	/**
	 * Test order status corresponds with charge status.
	 */
	public function test_process_response_updates_order_by_charge_status() {
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$charge_mock                           = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE['charges']['data'][0];
		$charge_mock['payment_method_details'] = $payment_method_mock;

		// Test no charge captured.
		$charge_mock['captured'] = false;
		$charge_mock['id']       = 'ch_mock_1';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'no', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $test_order->get_transaction_id() );
		$this->assertEquals( 'on-hold', $test_order->get_status() );

		// Test charge succeeds.
		$charge_mock['captured'] = true;
		$charge_mock['id']       = 'ch_mock_2';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'yes', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( 'processing', $test_order->get_status() );

		// Test charge pending.
		$charge_mock['status'] = 'pending';
		$charge_mock['id']     = 'ch_mock_3';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'yes', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $test_order->get_transaction_id() );
		$this->assertEquals( 'on-hold', $test_order->get_status() );

		// Test charge failed.
		$charge_mock['status'] = 'failed';
		$charge_mock['id']     = 'ch_mock_4';
		$exception             = null;
		try {
			$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		} catch ( WC_Stripe_Exception $e ) {
			// Test that exception is thrown.
			$exception = $e;
		}

		$note = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$this->assertMatchesRegularExpression( '/Payment processing failed./', $note->content );
		$this->assertMatchesRegularExpression( '/Payment processing failed./', $exception->getLocalizedMessage() );
	}

	/**
	 * TESTS FOR SAVED PAYMENTS.
	 */

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
		$order->save();

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
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $payment_intent_mock )
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
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
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
		$order->save();

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
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $payment_intent_mock )
			);

		$response      = $this->mock_gateway->process_payment( $order_id );
		$final_order   = wc_get_order( $order_id );
		$client_secret = $payment_intent_mock['client_secret'];

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( 'pending', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:$order_id:$client_secret/", $response['redirect'] );
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
		$order->save();

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
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock )
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
		$order->save();

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
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock ),
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $successful_payment_intent_mock )
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
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
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
		$order->save();

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
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock ),
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock ),
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock ),
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock ),
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock ),
				$this->array_to_object( $payment_method_mock ),
				$this->array_to_object( $failed_payment_intent_mock )
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'fail', $response['result'] );
		$this->assertEquals( 'failed', $final_order->get_status() );
		$this->assertEquals( '', $final_order->get_meta( '_stripe_customer_id', true ) );
	}

	/**
	 * TESTS FOR SUBSCRIPTIONS.
	 */

	/**
	 * Initial subscription test.
	 */
	public function test_if_order_has_subscription_payment_method_will_be_saved() {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$order->update_meta_data( '_stripe_intent_id', $payment_intent_id );
		$order->update_meta_data( '_stripe_upe_payment_type', '' );
		$order->update_meta_data( '_stripe_upe_waiting_for_redirect', true );
		$order->save();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );

		$expected_request = [
			'amount'               => $amount,
			'currency'             => $currency,
			'description'          => $description,
			'customer'             => $customer_id,
			'metadata'             => $metadata,
			'setup_future_usage'   => 'off_session',
			'statement_descriptor' => null,
		];

		$_POST = [ 'wc_payment_intent_id' => $payment_intent_id ];

		$this->mock_gateway->expects( $this->any() )
			->method( 'has_subscription' )
			->will( $this->returnValue( true ) );

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
		$this->assertMatchesRegularExpression( "/order_id=$order_id/", $response['redirect_url'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=stripe/', $response['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $response['redirect_url'] );
	}

	/**
	 * Initial subscription test with free-trial.
	 */
	public function test_if_free_trial_subscription_will_not_update_intent() {
		$setup_intent_id = 'seti_mock';
		$order           = WC_Helper_Order::create_order();
		$order_id        = $order->get_id();

		$order->set_total( 0 );
		$order->save();

		$_POST = [ 'wc_payment_intent_id' => $setup_intent_id ];

		$this->mock_gateway->expects( $this->any() )
			->method( 'has_subscription' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->never() )
			->method( 'get_stripe_customer_id' );

		$this->mock_gateway->expects( $this->never() )
			->method( 'stripe_request' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertFalse( $response['payment_needed'] );
		$this->assertEquals( $order_id, $response['order_id'] );
		$this->assertMatchesRegularExpression( "/order_id=$order_id/", $response['redirect_url'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=stripe/', $response['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $response['redirect_url'] );
	}

	/**
	 * Test successful subscription renewal.
	 */
	public function test_subscription_renewal_is_successful() {
		$this->set_postvars_for_saved_payment_method();

		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$prepared_source   = (object) [
			'token_id'       => false,
			'customer'       => $customer_id,
			'source'         => $payment_method_id,
			'source_object'  => (object) [],
			'payment_method' => null,
		];

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

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

		// Arrange: Make sure to check that an action we care about was called
		// by hooking into it.
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment',
			[ &$mock_action_process_payment, 'action' ]
		);

		$this->mock_gateway->expects( $this->any() )
			->method( 'prepare_order_source' )
			->will(
				$this->returnValue( $prepared_source )
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'create_and_confirm_intent_for_off_session' )
			->with(
				wc_get_order( $order_id ),
				$prepared_source,
				$amount
			)
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$this->mock_gateway->process_subscription_payment( $amount, wc_get_order( $order_id ), false, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );
		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment' ], $mock_action_process_payment->get_tags() );
	}

	/**
	 * Tests subscription renewal when authorization on payment method is required.
	 */
	public function test_subscription_renewal_checks_payment_method_authorization() {
		$this->set_postvars_for_saved_payment_method();

		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$prepared_source   = (object) [
			'token_id'       => false,
			'customer'       => $customer_id,
			'source'         => $payment_method_id,
			'source_object'  => (object) [],
			'payment_method' => null,
		];

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [ 'message' => 'Transaction requires authentication.' ];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['charges']['data'][0]['payment_method_details'] = $payment_method_mock;

		$error_response = [
			'error' => [
				'code'           => 'authentication_required',
				'message'        => 'Transaction requires authentication.',
				'payment_intent' => $payment_intent_mock,
			],
		];

		// Arrange: Make sure to check that an action we care about was called
		// by hooking into it.
		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_authentication_required',
			[ &$mock_action_process_payment, 'action' ]
		);

		$this->mock_gateway->expects( $this->any() )
			->method( 'prepare_order_source' )
			->will(
				$this->returnValue( $prepared_source )
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'create_and_confirm_intent_for_off_session' )
			->with(
				wc_get_order( $order_id ),
				$prepared_source,
				$amount
			)
			->will(
				$this->returnValue(
					$this->array_to_object( $error_response )
				)
			);

		$this->mock_gateway->process_subscription_payment( $amount, wc_get_order( $order_id ), false, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertEquals( 'failed', $final_order->get_status() );
		$this->assertEquals( 'ch_mock', $final_order->get_transaction_id() );
		$this->assertMatchesRegularExpression( '/pending/i', $note->content );
		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );
		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_authentication_required' ], $mock_action_process_payment->get_tags() );
	}

	/**
	 * TESTS FOR PRE-ORDERS.
	 */

	/**
	 * Pre-order payment is successful.
	 */
	public function test_pre_order_payment_is_successful() {
		$payment_intent_id = 'pi_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount, $description, $metadata ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

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

		// Mock order has pre-order product.
		$this->mock_gateway->expects( $this->once() )
			->method( 'maybe_process_pre_orders' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'mark_order_as_pre_ordered' );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'Credit card / debit card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertTrue( (bool) $final_order->get_meta( '_stripe_upe_redirect_processed', true ) );
	}

	/**
	 * Pre-order with no required payment uses setup intents.
	 */
	public function test_pre_order_without_payment_uses_setup_intents() {
		$setup_intent_id   = 'seti_mock';
		$payment_method_id = 'pm_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$order->set_total( 0 );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$setup_intent_mock                   = self::MOCK_CARD_SETUP_INTENT_TEMPLATE;
		$setup_intent_mock['id']             = $setup_intent_id;
		$setup_intent_mock['payment_method'] = $payment_method_mock;

		// Mock order has pre-order product.
		$this->mock_gateway->expects( $this->once() )
			->method( 'maybe_process_pre_orders' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_item_in_cart' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_product_charged_upfront' )
			->will( $this->returnValue( false ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "setup_intents/$setup_intent_id?expand[]=payment_method&expand[]=latest_attempt" )
			->will(
				$this->returnValue(
					$this->array_to_object( $setup_intent_mock )
				)
			);

		$this->mock_gateway->expects( $this->once() )
			->method( 'mark_order_as_pre_ordered' );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $setup_intent_id, true );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertEquals( $customer_id, $final_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertTrue( (bool) $final_order->get_meta( '_stripe_upe_redirect_processed', true ) );
	}


	/**
	 * @param array $account_data
	 *
	 * @return void
	 */
	private function set_stripe_account_data( $account_data ) {
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
												->disableOriginalConstructor()
												->setMethods( [ 'get_cached_account_data' ] )
												->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn( $account_data );
	}
}
