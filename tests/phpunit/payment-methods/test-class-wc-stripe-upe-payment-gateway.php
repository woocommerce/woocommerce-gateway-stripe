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
		'type'                          => WC_Stripe_Payment_Methods::CARD,
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
		'type'                                => WC_Stripe_Payment_Methods::SEPA_DEBIT,
		'object'                              => 'payment_method',
		WC_Stripe_Payment_Methods::SEPA_DEBIT => [
			'last4'       => '7061',
			'fingerprint' => 'fp_mock',
		],
	];

	/**
	 * Base template for Stripe payment intent.
	 */
	const MOCK_CARD_PAYMENT_INTENT_TEMPLATE = [
		'id'                 => 'pi_mock',
		'object'             => 'payment_intent',
		'status'             => WC_Stripe_Intent_Status::SUCCEEDED,
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
		'status'           => WC_Stripe_Intent_Status::SUCCEEDED,
		'client_secret'    => 'cs_mock',
		'last_setup_error' => [],
	];

	/**
	 * Initial setup.
	 */
	public function set_up() {
		parent::set_up();

		update_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_ACSS_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME, 'yes' );

		$stripe_settings                                  = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['sepa_tokens_for_other_methods'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
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

	public function tear_down() {
		parent::tear_down();
		delete_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME );
		delete_option( WC_Stripe_Feature_Flags::LPM_ACSS_FEATURE_FLAG_NAME );
		delete_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME );
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
		$total_tax    = $order->get_total_tax();
		$amount       = WC_Stripe_Helper::get_stripe_amount( $total, $currency );
		$description  = "Test Blog - Order $order_number";
		$metadata     = [
			'customer_name'  => 'Jeroen Sormani',
			'customer_email' => 'admin@example.org',
			'site_url'       => 'http://example.org',
			'order_id'       => $order_number,
			'order_key'      => $order_key,
			'payment_type'   => 'single',
			'signature'      => sprintf( '%d:%s', $order->get_id(), md5( implode( '-', [ absint( $order->get_id() ), $order->get_order_key(), $order->get_customer_id(), $amount ] ) ) ),
			'tax_amount'     => WC_Stripe_Helper::get_stripe_amount( $total_tax, strtolower( $currency ) ),
		];
		return [ $amount, $description, $metadata ];
	}

	/**
	 * @dataProvider get_upe_available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods( $country, $available_payment_methods ) {
		$this->set_stripe_account_data( [ 'country' => $country ] ); // TODO: Verify if the country is actually changing in the gateway.
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
					WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
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
					WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
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
					WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
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

		$_POST = [
			'payment_method'       => 'stripe',
			'wc_payment_intent_id' => $payment_intent_id,
		];

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
	 *
	 * @dataProvider provide_process_payment_deferred_intent_returns_valid_response
	 */
	public function test_process_payment_deferred_intent_returns_valid_response( $post_vars ) {
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
		$_POST = $post_vars;

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
	 * Provider for `test_process_payment_deferred_intent_returns_valid_response`.
	 */
	public function provide_process_payment_deferred_intent_returns_valid_response() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
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
				'status'         => WC_Stripe_Intent_Status::REQUIRES_ACTION,
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
				'status'               => WC_Stripe_Intent_Status::REQUIRES_ACTION,
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
	 *
	 * @dataProvider provide_process_payment_deferred_intent_handles_exception
	 */
	public function test_process_payment_deferred_intent_handles_exception( $post_vars ) {
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

		$_POST = $post_vars;

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

	/**
	 * Provider for `test_process_payment_deferred_intent_handles_exception`.
	 */
	public function provide_process_payment_deferred_intent_handles_exception() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
	}

	/**
	 * @dataProvider provide_process_payment_deferred_intent_bails_with_empty_payment_type
	 */
	public function test_process_payment_deferred_intent_bails_with_empty_payment_type( $post_vars ) {
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

		$_POST = $post_vars;

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
	 * Provider for `test_process_payment_deferred_intent_bails_with_empty_payment_type`.
	 */
	public function provide_process_payment_deferred_intent_bails_with_empty_payment_type() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => '',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => '',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
	}

	/**
	 * @dataProvider provide_process_payment_deferred_intent_bails_with_invalid_payment_type
	 */
	public function test_process_payment_deferred_intent_bails_with_invalid_payment_type( $post_vars ) {
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

		$_POST = $post_vars;

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
	 * Provider for `test_process_payment_deferred_intent_bails_with_invalid_payment_type`.
	 */
	public function provide_process_payment_deferred_intent_bails_with_invalid_payment_type() {
		return [
			'with-payment-method'     => [
				[
					'payment_method'               => 'some_invalid_type',
					'wc-stripe-payment-method'     => 'pm_mock',
					'wc-stripe-confirmation-token' => '',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'some_invalid_type',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
					'wc-stripe-is-deferred-intent' => '1',
				],
			],
		];
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
	 * Test for `is_spe_enabled`.
	 *
	 * @return void
	 */
	public function test_is_spe_enabled() {
		// Disabled
		update_option( WC_Stripe_Feature_Flags::SPE_FEATURE_FLAG_NAME, 'no' );

		$gateway = new WC_Stripe_UPE_Payment_Gateway();
		$this->assertFalse( $gateway->is_spe_enabled() );

		// Enabled
		update_option( WC_Stripe_Feature_Flags::SPE_FEATURE_FLAG_NAME, 'yes' );

		$stripe_settings                           = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['single_payment_element'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$gateway = new WC_Stripe_UPE_Payment_Gateway();
		$this->assertTrue( $gateway->is_spe_enabled() );
	}
}
