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
			->expects( $this->exactly( 2 ) )
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

		$create_and_confirm_setup_intent_num_calls = $free_order && ! ( $saved_token && 'cashapp' === $payment_method ) ? 1 : 0;
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
			->expects( $saved_token ? $this->never() : ( $free_order ? $this->once() : $this->exactly( 2 ) ) )
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
				'payment method' => 'wechat_pay',
			],
			'cashapp / default amount'     => [
				'payment method' => 'cashapp',
			],
			'cashapp / free'               => [
				'payment method' => 'cashapp',
				'free order'     => true,
			],
			'cashapp / free / saved token' => [
				'payment method' => 'cashapp',
				'free order'     => true,
				'saved token'    => true,
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
}
