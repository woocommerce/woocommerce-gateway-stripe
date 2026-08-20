<?php

/**
 * These tests make assertions against class WC_Stripe_Intent_Controller
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

		$this->order = WC_Helper_Order::create_order();
		$this->create_gateway_and_controller();
	}

	/**
	 * Creates the mocked gateway and controller under test.
	 *
	 * The gateway snapshots the main Stripe settings in its constructor, so tests that
	 * change those settings must call this again for the new values to take effect.
	 */
	private function create_gateway_and_controller() {
		$mock_account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->getMock();

		$this->gateway         = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->setConstructorArgs( [ $mock_account ] )
			->setMethods( [ 'maybe_process_upe_redirect', 'has_subscription', 'get_upe_enabled_at_checkout_payment_method_ids' ] )
			->getMock();
		$this->mock_controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();
		$this->mock_controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $this->gateway );
		$this->gateway->expects( $this->any() )
			->method( 'has_subscription' )
			->willReturn( true );
	}

	/**
	 * Test that the capture method is correctly set in the intent based on the settings.
	 *
	 * @param ?string $capture_setting   The value of the `capture` setting (null = not set, 'yes', or 'no').
	 * @param string  $expected_method   The expected `capture_method` in the request ('automatic' or 'manual').
	 * @return void
	 * @dataProvider provide_test_capture_method
	 */
	public function test_capture_method( ?string $capture_setting, string $expected_method ) {
		$this->gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )->willReturn( [ WC_Stripe_Payment_Methods::BLIK ] );

		if ( null !== $capture_setting ) {
			$this->gateway->settings['capture'] = $capture_setting;
		}

		$test_request = function ( $preempt, $parsed_args, $url ) use ( $expected_method ) {
			$this->assertArrayHasKey( 'capture_method', $parsed_args['body'] );
			$this->assertEquals( $expected_method, $parsed_args['body']['capture_method'] );

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

		$this->mock_controller->create_payment_intent( $this->order->get_id(), WC_Stripe_Payment_Methods::BLIK );
	}

	/**
	 * Data provider for `test_capture_method`.
	 *
	 * @return array
	 */
	public function provide_test_capture_method(): array {
		return [
			'default (no setting) uses automatic' => [ null, 'automatic' ],
			'capture=no uses manual'              => [ 'no', 'manual' ],
			'capture=yes uses automatic'          => [ 'yes', 'automatic' ],
		];
	}

	/**
	 * Test that create_payment_intent uses the correct currency.
	 *
	 * @param string|null $order_currency   Currency to set on the order, or null to skip passing an order.
	 * @param string      $global_currency  Currency returned by the woocommerce_currency filter.
	 * @param string      $expected_currency Expected currency in the Stripe API request.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-stripe/issues/4925
	 * @dataProvider provide_create_payment_intent_currency_data
	 */
	public function test_create_payment_intent_chooses_currency( $order_currency, $global_currency, $expected_currency ) {
		$this->gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )->willReturn( [ WC_Stripe_Payment_Methods::BLIK ] );

		$order_id = null;

		if ( null !== $order_currency ) {
			$this->order->set_currency( $order_currency );
			$this->order->save();
			$order_id = $this->order->get_id();
		}

		$currency_callback = function () use ( $global_currency ) {
			return $global_currency;
		};
		add_filter( 'woocommerce_currency', $currency_callback );

		$test_request = function ( $preempt, $parsed_args, $url ) use ( $expected_currency ) {
			$this->assertEquals( $expected_currency, $parsed_args['body']['currency'] );

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

		$this->mock_controller->create_payment_intent( $order_id, WC_Stripe_Payment_Methods::BLIK );

		remove_filter( 'woocommerce_currency', $currency_callback );
	}

	/**
	 * Data provider for test_create_payment_intent_chooses_currency.
	 *
	 * @return array[] [ order_currency, global_currency, expected_currency ]
	 */
	public function provide_create_payment_intent_currency_data() {
		return [
			'uses order currency when order exists' => [ 'USD', 'CAD', 'usd' ],
			'uses global currency without order'    => [ null, 'EUR', 'eur' ],
		];
	}

	/**
	 * Test that intents can only be created upfront for payment methods that do not support deferred intent creation.
	 *
	 * @param string|null $payment_method_type The requested payment method type.
	 * @dataProvider provide_unsupported_create_payment_intent_types
	 */
	public function test_create_payment_intent_rejects_deferred_or_invalid_payment_method_types( $payment_method_type ) {
		$this->gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )->willReturn( [ WC_Stripe_Payment_Methods::CARD ] );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Unable to process your request.' );

		$this->mock_controller->create_payment_intent( $this->order->get_id(), $payment_method_type );
	}

	/**
	 * Data provider for test_create_payment_intent_rejects_deferred_or_invalid_payment_method_types.
	 *
	 * @return array[]
	 */
	public function provide_unsupported_create_payment_intent_types() {
		return [
			'card supports deferred intent creation' => [ WC_Stripe_Payment_Methods::CARD ],
			'missing payment method type'            => [ null ],
			'unknown payment method type'            => [ 'unknown' ],
		];
	}

	/**
	 * Test authorization for AJAX PaymentIntent creation against an existing order.
	 *
	 * @param string $payment_method_type Payment method requested by the caller.
	 * @param string $order_customer      Whether the order belongs to a guest or registered customer.
	 * @param string $current_customer    Whether the caller is a guest, the owner, or another customer.
	 * @param string $order_key_state     Whether the request includes no key, the correct key, or a wrong key.
	 * @param bool   $needs_payment       Whether the order still needs payment.
	 * @param bool   $expected_success    Whether intent creation should be allowed.
	 * @dataProvider provide_create_payment_intent_ajax_authorization_data
	 */
	public function test_create_payment_intent_ajax_authorization(
		string $payment_method_type,
		string $order_customer,
		string $current_customer,
		string $order_key_state,
		bool $needs_payment,
		bool $expected_success
	): void {
		$owner_id = $this->factory->user->create( [ 'role' => 'customer' ] );
		$other_id = $this->factory->user->create( [ 'role' => 'customer' ] );

		$order = WC_Helper_Order::create_order( 'guest' === $order_customer ? 0 : $owner_id );
		if ( ! $needs_payment ) {
			$order->set_status( 'completed' );
			$order->save();
		}

		switch ( $current_customer ) {
			case 'owner':
				$current_user_id = $owner_id;
				break;
			case 'other':
				$current_user_id = $other_id;
				break;
			case 'guest':
			default:
				$current_user_id = 0;
				break;
		}

		$order_key = null;
		if ( 'correct' === $order_key_state ) {
			$order_key = $order->get_order_key();
		} elseif ( 'wrong' === $order_key_state ) {
			$order_key = 'wc_order_wrong_key';
		}

		$controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create_payment_intent' ] )
			->getMock();

		$create_intent_expectation = $controller
			->expects( $expected_success ? $this->once() : $this->never() )
			->method( 'create_payment_intent' )
			->with( $order->get_id(), $payment_method_type );

		if ( $expected_success ) {
			$create_intent_expectation->willReturn(
				[
					'id'            => 'pi_authorized',
					'client_secret' => 'pi_authorized_secret',
				]
			);
		}

		$original_session = WC()->session;
		$session          = $this->getMockBuilder( WC_Session_Handler::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_customer_id' ] )
			->getMock();
		$session->method( 'get_customer_id' )
			->willReturn( $current_user_id > 0 ? (string) $current_user_id : 't_guest_session' );

		$notes_before  = count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) );
		$status_before = $order->get_status();

		wp_set_current_user( $current_user_id );
		WC()->session = $session;

		try {
			$response = $this->run_create_payment_intent_ajax_request(
				$controller,
				$order->get_id(),
				$payment_method_type,
				$order_key
			);
		} finally {
			WC()->session = $original_session;
			wp_set_current_user( 0 );
		}

		$this->assertSame( $expected_success, $response['success'] );

		if ( $expected_success ) {
			$this->assertSame( 'pi_authorized', $response['data']['id'] );
			return;
		}

		$this->assertSame(
			'Unable to process your request. Please reload the page and try again.',
			$response['data']['error']['message']
		);

		$final_order = wc_get_order( $order->get_id() );
		$this->assertSame( '', WC_Stripe_Order_Helper::get_instance()->get_stripe_intent_id( $final_order ) );
		$this->assertSame( $status_before, $final_order->get_status() );
		$this->assertSame( $notes_before, count( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) ) );
	}

	/**
	 * Data provider for AJAX PaymentIntent order authorization.
	 *
	 * @return array[]
	 */
	public function provide_create_payment_intent_ajax_authorization_data(): array {
		$scenarios = [
			//                                                              current, owner, order_key, needs_payment, should_succeed
			'guest cannot use a foreign guest order without its key'    => [ 'guest', 'guest', 'none', true, false ],
			'guest can use a guest order with its key'                  => [ 'guest', 'guest', 'correct', true, true ],
			'guest cannot use a guest order with the wrong key'         => [ 'guest', 'guest', 'wrong', true, false ],
			'guest cannot use a registered customer order with its key' => [ 'owner', 'guest', 'correct', true, false ],
			'other customer cannot use the owner order without its key' => [ 'owner', 'other', 'none', true, false ],
			'other customer cannot use the owner order with its key'    => [ 'owner', 'other', 'correct', true, false ],
			'owner can use their order without its key'                 => [ 'owner', 'owner', 'none', true, true ],
			'owner can use their order with its key'                    => [ 'owner', 'owner', 'correct', true, true ],
			'owner cannot use their order with the wrong key'           => [ 'owner', 'owner', 'wrong', true, false ],
			'owner cannot use an order that no longer requires payment' => [ 'owner', 'owner', 'correct', false, false ],
		];

		$data = [];
		foreach ( [ WC_Stripe_Payment_Methods::BLIK, WC_Stripe_Payment_Methods::ACSS_DEBIT ] as $payment_method_type ) {
			foreach ( $scenarios as $scenario_name => $scenario ) {
				$data[ $payment_method_type . ': ' . $scenario_name ] = array_merge( [ $payment_method_type ], $scenario );
			}
		}

		return $data;
	}

	/**
	 * Test that a missing order is rejected before PaymentIntent creation.
	 *
	 * @param string $payment_method_type Payment method requested by the caller.
	 * @dataProvider provide_nondeferred_payment_method_types
	 */
	public function test_create_payment_intent_ajax_rejects_missing_order( string $payment_method_type ): void {
		$controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create_payment_intent' ] )
			->getMock();
		$controller->expects( $this->never() )
			->method( 'create_payment_intent' );

		$response = $this->run_create_payment_intent_ajax_request(
			$controller,
			999999999,
			$payment_method_type,
			null
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame(
			'Unable to process your request. Please reload the page and try again.',
			$response['data']['error']['message']
		);
	}

	/**
	 * Non-deferred payment methods that create their intents before checkout submission.
	 *
	 * @return array[]
	 */
	public function provide_nondeferred_payment_method_types(): array {
		return [
			'BLIK'       => [ WC_Stripe_Payment_Methods::BLIK ],
			'ACSS Debit' => [ WC_Stripe_Payment_Methods::ACSS_DEBIT ],
		];
	}

	/**
	 * Test that unsupported payment methods remain rejected after order authorization succeeds.
	 *
	 * @param string|null $payment_method_type Payment method requested by the caller.
	 * @dataProvider provide_unsupported_create_payment_intent_types
	 */
	public function test_create_payment_intent_ajax_rejects_deferred_or_invalid_payment_method_types( $payment_method_type ): void {
		$this->gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )
			->willReturn( [ WC_Stripe_Payment_Methods::CARD ] );

		$http_request_count = 0;
		$count_http_request = static function () use ( &$http_request_count ) {
			++$http_request_count;
			return false;
		};
		add_filter( 'pre_http_request', $count_http_request );

		wp_set_current_user( 1 );
		try {
			$response = $this->run_create_payment_intent_ajax_request(
				$this->mock_controller,
				$this->order->get_id(),
				$payment_method_type,
				$this->order->get_order_key()
			);
		} finally {
			wp_set_current_user( 0 );
			remove_filter( 'pre_http_request', $count_http_request );
		}

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, $http_request_count );
		$this->assertSame( '', WC_Stripe_Order_Helper::get_instance()->get_stripe_intent_id( $this->order ) );
	}

	/**
	 * Run the PaymentIntent AJAX handler and decode its JSON response.
	 *
	 * @param WC_Stripe_Intent_Controller $controller          Controller under test.
	 * @param int                         $order_id             Order ID supplied by the caller.
	 * @param string|null                 $payment_method_type Payment method supplied by the caller.
	 * @param string|null                 $order_key           Order key supplied by the caller.
	 * @return array
	 */
	private function run_create_payment_intent_ajax_request(
		WC_Stripe_Intent_Controller $controller,
		int $order_id,
		$payment_method_type,
		?string $order_key
	): array {
		$original_post    = $_POST;
		$original_request = $_REQUEST;
		$request_data     = [
			'stripe_order_id'     => $order_id,
			'payment_method_type' => $payment_method_type,
			'_ajax_nonce'         => wp_create_nonce( 'wc_stripe_create_payment_intent_nonce' ),
		];

		if ( null !== $order_key ) {
			$request_data['order_key'] = $order_key;
		}

		$_POST    = $request_data;
		$_REQUEST = $request_data;

		Ajax_Test_Helper::init_hooks();
		$buffer_level = ob_get_level();
		ob_start();

		try {
			$controller->create_payment_intent_ajax();
			$output = ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			Ajax_Test_Helper::remove_hooks();
			$_POST    = $original_post;
			$_REQUEST = $original_request;
		}

		$response = json_decode( $output, true );
		$this->assertIsArray( $response );

		return $response;
	}

	/**
	 * Test that setup intents can only be created upfront for payment methods that do not support deferred intent creation.
	 *
	 * @param string|null $payment_method_type The requested payment method type.
	 * @dataProvider provide_unsupported_init_setup_intent_types
	 */
	public function test_init_setup_intent_rejects_deferred_or_invalid_payment_method_types( $payment_method_type ) {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Unable to process your request.' );

		$this->mock_controller->init_setup_intent( $payment_method_type );
	}

	/**
	 * Data provider for test_init_setup_intent_rejects_deferred_or_invalid_payment_method_types.
	 *
	 * @return array[]
	 */
	public function provide_unsupported_init_setup_intent_types() {
		return [
			'card supports deferred intent creation' => [ WC_Stripe_Payment_Methods::CARD ],
			'missing payment method type'            => [ null ],
			'unknown payment method type'            => [ 'unknown' ],
		];
	}

	/**
	 * Test that create_payment_intent includes the bank statement descriptor for non-card
	 * payment methods that create their intent upfront (non-deferred, e.g. ACSS Debit and BLIK).
	 *
	 * @param string|null $payment_method_type The payment method type requested for the intent.
	 * @param string      $local_descriptor    The locally configured statement descriptor.
	 * @param array       $account_data        The Stripe account data to mock.
	 * @param string|null $expected_descriptor The expected statement_descriptor in the request, or null if it must not be set.
	 *
	 * @dataProvider provide_create_payment_intent_statement_descriptor_data
	 */
	public function test_create_payment_intent_statement_descriptor( $payment_method_type, $local_descriptor, $account_data, $expected_descriptor ) {
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['statement_descriptor'] = $local_descriptor;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->create_gateway_and_controller();

		$this->gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )->willReturn( [ $payment_method_type ] );

		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_cached_account_data' ] )
			->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn( $account_data );

		$request_body = null;
		$test_request = function ( $preempt, $parsed_args ) use ( &$request_body ) {
			$request_body = $parsed_args['body'];

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'pi_mock',
						'client_secret' => 'cs_mock',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_payment_intent( $this->order->get_id(), $payment_method_type );

		$this->assertIsArray( $request_body, 'The payment intent creation request should have been made.' );

		if ( null === $expected_descriptor ) {
			$this->assertArrayNotHasKey( 'statement_descriptor', $request_body );
		} else {
			$this->assertArrayHasKey( 'statement_descriptor', $request_body );
			$this->assertSame( $expected_descriptor, $request_body['statement_descriptor'] );
		}
	}

	/**
	 * Data provider for test_create_payment_intent_statement_descriptor.
	 *
	 * @return array[] [ payment_method_type, local_descriptor, account_data, expected_descriptor ]
	 */
	public function provide_create_payment_intent_statement_descriptor_data() {
		$account_with_descriptor = [
			'settings' => [
				'payments' => [
					'statement_descriptor' => 'ACCOUNT DESCRIPTOR',
				],
			],
		];

		return [
			'blik uses the local descriptor'          => [ WC_Stripe_UPE_Payment_Method_BLIK::STRIPE_ID, 'WOO STORE', $account_with_descriptor, 'WOO STORE' ],
			'acss falls back to account descriptor'   => [ WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID, '', $account_with_descriptor, 'ACCOUNT DESCRIPTOR' ],
			'no descriptor available leaves it unset' => [ WC_Stripe_UPE_Payment_Method_BLIK::STRIPE_ID, '', [], null ],
		];
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
	 * Level3 data is card-only: the controller must persist the selected payment type to the
	 * order before the intent request so the level3 gate can exclude non-card methods.
	 *
	 * @param string $selected_payment_type The selected payment type posted with the confirmation token.
	 * @param bool   $expect_level3         Whether the outgoing request should carry level3 data.
	 * @dataProvider provide_test_confirmation_token_level3
	 */
	public function test_create_and_confirm_payment_intent_with_confirmation_token_gates_level3( $selected_payment_type, $expect_level3 ) {
		// The level3 gate only applies to US-based stores.
		update_option( 'woocommerce_default_country', 'US:CA' );

		$payment_information = $this->get_confirmation_token_payment_information( $selected_payment_type );

		$requests_seen = [];
		$test_request  = function ( $preempt, $parsed_args, $url ) use ( &$requests_seen ) {
			if ( false !== strpos( $url, 'payment_intents' ) ) {
				$requests_seen[] = $parsed_args['body'];
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [ 'id' => 'pi_mock' ] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_and_confirm_payment_intent( $payment_information );

		$this->assertCount( 1, $requests_seen );
		if ( $expect_level3 ) {
			$this->assertArrayHasKey( 'level3', $requests_seen[0] );
		} else {
			$this->assertArrayNotHasKey( 'level3', $requests_seen[0] );
		}
		$this->assertSame( $selected_payment_type, $this->order->get_meta( '_stripe_upe_payment_type' ) );
	}

	/**
	 * Same as the create case: the confirm call on an existing intent must not carry level3
	 * data for non-card methods.
	 *
	 * @param string $selected_payment_type The selected payment type posted with the confirmation token.
	 * @param bool   $expect_level3         Whether the outgoing request should carry level3 data.
	 * @dataProvider provide_test_confirmation_token_level3
	 */
	public function test_update_and_confirm_payment_intent_with_confirmation_token_gates_level3( $selected_payment_type, $expect_level3 ) {
		// The level3 gate only applies to US-based stores.
		update_option( 'woocommerce_default_country', 'US:CA' );

		$payment_information = $this->get_confirmation_token_payment_information( $selected_payment_type );
		$payment_intent      = (object) [ 'id' => 'pi_mock' ];

		$requests_seen = [];
		$test_request  = function ( $preempt, $parsed_args, $url ) use ( &$requests_seen ) {
			if ( false !== strpos( $url, 'payment_intents/pi_mock/confirm' ) ) {
				$requests_seen[] = $parsed_args['body'];
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [ 'id' => 'pi_mock' ] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->update_and_confirm_payment_intent( $payment_intent, $payment_information );

		$this->assertCount( 1, $requests_seen );
		if ( $expect_level3 ) {
			$this->assertArrayHasKey( 'level3', $requests_seen[0] );
		} else {
			$this->assertArrayNotHasKey( 'level3', $requests_seen[0] );
		}
		$this->assertSame( $selected_payment_type, $this->order->get_meta( '_stripe_upe_payment_type' ) );
	}

	/**
	 * Provider for the confirmation-token level3 gating tests.
	 *
	 * @return array
	 */
	public function provide_test_confirmation_token_level3() {
		return [
			'amazon_pay does not get level3' => [ WC_Stripe_Payment_Methods::AMAZON_PAY, false ],
			'card keeps level3'              => [ WC_Stripe_Payment_Methods::CARD, true ],
		];
	}

	/**
	 * Payment information mirroring what the ECE confirmation-token flow prepares: a confirmation
	 * token instead of a payment method ID, and no UPE payment type persisted on the order yet.
	 *
	 * @param string $selected_payment_type The selected payment type.
	 * @return array
	 */
	private function get_confirmation_token_payment_information( $selected_payment_type ) {
		return [
			'amount'                        => 100,
			'confirmation_token'            => 'ctoken_mock',
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
			'shipping'                      => [],
			'selected_payment_type'         => $selected_payment_type,
			'payment_method_types'          => [ $selected_payment_type ],
			'return_url'                    => 'https://example.com/return',
			'is_using_saved_payment_method' => false,
			'save_payment_method_to_store'  => false,
			'has_subscription'              => false,
		];
	}

	/**
	 * Test for setting the `setup_future_usage` parameter in the
	 *  create_and_confirm_payment_intent intent creation request.
	 */
	public function test_intent_creation_request_setup_future_usage() {
		$payment_information = [
			'amount'                        => 100,
			'capture_method'                => 'automatic',
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

	/**
	 * Test presence of idempotency key when sending the payment intent request.
	 */
	public function test_idempotency_key_for_create_and_confirm_payment_intent() {
		$payment_information = [
			'amount'                        => 100,
			'capture_method'                => 'automatic',
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
			'save_payment_method_to_store'  => true,
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			$this->assertNotEmpty( $parsed_args['headers']['Idempotency-Key'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_and_confirm_payment_intent( $payment_information );
	}

	/**
	 * When the Dynamic Payment Methods flag is set, the request drops payment_method_types in favour
	 * of automatic_payment_methods, carries the exclusion list, and includes the return_url that
	 * allow_redirects requires.
	 */
	public function test_create_and_confirm_payment_intent_with_automatic_payment_methods() {
		$excluded = [ WC_Stripe_Payment_Methods::AMAZON_PAY, 'konbini' ];

		$payment_information                                  = $this->get_base_payment_information();
		$payment_information['automatic_payment_methods']     = true;
		$payment_information['excluded_payment_method_types'] = $excluded;
		$payment_information['return_url']                    = 'https://example.com/return';

		$test_request = function ( $preempt, $parsed_args, $url ) use ( $excluded ) {
			$body = $parsed_args['body'];

			$this->assertArrayNotHasKey( 'payment_method_types', $body );
			$this->assertSame(
				[
					'enabled'         => 'true',
					'allow_redirects' => 'always',
				],
				$body['automatic_payment_methods']
			);
			$this->assertSame( $excluded, $body['excluded_payment_method_types'] );
			$this->assertSame( 'https://example.com/return', $body['return_url'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_and_confirm_payment_intent( $payment_information );
	}

	/**
	 * Without the flag, the request keeps the explicit payment_method_types list and sends no
	 * automatic_payment_methods.
	 */
	public function test_create_and_confirm_payment_intent_without_automatic_payment_methods() {
		$payment_information = $this->get_base_payment_information();

		$test_request = function ( $preempt, $parsed_args, $url ) {
			$body = $parsed_args['body'];

			$this->assertSame( [ WC_Stripe_Payment_Methods::CARD ], $body['payment_method_types'] );
			$this->assertArrayNotHasKey( 'automatic_payment_methods', $body );
			$this->assertArrayNotHasKey( 'excluded_payment_method_types', $body );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode( [] ),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->mock_controller->create_and_confirm_payment_intent( $payment_information );
	}

	/**
	 * Minimal valid payment information for a card create_and_confirm_payment_intent request.
	 *
	 * @return array
	 */
	private function get_base_payment_information() {
		return [
			'amount'                        => 100,
			'capture_method'                => 'automatic',
			'currency'                      => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			'customer'                      => 'cus_mock',
			'level3'                        => [],
			'metadata'                      => [ '_stripe_metadata' => '123' ],
			'order'                         => $this->order,
			'payment_method'                => 'pm_mock',
			'shipping'                      => [],
			'selected_payment_type'         => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'          => [ WC_Stripe_Payment_Methods::CARD ],
			'is_using_saved_payment_method' => false,
			'save_payment_method_to_store'  => false,
			'has_subscription'              => false,
		];
	}

	/**
	 * Test for create_and_confirm_setup_intent method.
	 */
	public function test_create_and_confirm_setup_intent() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::CARD ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify the request is made to the setup_intents endpoint
			$this->assertStringContainsString( 'setup_intents', $url );

			// Verify required parameters
			$this->assertEquals( 'pm_mock', $parsed_args['body']['payment_method'] );
			$this->assertEquals( 'cus_mock', $parsed_args['body']['customer'] );
			$this->assertEquals( 'true', $parsed_args['body']['confirm'] );
			$this->assertEquals( 'true', $parsed_args['body']['use_stripe_sdk'] );
			// Return URL should not be included for card payment.
			$this->assertArrayNotHasKey( 'return_url', $parsed_args['body'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'succeeded',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'seti_mock', $result->id );
		$this->assertEquals( 'secret_mock', $result->client_secret );
		$this->assertEquals( 'succeeded', $result->status );
	}

	/**
	 * Test that SEPA setup intents include mandate data.
	 */
	public function test_create_and_confirm_setup_intent_with_sepa() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::SEPA_DEBIT,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::SEPA_DEBIT ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify mandate data is included for SEPA
			$this->assertArrayHasKey( 'mandate_data', $parsed_args['body'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'succeeded',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'seti_mock', $result->id );
	}

	/**
	 * Test that Boleto setup intents have delayed confirmation.
	 */
	public function test_create_and_confirm_setup_intent_with_boleto() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::BOLETO,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::BOLETO ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify confirmation is delayed for Boleto
			$this->assertEquals( 'false', $parsed_args['body']['confirm'] );
			// Return URL should not be included when confirm is false
			$this->assertArrayNotHasKey( 'return_url', $parsed_args['body'] );

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'requires_confirmation',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'requires_confirmation', $result->status );
	}

	/**
	 * Test error handling in setup intent creation.
	 */
	public function test_create_and_confirm_setup_intent_error() {
		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::CARD ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $this->order,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'error' => [
							'message' => 'Invalid payment method',
						],
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );

		$this->expectException( WC_Stripe_Exception::class );
		$this->mock_controller->create_and_confirm_setup_intent( $payment_information );
	}

	/**
	 * Test mandate options for card payment method in setup intent for subscription.
	 */
	public function test_mandate_options_for_card_setup_intent_for_subscription() {
		// create a subscription
		$subscription = new WC_Subscription();
		$subscription->set_status( 'active' );
		$subscription->set_total( 100 );
		$subscription->set_currency( 'USD' );
		$subscription->set_customer_id( 'cus_mock' );
		$subscription->set_payment_method( 'pm_mock' );
		$subscription->save();

		WC_Subscriptions_Switcher::$cart_contains_switches         = false;
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order = [ $subscription ];

		// Manually add the subscription filter that would normally be added by maybe_init_subscriptions()
		add_filter( 'wc_stripe_generate_create_intent_request', [ $this->gateway, 'add_subscription_information_to_intent' ], 10, 4 );

		$payment_information = [
			'payment_method'        => 'pm_mock',
			'customer'              => 'cus_mock',
			'selected_payment_type' => WC_Stripe_Payment_Methods::CARD,
			'payment_method_types'  => [ WC_Stripe_Payment_Methods::CARD ],
			'return_url'            => 'https://example.com/return',
			'order'                 => $subscription,
			'use_stripe_sdk'        => 'true',
		];

		$test_request = function ( $preempt, $parsed_args, $url ) {
			// Verify card mandate options are present
			$this->assertArrayHasKey( 'payment_method_options', $parsed_args['body'] );
			$this->assertArrayHasKey( WC_Stripe_Payment_Methods::CARD, $parsed_args['body']['payment_method_options'] );

			// Verify mandate options for card include currency
			$this->assertArrayHasKey( 'mandate_options', $parsed_args['body']['payment_method_options'][ WC_Stripe_Payment_Methods::CARD ] );
			$this->assertArrayHasKey( 'currency', $parsed_args['body']['payment_method_options'][ WC_Stripe_Payment_Methods::CARD ]['mandate_options'] );

			// Verify currency matches order currency
			$this->assertEquals(
				strtolower( $this->order->get_currency() ),
				$parsed_args['body']['payment_method_options'][ WC_Stripe_Payment_Methods::CARD ]['mandate_options']['currency']
			);

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => json_encode(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'secret_mock',
						'status'        => 'succeeded',
					]
				),
			];
		};

		add_filter( 'pre_http_request', $test_request, 10, 3 );
		$result = $this->mock_controller->create_and_confirm_setup_intent( $payment_information );

		$this->assertEquals( 'succeeded', $result->status );
	}

	/**
	 * Test that rate limiting works after a failed attempt.
	 */
	public function test_rate_limiting_on_consecutive_failed_calls() {
		Ajax_Test_Helper::init_hooks();

		wp_set_current_user( 1 );
		$_POST['wc-stripe-payment-method'] = 'pm_test_123';
		$_POST['wc-stripe-payment-type']   = WC_Stripe_Payment_Methods::CARD;
		// First call with invalid nonce - should fail and trigger rate limiting
		$_POST['_ajax_nonce'] = 'invalid_nonce';

		ob_start();
		$this->mock_controller->create_and_confirm_setup_intent_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'error', $response['data'] );
		$this->assertEquals( 'Unable to verify your request. Please refresh the page and try again.', $response['data']['error']['message'] );

		// Second call should fail due to rate limiting, regardless of nonce.
		$_POST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_create_and_confirm_setup_intent_nonce' );

		ob_start();
		$this->mock_controller->create_and_confirm_setup_intent_ajax();
		$output = ob_get_clean();

		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'error', $response['data'] );
		$this->assertEquals( 'You cannot add a new payment method so soon after the previous one.', $response['data']['error']['message'] );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Create a subscription and configure the wcs_get_subscription mock to return it.
	 *
	 * @param int $owner_id User ID who owns the subscription.
	 * @return WC_Subscription
	 */
	private function create_mock_subscription( int $owner_id ): WC_Subscription {
		$subscription = new WC_Subscription();
		$subscription->set_customer_id( $owner_id );
		$subscription->set_status( 'active' );
		$subscription->save();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return ( (int) $id === $subscription->get_id() ) ? $subscription : false;
			}
		);

		return $subscription;
	}

	/**
	 * Test that update_order_status_ajax does not fail the order when a customer cancels the payment
	 * (e.g. closes the Klarna popup), and returns an appropriate JSON error response.
	 */
	public function test_update_order_status_ajax_cancellation_does_not_fail_order() {
		Ajax_Test_Helper::init_hooks();

		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$intent_id = 'pi_mock_cancel';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->save();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'process_order_for_confirmed_intent' )
			->willThrowException( new WC_Stripe_Payment_Cancelled_Exception( 'Customer cancelled checkout on Klarna' ) );

		$controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();

		$controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		// Response must indicate failure so the frontend can show a message.
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'error', $response['data'] );
		$this->assertStringContainsString( 'cancelled', strtolower( $response['data']['error']['message'] ) );

		// Order must NOT be set to failed — the customer should be able to retry.
		$final_order = wc_get_order( $order_id );
		$this->assertNotEquals( 'failed', $final_order->get_status() );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * A request whose intent_id does not match the order's stored intent (an attacker replaying a
	 * valid generic guest nonce against a victim order_id) must be rejected before any order
	 * mutation: the order is not set to failed and no note is added to it.
	 */
	public function test_update_order_status_ajax_intent_mismatch_does_not_fail_order() {
		Ajax_Test_Helper::init_hooks();

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_stripe_intent_id', 'pi_victim_real' );
		$order->set_status( 'completed' );
		$order->save();
		$order_id = $order->get_id();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		// A mismatch is rejected before processing starts, so the gateway is never asked to process.
		$gateway->expects( $this->never() )
			->method( 'process_order_for_confirmed_intent' );

		$controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();

		$controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $gateway );

		$notes_before = count( wc_get_order_notes( [ 'order_id' => $order_id ] ) );

		// Valid generic guest nonce, victim order_id, attacker-supplied unrelated intent_id.
		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = 'pi_attacker_unrelated';
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );

		// The victim order must be untouched: still completed, and no spam note added.
		$final_order = wc_get_order( $order_id );
		$this->assertEquals( 'completed', $final_order->get_status() );
		$this->assertSame( $notes_before, count( wc_get_order_notes( [ 'order_id' => $order_id ] ) ) );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * A genuine payment failure raised once gateway processing has begun must still fail the order.
	 * This guards the $processing_started flag from suppressing legitimate payment-failure handling.
	 */
	public function test_update_order_status_ajax_payment_failure_still_fails_order() {
		Ajax_Test_Helper::init_hooks();

		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_matches_order';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->save();
		$order_id = $order->get_id();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'process_order_for_confirmed_intent' )
			->willThrowException( new WC_Stripe_Exception( 'processing_error', 'Your card was declined.' ) );

		$controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();

		$controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'failed', wc_get_order( $order_id )->get_status() );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Builds a controller whose gateway is mocked so process_order_for_confirmed_intent can be
	 * asserted against without hitting Stripe. Mirrors the setup of the sibling AJAX tests.
	 *
	 * @param WC_Stripe_UPE_Payment_Gateway $gateway The mocked gateway to return from get_gateway().
	 * @return WC_Stripe_Intent_Controller
	 */
	private function build_controller_with_gateway( $gateway ) {
		$controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_gateway' ] )
			->getMock();

		$controller->expects( $this->any() )
			->method( 'get_gateway' )
			->willReturn( $gateway );

		return $controller;
	}

	/**
	 * When a concurrent request (e.g. the deferred webhook) has already moved the order to a
	 * terminal status, the AJAX handler must skip re-processing and return the thank-you URL.
	 *
	 * @param string $status A status that indicates the order was already settled elsewhere.
	 * @dataProvider provide_already_settled_statuses
	 */
	public function test_update_order_status_ajax_skips_when_order_already_settled( string $status ) {
		Ajax_Test_Helper::init_hooks();

		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_already_settled';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->set_status( $status );
		$order->save();
		$order_id = $order->get_id();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		// Already settled by another request; this one must not process it again.
		$gateway->expects( $this->never() )
			->method( 'process_order_for_confirmed_intent' );

		$controller = $this->build_controller_with_gateway( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$response = json_decode( ob_get_clean(), true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'return_url', $response['data'] );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Data provider of statuses that mean the order was already settled by a concurrent request.
	 *
	 * @return array
	 */
	public function provide_already_settled_statuses(): array {
		return [
			'processing' => [ 'processing' ],
			'completed'  => [ 'completed' ],
			'on-hold'    => [ 'on-hold' ],
		];
	}

	/**
	 * When the redirect handler has already processed this order (flag set), the AJAX handler must
	 * skip re-processing even if the status guard has not caught up yet.
	 */
	public function test_update_order_status_ajax_skips_when_redirect_already_processed() {
		Ajax_Test_Helper::init_hooks();

		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_redirect_processed';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->set_status( 'pending' );
		$order->save();
		$order_id = $order->get_id();

		// The helper's meta setter only stages the value; persist it so the handler's fresh
		// order load sees the flag.
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_redirect_processed( $order, true );
		$order->save();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		$gateway->expects( $this->never() )
			->method( 'process_order_for_confirmed_intent' );

		$controller = $this->build_controller_with_gateway( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$response = json_decode( ob_get_clean(), true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'return_url', $response['data'] );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * A request that finds the order already locked by another in-flight request must skip
	 * processing AND leave the existing lock intact — releasing the winner's lock would defeat
	 * the mutual exclusion the lock exists to provide.
	 */
	public function test_update_order_status_ajax_skips_and_preserves_existing_lock() {
		Ajax_Test_Helper::init_hooks();

		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_locked';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->set_status( 'pending' );
		$order->save();
		$order_id = $order->get_id();

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		// Simulate the concurrent request already holding the lock.
		$this->assertFalse( $order_helper->lock_order_payment( $order ) );

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		$gateway->expects( $this->never() )
			->method( 'process_order_for_confirmed_intent' );

		$controller = $this->build_controller_with_gateway( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$response = json_decode( ob_get_clean(), true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'return_url', $response['data'] );

		// The winner's lock must survive this losing request.
		$this->assertNotEmpty( $order_helper->get_order_existing_payment_lock( wc_get_order( $order_id ) ) );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * On the happy path the handler acquires the lock, processes once, and releases the lock so a
	 * legitimate later retry (or the webhook) is not blocked by a stale lock.
	 */
	public function test_update_order_status_ajax_releases_lock_after_processing() {
		Ajax_Test_Helper::init_hooks();

		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_happy_path';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->set_status( 'pending' );
		$order->save();
		$order_id = $order->get_id();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'process_order_for_confirmed_intent' );

		$controller = $this->build_controller_with_gateway( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$response = json_decode( ob_get_clean(), true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'return_url', $response['data'] );

		// Lock must be released after processing completes.
		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( wc_get_order( $order_id ) ) );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * A genuine processing failure must still release the lock, otherwise a stale 5-minute lock
	 * would block the customer's retry and the webhook fallback.
	 */
	public function test_update_order_status_ajax_releases_lock_after_processing_failure() {
		Ajax_Test_Helper::init_hooks();

		$order     = WC_Helper_Order::create_order();
		$intent_id = 'pi_failure';
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->set_status( 'pending' );
		$order->save();
		$order_id = $order->get_id();

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'process_order_for_confirmed_intent' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'process_order_for_confirmed_intent' )
			->willThrowException( new WC_Stripe_Exception( 'processing_error', 'Your card was declined.' ) );

		$controller = $this->build_controller_with_gateway( $gateway );

		$_POST['order_id']       = $order_id;
		$_POST['intent_id']      = $intent_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->update_order_status_ajax();
		$response = json_decode( ob_get_clean(), true );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'failed', wc_get_order( $order_id )->get_status() );

		// Even on failure the lock must be released.
		$this->assertEmpty( WC_Stripe_Order_Helper::get_instance()->get_order_existing_payment_lock( wc_get_order( $order_id ) ) );

		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Test that confirm_change_payment rejects requests from users who do not own the subscription.
	 */
	public function test_confirm_change_payment_rejects_non_owner() {
		Ajax_Test_Helper::init_hooks();

		$owner        = $this->factory->user->create( [ 'role' => 'customer' ] );
		$subscription = $this->create_mock_subscription( $owner );

		// Log in as a different user.
		$non_owner = $this->factory->user->create( [ 'role' => 'customer' ] );
		wp_set_current_user( $non_owner );

		$_POST['order_id']       = $subscription->get_id();
		$_POST['intent_id']      = 'seti_mock_123';
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$this->mock_controller->confirm_change_payment_from_setup_intent_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'permission', strtolower( $response['data']['error']['message'] ) );

		WC_Subscriptions::set_wcs_get_subscription( null );
		Ajax_Test_Helper::remove_hooks();
	}

	/**
	 * Test that confirm_change_payment allows the subscription owner to proceed past the ownership check.
	 */
	public function test_confirm_change_payment_allows_owner(): void {
		Ajax_Test_Helper::init_hooks();

		$owner        = $this->factory->user->create( [ 'role' => 'customer' ] );
		$subscription = $this->create_mock_subscription( $owner );

		wp_set_current_user( $owner );

		$_POST['order_id']       = $subscription->get_id();
		$_POST['intent_id']      = 'seti_mock_123';
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$this->mock_controller->confirm_change_payment_from_setup_intent_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		// Should not fail with a permission error.
		if ( ! $response['success'] ) {
			$this->assertStringNotContainsString( 'permission', strtolower( $response['data']['error']['message'] ) );
		}

		WC_Subscriptions::set_wcs_get_subscription( null );
		Ajax_Test_Helper::remove_hooks();
	}

	public function test_confirm_change_payment_succeeds_when_token_relink_finds_no_match() {
		Ajax_Test_Helper::init_hooks();

		$owner        = $this->factory->user->create( [ 'role' => 'customer' ] );
		$subscription = $this->create_mock_subscription( $owner );
		wp_set_current_user( $owner );

		// A confirmed token whose Stripe id is deliberately NOT among the user's
		// saved tokens, so replace_subscription_payment_token() finds no match.
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( 'pm_unsaved' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );

		$gateway = $this->getMockBuilder( 'WC_Stripe_UPE_Payment_Gateway' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_token_from_setup_intent' ] )
			->getMock();
		$gateway->expects( $this->once() )
			->method( 'create_token_from_setup_intent' )
			->willReturn( $token );

		$controller = $this->getMockBuilder( 'WC_Stripe_Intent_Controller' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_upe_gateway' ] )
			->getMock();
		$controller->expects( $this->any() )
			->method( 'get_upe_gateway' )
			->willReturn( $gateway );

		$_POST['order_id']       = $subscription->get_id();
		$_POST['intent_id']      = 'seti_mock_123';
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wc_stripe_update_order_status_nonce' );

		ob_start();
		$controller->confirm_change_payment_from_setup_intent_ajax();
		$output   = ob_get_clean();
		$response = json_decode( $output, true );

		$this->assertTrue( $response['success'], 'A display-only token-relink miss must not fail an authenticated change.' );

		WC_Subscriptions::set_wcs_get_subscription( null );
		Ajax_Test_Helper::remove_hooks();
	}
}
