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
		'type' => WC_Stripe_Payment_Methods::CARD,
		WC_Stripe_Payment_Methods::CARD => [
			'brand'     => 'visa',
			'networks'  => [ 'preferred' => 'visa' ],
			'exp_month' => '7',
			'funding'   => 'credit',
			'last4'     => '4242',
		],
	];

	/**
	 * Base template for SEPA Direct Debit payment method.
	 */
	const MOCK_SEPA_PAYMENT_METHOD_TEMPLATE = [
		'type'       => WC_Stripe_Payment_Methods::SEPA_DEBIT,
		'object'     => 'payment_method',
		WC_Stripe_Payment_Methods::SEPA_DEBIT => [
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
	 * Base template for Wallet payment intent.
	 */
	const MOCK_WECHAT_PAY_PAYMENT_INTENT_TEMPLATE = [
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

		$mock_account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [ $mock_account ] )
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
					'is_subscriptions_enabled',
					'update_saved_payment_method',
				]
			)
			->getMock();

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( self::MOCK_RETURN_URL )
			);

		$this->mock_gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->setMethods( [ 'create_and_confirm_payment_intent', 'update_and_confirm_payment_intent', 'create_and_confirm_setup_intent' ] )
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
				WC_Stripe_Payment_Methods::CARD,
				WC_Stripe_Payment_Methods::LINK,
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
					WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Affirm::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Multibanco::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Wechat_Pay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Cash_App_Pay::STRIPE_ID,
				],
			],
			[
				'NON_US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
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
		$this->expectOutputRegex( '/<div class="wc-stripe-upe-element" data-payment-method-type="card"><\/div>/' );
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
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => $description,
			'customer'    => $customer_id,
			'metadata'    => $metadata,
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

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

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
				'charges'        => (object) [
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
			->willReturn( null );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:{$order_id}:{$mock_intent->client_secret}/", $response['redirect'] );
	}

	/**
	 * Test Wallet checkout process_payment flow with deferred intent.
	 *
	 * @param string $payment_method Payment method to test.
	 * @param bool $free_order Whether the order is free.
	 * @param bool $saved_token Whether the payment method is saved.
	 * @dataProvider provide_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response
	 * @throws WC_Data_Exception When setting order payment method fails.
	 */
	public function test_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response( $payment_method, $free_order = false, $saved_token = false ) {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order( 1, null, [ 'total' => $free_order ? 0 : 50 ] );
		$order_id    = $order->get_id();

		// Set payment gateway.
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Method_Wechat_Pay::STRIPE_ID );
		$order->save();

		$mock_intent = (object) wp_parse_args(
			[
				'status'               => 'requires_action',
				'object'               => 'payment_intent',
				'data'                 => [
					(object) [
						'id'       => $order_id,
						'captured' => 'yes',
						'status'   => 'succeeded',
					],
				],
				'payment_method'       => 'pm_mock',
				'payment_method_types' => [ $payment_method ],
				'charges'              => (object) [
					'total_count' => 0, // Intents requiring SCA verification respond with no charges.
					'data'        => [],
				],
			],
			self::MOCK_WECHAT_PAY_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST = [
			'payment_method'               => 'stripe_' . $payment_method,
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-is-deferred-intent' => '1',
		];

		if ( $saved_token ) {
			$token = WC_Helper_Token::create_token( 'pm_mock' );
			$token->set_gateway_id( 'stripe_' . $payment_method );
			$token->save();

			$_POST[ 'wc-stripe_' . $payment_method . '-payment-token' ] = (string) $token->get_id();
		}

		$this->mock_gateway->intent_controller
			->expects( $free_order ? $this->never() : $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$create_and_confirm_setup_intent_num_calls = $free_order && ! ( $saved_token && WC_Stripe_Payment_Methods::CASHAPP_PAY === $payment_method ) ? 1 : 0;
		$this->mock_gateway->intent_controller
			->expects( $this->exactly( $create_and_confirm_setup_intent_num_calls ) )
			->method( 'create_and_confirm_setup_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		// We only use this when handling mandates.
		$this->mock_gateway
			->expects( $saved_token ? $this->never() : $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( null );

		$this->mock_gateway
			->expects( $saved_token ? $this->once() : $this->never() )
			->method( 'update_saved_payment_method' );

		$response   = $this->mock_gateway->process_payment( $order_id );
		$return_url = self::MOCK_RETURN_URL;

		if ( $saved_token ) {
			$expected_redirect_url = '/' . self::MOCK_RETURN_URL . '/';
		} else {
			$expected_redirect_url = "/#wc-stripe-wallet-{$order_id}:{$payment_method}:{$mock_intent->object}:{$mock_intent->client_secret}:{$return_url}/";
		}

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( $expected_redirect_url, $response['redirect'] );
	}

	/**
	 * Provider for `test_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response`.
	 *
	 * @return array
	 */
	public function provide_process_payment_deferred_intent_with_required_action_for_wallet_returns_valid_response() {
		return [
			'wechat pay / default amount'  => [
				'payment method' => WC_Stripe_Payment_Methods::WECHAT_PAY,
			],
			'cashapp / default amount'     => [
				'payment method' => WC_Stripe_Payment_Methods::CASHAPP_PAY,
			],
			'cashapp / free'               => [
				'payment method' => WC_Stripe_Payment_Methods::CASHAPP_PAY,
				'free order'     => true,
			],
			'cashapp / free / saved token' => [
				'payment method' => WC_Stripe_Payment_Methods::CASHAPP_PAY,
				'free order'     => true,
				'saved token'    => true,
			],
		];
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

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

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

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

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

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'update_saved_payment_method' );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'failure', $response['result'] );

		$processed_order = wc_get_order( $order_id );
		$this->assertEquals( 'failed', $processed_order->get_status() );
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
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );
		$note        = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];

		$this->assertEquals( 'processing', $final_order->get_status() );
		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
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
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

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
		$this->assertEquals( 'Credit / Debit Card', $success_order->get_payment_method_title() );
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
		$setup_intent_mock['latest_charge']  = [];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
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
		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
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
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

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
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->willReturnOnConsecutiveCalls(
				$this->array_to_object( $payment_intent_mock ),
				$this->array_to_object( $generated_payment_method_mock )
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => [
				'type'       => WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::BANCONTACT => [
					'generated_sepa_debit' => $generated_payment_method_id,
				],
			],
		];
		$this->mock_gateway
			->expects( $this->exactly( 3 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

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
		$setup_intent_mock['latest_charge']  = [];
		$setup_intent_mock['latest_attempt'] = [
			'payment_method_details' => [
				'type'       => WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::BANCONTACT => [
					'generated_sepa_debit' => $generated_payment_method_id,
				],
			],
		];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);
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
	public function test_process_payment_with_saved_method_returns_valid_response() {
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

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

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
				'status'         => 'requires_action',
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

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$response      = $this->mock_gateway->process_payment( $order_id );
		$final_order   = wc_get_order( $order_id );
		$client_secret = $payment_intent_mock->client_secret;

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( 'pending', $final_order->get_status() ); // Order status should be pending until 3DS is completed.
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

		// Set the appropriate POST flag to trigger a deferred intent request.
		$_POST['wc-stripe-is-deferred-intent'] = '1';
		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-payment-method']     = 'pm_mock';

		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();
		$payment_intent_id = 'pi_mock';
		$payment_method_id = $token->get_token();
		$customer_id       = 'cus_mock';

		$failed_payment_intent_mock = (object) [
			'error' => (object) [
				'type'           => 'completely_fatal_error',
				'code'           => '666',
				'message'        => 'Oh my god',
				'payment_intent' => (object) [
					'id'     => $payment_intent_id,
					'object' => 'payment_intent',
				],
			],
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $failed_payment_intent_mock );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'failure', $response['result'] );
		$this->assertEquals( 'failed', $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $final_order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( $payment_method_id, $final_order->get_meta( '_stripe_source_id', true ) );
	}

	/**
	 * Tests retryable error during checkout using saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_retries_error_when_possible() {
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

		$successful_payment_intent_mock = (object) array_merge(
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

		$failed_payment_intent_mock = (object) [
			'error' => (object) [
				'type'           => 'api_connection_error',
				'code'           => '501',
				'message'        => 'Owie server hurty',
				'payment_intent' => (object) [
					'id'     => $payment_intent_id,
					'object' => 'payment_intent',
				],
			],
		];

		$this->mock_gateway->intent_controller
			->expects( $this->exactly( 3 ) )
			->method( 'create_and_confirm_payment_intent' )
			->willReturnOnConsecutiveCalls(
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$successful_payment_intent_mock
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $failed_payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 4 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

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

		$successful_payment_intent_mock = (object) array_merge(
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

		$failed_payment_intent_mock = (object) [
			'error' => (object) [
				'type'           => 'invalid_request_error',
				'code'           => '404',
				'message'        => 'No such customer',
				'payment_intent' => (object) [
					'id'     => $payment_intent_id,
					'object' => 'payment_intent',
				],
			],
		];

		$this->mock_gateway->intent_controller
			->expects( $this->exactly( 6 ) )
			->method( 'create_and_confirm_payment_intent' )
			->willReturnOnConsecutiveCalls(
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock,
				$failed_payment_intent_mock
			);

		$this->mock_gateway
			->expects( $this->any() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'failure', $response['result'] );
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

		// When the order contains a subscription, the payment type is expected to be "recurring".
		$metadata['payment_type'] = 'recurring';

		$expected_request = [
			'amount'             => $amount,
			'currency'           => $currency,
			'description'        => $description,
			'customer'           => $customer_id,
			'metadata'           => $metadata,
			'setup_future_usage' => 'off_session',
		];

		$_POST = [ 'wc_payment_intent_id' => $payment_intent_id ];

		$this->mock_gateway->expects( $this->any() )
			->method( 'is_subscriptions_enabled' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->any() )
			->method( 'has_subscription' )
			->will( $this->returnValue( true ) );

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
			->method( 'get_stripe_customer_from_order' );

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
		$order->update_meta_data( '_stripe_lock_payment', ( time() + MINUTE_IN_SECONDS ) ); // To assist with comparing expected order objects, set an existing lock.
		$order->save();

		$order = wc_get_order( $order_id );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

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
				$order,
				$prepared_source,
				$amount
			)
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_subscription_payment( $amount, $order, false, false );

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
		$order->update_meta_data( '_stripe_lock_payment', ( time() + MINUTE_IN_SECONDS ) ); // To assist with comparing expected order objects, set an existing lock.
		$order->save();

		$order = wc_get_order( $order_id );

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['last_payment_error'] = [ 'message' => 'Transaction requires authentication.' ];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['last_charge']        = 'ch_mock';

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
				$order,
				$prepared_source,
				$amount
			)
			->will(
				$this->returnValue(
					$this->array_to_object( $error_response )
				)
			);

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_intent_mock,
		];
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_subscription_payment( $amount, $order, false, false );

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
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		// Mock order has pre-order product.
		$this->mock_gateway->expects( $this->any() )
			->method( 'has_pre_order' )
			->with( $order_id )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_item_in_cart' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'is_pre_order_product_charged_upfront' )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->will(
				$this->returnValue(
					$this->array_to_object( $payment_intent_mock )
				)
			);
		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);

		$this->mock_gateway->expects( $this->any() )
			->method( 'has_pre_order_charged_upon_release' )
			->with( wc_get_order( $order_id ) )
			->will( $this->returnValue( true ) );

		$this->mock_gateway->expects( $this->once() )
			->method( 'mark_order_as_pre_ordered' );

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
			'payment_method_details' => $payment_method_mock,
		];
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
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
		$setup_intent_mock['latest_charge']  = [];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_from_order' )
			->with( wc_get_order( $order_id ) )
			->will(
				$this->returnValue( $this->mock_stripe_customer )
			);

		// Mock order has pre-order product.
		$this->mock_gateway->expects( $this->once() )
			->method( 'has_pre_order' )
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
	 * Test if `display_order_fee` and `display_order_payout` are called when viewing an order on the admin panel.
	 *
	 * @return void
	 */
	public function test_fees_actions_are_called_on_order_admin_page() {
		$order = WC_Helper_Order::create_order();

		$this->mock_gateway->expects( $this->once() )
			->method( 'display_order_fee' )
			->with( $order->get_id() );

		$this->mock_gateway->expects( $this->once() )
			->method( 'display_order_payout' )
			->with( $order->get_id() );

		do_action( 'woocommerce_admin_order_totals_after_total', $order->get_id() );
	}
	/**
	 * Test for `process_payment` when the order has an existing payment intent attached.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_deferred_intent_with_existing_intent() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$currency    = $order->get_currency();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'payment_method'       => 'pm_mock',
				'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
				'charges'              => (object) [
					'data' => [
						(object) [
							'id'       => $order_id,
							'captured' => 'yes',
							'status'   => 'succeeded',
						],
					],
				],
				'status'               => 'requires_action',
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
			->method( 'get_intent_from_order' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:{$order_id}:{$mock_intent->client_secret}/", $response['redirect'] );
	}

	/**
	 * Test for `process_payment` with a co-branded credit card and preferred brand set.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_deferred_intent_with_co_branded_cc_and_preferred_brand() {
		if ( ! WC_Stripe_Co_Branded_CC_Compatibility::is_wc_supported() ) {
			$this->markTestSkipped( 'Test requires WooCommerce ' . WC_Stripe_Co_Branded_CC_Compatibility::MIN_WC_VERSION . ' or newer.' );
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

		$charge = [
			'id'                     => 'ch_mock',
			'captured'               => true,
			'status'                 => 'succeeded',
		];
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $this->array_to_object( $charge ) );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'update_saved_payment_method' )
			->with(
				$this->equalTo( $payment_method_id ),
				$this->callback(
					function( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'stripe_request' )
			->with(
				"payment_methods/$payment_method_id",
			)
			->will(
				$this->returnValue(
					$this->array_to_object( self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE )
				)
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
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test for `filter_saved_payment_methods_list`
	 *
	 * @param bool $saved_cards Whether saved cards are enabled.
	 * @param array $item The list of saved payment methods.
	 * @param array $expected The expected list of saved payment methods.
	 * @return void
	 * @dataProvider provide_test_filter_saved_payment_methods_list
	 */
	public function test_filter_saved_payment_methods_list( $saved_cards, $item, $expected ) {
		$payment_token                   = $this->getMockBuilder( 'WC_Payment_Token_CC' )
			->disableOriginalConstructor()
			->getMock();
		$this->mock_gateway->saved_cards = $saved_cards;
		$list                            = $this->mock_gateway->filter_saved_payment_methods_list( $item, $payment_token );
		$this->assertSame( $expected, $list );
	}

	/**
	 * Provider for `test_filter_saved_payment_methods_list`
	 *
	 * @return array
	 */
	public function provide_test_filter_saved_payment_methods_list() {
		$item = [
			'brand'     => 'visa',
			'exp_month' => '7',
			'exp_year'  => '2099',
			'last4'     => '4242',
		];
		return [
			'Saved cards enabled'  => [
				'saved cards' => true,
				'item'        => $item,
				'expected'    => $item,
			],
			'Saved cards disabled' => [
				'saved cards' => false,
				'item'        => $item,
				'expected'    => [],
			],
		];
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

	/**
	 * Test test_set_payment_method_title_for_order.
	 *
	 */
	public function test_set_payment_method_title_for_order() {
		$order = WC_Helper_Order::create_order();

		// Subscriptions - note that orders are used here as subscriptions. Subscriptions inherit all order methods so should suffice for testing.
		$mock_subscription_0 = WC_Helper_Order::create_order();
		$mock_subscription_1 = WC_Helper_Order::create_order();

		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order = [ $mock_subscription_0, $mock_subscription_1 ];

		$this->mock_gateway->expects( $this->exactly( 3 ) ) // 3 times because we test 3 payment methods.
			->method( 'is_subscriptions_enabled' )
			->willReturn( true );

		/**
		 * SEPA
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID );

		$this->assertEquals( 'stripe_sepa_debit', $order->get_payment_method() );
		$this->assertEquals( 'SEPA Direct Debit', $order->get_payment_method_title() );

		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );

		/**
		 * iDEAL
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID );

		$this->assertEquals( 'stripe_ideal', $order->get_payment_method() );
		$this->assertEquals( 'iDEAL', $order->get_payment_method_title() );

		// iDEAL subscriptions should be set to SEPA as it's the processing payment method of subscription payments for iDEAL.
		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe_sepa_debit', $mock_subscription_0->get_payment_method() );

		/**
		 * Cards
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID );

		// Cards should be set to `stripe`.
		$this->assertEquals( 'stripe', $order->get_payment_method() );
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );

		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
	}

	/**
	 * Test test_set_payment_method_title_for_order with custom title.
	 */
	public function test_set_payment_method_title_for_order_custom_title() {
		$order = WC_Helper_Order::create_order();

		// CARD
		// Set a custom title.
		$payment_method_type     = WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID;
		$payment_method_settings = get_option( "woocommerce_stripe_{$payment_method_type}_settings", [] );
		$payment_method_settings['title'] = 'Custom Card Title';
		update_option( "woocommerce_stripe_{$payment_method_type}_settings", $payment_method_settings );

		$this->mock_gateway->set_payment_method_title_for_order( $order, $payment_method_type );

		$this->assertEquals( 'Custom Card Title', $order->get_payment_method_title() );

		// SEPA
		// Set a custom title.
		$payment_method_type     = WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;
		$payment_method_settings = get_option( "woocommerce_stripe_{$payment_method_type}_settings", [] );
		$payment_method_settings['title'] = 'Custom SEPA Title';
		update_option( "woocommerce_stripe_{$payment_method_type}_settings", $payment_method_settings );

		$this->mock_gateway->set_payment_method_title_for_order( $order, $payment_method_type );

		$this->assertEquals( 'Custom SEPA Title', $order->get_payment_method_title() );
	}

	/**
	 * Test test_set_payment_method_title_for_order with ECE wallet PM.
	 */
	public function test_set_payment_method_title_for_order_ECE_title() {
		$order = WC_Helper_Order::create_order();
		update_option( WC_Stripe_Feature_Flags::ECE_FEATURE_FLAG_NAME, 'yes' );

		// GOOGLE PAY
		$mock_ece_payment_method = (object) [
			'card' => (object) [
				'brand'  => 'visa',
				'wallet' => (object) [
					'type' => 'google_pay',
				],
			],
		];

		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );
		$this->assertEquals( 'Google Pay (Stripe)', $order->get_payment_method_title() );

		// APPLE PAY
		$mock_ece_payment_method->card->wallet->type = 'apple_pay';
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );
		$this->assertEquals( 'Apple Pay (Stripe)', $order->get_payment_method_title() );

		// INVALID
		$mock_ece_payment_method->card->wallet->type = 'invalid';
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );

		// Invalid wallet type should default to Credit / Debit Card.
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );

		// NO WALLET
		unset( $mock_ece_payment_method->card->wallet->type );
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $mock_ece_payment_method );

		// No wallet type should default to Credit / Debit Card.
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );

		// Unset the feature flag.
		delete_option( WC_Stripe_Feature_Flags::ECE_FEATURE_FLAG_NAME );
	}
}
