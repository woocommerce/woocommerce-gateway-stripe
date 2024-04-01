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
	 * Mock WC Stripe Customer
	 *
	 * @var WC_Stripe_Customer
	 */
	private $mock_stripe_customer;

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
		'id'                 => 'pi_mock',
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
					'get_latest_charge_from_intent',
					'get_return_url',
					'get_stripe_customer_id',
					'has_subscription',
					'maybe_process_pre_orders',
					'mark_order_as_pre_ordered',
					'is_pre_order_item_in_cart',
					'is_pre_order_product_charged_upfront',
					'prepare_order_source',
					'stripe_request',
					'get_stripe_customer_from_order',
					'display_order_fee',
					'display_order_payout',
					'get_intent_from_order',
					'has_pre_order_charged_upon_release',
					'has_pre_order',
				]
			)
			->getMock();

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( self::MOCK_RETURN_URL )
			);

		$this->mock_gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->setMethods( [ 'create_and_confirm_payment_intent', 'update_and_confirm_payment_intent' ] )
			->getMock();

		$this->mock_gateway->action_scheduler_service = $this->getMockBuilder( WC_Stripe_Action_Scheduler_Service::class )
		->setMethods( [ 'schedule_job' ] )
		->getMock();

		$this->mock_stripe_customer = $this->getMockBuilder( WC_Stripe_Customer::class )
			->disableOriginalConstructor()
			->setMethods(
				[
					'create_customer',
					'update_customer',
				]
			)
			->getMock();

		$this->mock_stripe_customer->expects( $this->any() )
			->method( 'create_customer' )
			->will(
				$this->returnValue( 'cus_mock' )
			);
		$this->mock_stripe_customer->expects( $this->any() )
			->method( 'update_customer' )
			->will(
				$this->returnValue( 'cus_mock' )
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
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Giropay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
			[
				'NON_US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Giropay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
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
		$this->expectOutputRegex( '/<div class="wc-stripe-upe-element"><\/div>/' );
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
		];

		$_POST = [ 'wc_payment_intent_id' => $payment_intent_id ];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
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
	 * Test basic checkout process_payment flow with deferred intent.
	 */
	public function test_process_payment_deferred_intent_returns_valid_response() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$currency    = $order->get_currency();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'payment_method' => 'pm_mock',
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => $order_id,
							'captured' => 'yes',
							'status'   => 'succeeded',
						],
					],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( self::MOCK_RETURN_URL, $response['redirect'] );
	}

	/**
	 * Test SCA/3DS checkout process_payment flow with deferred intent.
	 */
	public function test_process_payment_deferred_intent_with_required_action_returns_valid_response() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'status'         => 'requires_action',
				'data'           => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
				'payment_method' => 'pm_mock',
				'charges' => (object) [
					'total_count' => 0, // Intents requiring SCA verification respond with no charges.
					'data'        => [],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		// We only use this when handling mandates.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( (object) [] );

		$this->mock_gateway->action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:{$order_id}:{$mock_intent->client_secret}/", $response['redirect'] );
	}

	/**
	 * Exception handling of the process_payment flow with deferred intent.
	 */
	public function test_process_payment_deferred_intent_handles_exception() {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$mock_intent = (object) [
			'charges' => (object) [
				'data' => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
			],
		];

		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willThrowException( new WC_Stripe_Exception( "It's a trap!" ) );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( 'failed', $processed_order->get_status() );
	}

	public function test_process_payment_deferred_intent_bails_with_empty_payment_type() {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$mock_intent = (object) [
			'charges' => (object) [
				'data' => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
			],
		];

		$_POST = [
			'payment_method'               => '',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( 'failed', $processed_order->get_status() );
	}

	public function test_process_payment_deferred_intent_bails_with_invalid_payment_type() {
		$payment_intent_id = 'pi_mock';
		$customer_id       = 'cus_mock';
		$order             = WC_Helper_Order::create_order();
		$currency          = $order->get_currency();
		$order_id          = $order->get_id();

		$mock_intent = (object) [
			'charges' => (object) [
				'data' => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
			],
		];

		$_POST = [
			'payment_method'               => 'some_invalid_type',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( 'failed', $processed_order->get_status() );
	}

	/**
	 * Test for `process_payment` with a co-branded credit card and preferred brand set.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_deferred_intent_with_co_branded_cc_and_preferred_brand() {
		if ( ! WC_Stripe_Co_Branded_CC_Compatibility::is_wc_supported() ) {
			$this->markTestSkipped( 'Test requires WooCommerce 8.8 or newer.' );
		}

		$token = $this->set_postvars_for_saved_payment_method();

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'id'             => $payment_intent_id,
				'amount'         => $amount,
				'payment_method' => $payment_method_id,
				'charges'        => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			]
		);

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $payment_intent_mock );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->greaterThanOrEqual( time() ),
				'wc_stripe_update_saved_payment_method',
				[
					'payment_method' => $payment_method_id,
					'order_id'       => $order_id,
				]
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
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
		$this->assertEquals( 'visa', $final_order->get_meta( '_stripe_card_brand', true ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
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
