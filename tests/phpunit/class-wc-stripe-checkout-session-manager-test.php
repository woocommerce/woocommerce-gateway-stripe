<?php

/**
 * Tests for native WooCommerce Checkout Session synchronization.
 */
class WC_Stripe_Checkout_Session_Manager_Test extends WP_UnitTestCase {
	/**
	 * Reset cart and session state used by the manager.
	 */
	public function set_up(): void {
		parent::set_up();
		WC()->session->init();
		WC()->session->set( 'wc_stripe_checkout_session', null );
		WC()->cart->empty_cart();
		$this->set_store_api_sync_requested( false );
	}

	/**
	 * Clean up the active Checkout Session context between tests.
	 */
	public function tear_down(): void {
		$record = WC()->session->get( 'wc_stripe_checkout_session' );
		if ( is_array( $record ) && ! empty( $record['session_id'] ) ) {
			WC_Stripe_Checkout_Session_Context::delete_context( $record['session_id'] );
		}

		WC()->session->set( 'wc_stripe_checkout_session', null );
		WC()->cart->empty_cart();
		$this->set_store_api_sync_requested( false );
		parent::tear_down();
	}

	/**
	 * Get a reflection for the store_api_sync_requested property.
	 *
	 * @return ReflectionProperty
	 */
	private function get_store_api_sync_requested_reflection(): ReflectionProperty {
		$property = new ReflectionProperty( WC_Stripe_Checkout_Session_Lifecycle::class, 'store_api_sync_requested' );
		$property->setAccessible( true );

		return $property;
	}

	/**
	 * Set the store_api_sync_requested property value.
	 *
	 * @param bool $requested The value to set for the flag.
	 * @return void
	 */
	private function set_store_api_sync_requested( bool $requested ): void {
		$this->get_store_api_sync_requested_reflection()->setValue( null, $requested );
	}

	/**
	 * Read the value of the store_api_sync_requested property.
	 *
	 * @return bool
	 */
	private function is_store_api_sync_requested(): bool {
		return (bool) $this->get_store_api_sync_requested_reflection()->getValue();
	}

	/**
	 * Make the store eligible for Adaptive Pricing.
	 *
	 * @return callable Restores the settings, transients, and services this changed.
	 */
	private function enable_adaptive_pricing(): callable {
		$original_settings = WC_Stripe_Helper::get_stripe_settings();
		$original_account  = WC_Stripe::get_instance()->account;

		// Write the full set rather than merging onto whatever is stored: otherwise settings written
		// from a set_up_before_class() elsewhere in the suite are committed outside the per-test
		// transaction and survive into this test.
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'adaptive_pricing'           => 'yes',
				'optimized_checkout_element' => 'yes',
				'pmc_enabled'                => 'yes',
				'capture'                    => 'yes',
				'testmode'                   => 'yes',
				'webhook_data'               => [
					'id'     => 'we_live',
					'secret' => 'whsec_live',
				],
				'test_webhook_data'          => [
					'id'     => 'we_test',
					'secret' => 'whsec_test',
				],
			]
		);

		$reflection = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$reflection->setAccessible( true );
		$reflection->setValue( WC_Stripe::get_instance(), null );

		$webhook_status_cache_key = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Account::class, 'WEBHOOK_STATUS_CACHE_KEY', 'string' );
		// is_webhook_enabled() short-circuits on a cached status, so we don't hit the Stripe API here.
		WC_Stripe_Database_Cache::set_with_mode( $webhook_status_cache_key, 'enabled', HOUR_IN_SECONDS, 'live' );
		WC_Stripe_Database_Cache::set_with_mode( $webhook_status_cache_key, 'enabled', HOUR_IN_SECONDS, 'test' );

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_cached_account_data' ] )
			->getMock();
		$account->method( 'get_cached_account_data' )->willReturn( [ 'country' => 'US' ] );
		WC_Stripe::get_instance()->account = $account;

		// The cart-eligibility loop reads these doubles, which have no default value.
		WC_Subscriptions_Product::set_is_subscription( false );
		WC_Subscriptions_Product::set_subscription_product_ids( [] );
		WC_Pre_Orders_Product::set_is_pre_order_charged_upon_release( false );
		WC_Deposits_Product_Manager::set_deposits_enabled( false );

		return function () use ( $original_settings, $original_account, $reflection, $webhook_status_cache_key ): void {
			WC_Stripe_Helper::update_main_stripe_settings( $original_settings );
			WC_Stripe_Database_Cache::delete_with_mode( $webhook_status_cache_key, 'live' );
			WC_Stripe_Database_Cache::delete_with_mode( $webhook_status_cache_key, 'test' );
			WC_Stripe::get_instance()->account = $original_account;
			WC_Subscriptions_Product::set_is_subscription( false );
			WC_Subscriptions_Product::set_subscription_product_ids( [] );
			WC_Pre_Orders_Product::set_is_pre_order_charged_upon_release( false );
			WC_Deposits_Product_Manager::set_deposits_enabled( false );
			$reflection->setValue( WC_Stripe::get_instance(), null );
		};
	}

	/**
	 * Extract the JSON payload the classic fragment carries.
	 *
	 * @param string $fragment_html Classic fragment markup.
	 * @return string
	 */
	private function get_fragment_session_data( string $fragment_html ): string {
		preg_match( '/data-checkout-session="([^"]*)"/', $fragment_html, $matches );

		return html_entity_decode( $matches[1] ?? '', ENT_QUOTES );
	}

	/**
	 * Creation stores identifiers in the WooCommerce session and reuses them while totals are unchanged.
	 */
	public function test_synchronize_creates_and_reuses_session_for_unchanged_cart(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 12.34 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$request_count = 0;
		$session_id    = 'cs_test_native_create';
		$mock_request  = static function ( $return_value, $parsed_args, $url ) use ( &$request_count, $session_id ) {
			if ( 'https://api.stripe.com/v1/checkout/sessions' !== $url ) {
				return $return_value;
			}

			++$request_count;
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'id'            => $session_id,
						'client_secret' => 'cs_test_native_secret',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$reused  = $manager->synchronize();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
		}

		$this->assertSame( 1, $request_count );
		$this->assertSame( $session_id, $created['session_id'] );
		$this->assertSame( 'cs_test_native_secret', $created['client_secret'] );
		$this->assertIsString( $created['revision'] );
		$this->assertNotSame( '', $created['revision'] );
		$this->assertSame( $created, $reused );

		$context = WC_Stripe_Checkout_Session_Context::get_context( $session_id );
		$this->assertIsArray( $context );
		$this->assertSame(
			WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() ),
			$context['amount']
		);
	}

	/**
	 * We must create checkout sessions using the merchant's Payment Method Configuration ID.
	 *
	 * @param string|null $configuration_id The cached PMC ID, or null when PMC is disabled or unavailable.
	 *
	 * @dataProvider provide_create_session_payment_method_configuration_data
	 */
	public function test_create_session_with_payment_method_configuration( ?string $configuration_id ): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 12.34 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();
		if ( null === $configuration_id ) {
			PMC_Test_Helper::disable_pmc();
		} else {
			PMC_Test_Helper::enable_pmc( true );
			PMC_Test_Helper::cache_mocked_configuration( $configuration_id );
		}

		$captured_create = null;
		$capture_body    = static function ( $request, $api ) use ( &$captured_create ) {
			if ( 'checkout/sessions' === $api ) {
				$captured_create = $request;
			}
			return $request;
		};
		$mock_response   = static function ( $return_value, $parsed_args, $url ) {
			if ( 'https://api.stripe.com/v1/checkout/sessions' !== $url ) {
				return $return_value;
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'id'            => 'cs_test_pmc',
						'client_secret' => 'cs_test_pmc_secret',
					]
				),
			];
		};
		add_filter( 'wc_stripe_request_body', $capture_body, 10, 2 );
		add_filter( 'pre_http_request', $mock_response, 10, 3 );

		try {
			( new WC_Stripe_Checkout_Session_Manager() )->create_session();
		} finally {
			remove_filter( 'wc_stripe_request_body', $capture_body, 10 );
			remove_filter( 'pre_http_request', $mock_response, 10 );
			WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();
			$product->delete( true );
			WC()->cart->empty_cart();
		}

		$this->assertIsArray( $captured_create );
		if ( null === $configuration_id ) {
			$this->assertArrayNotHasKey( 'payment_method_configuration', $captured_create );
		} else {
			$this->assertArrayHasKey( 'payment_method_configuration', $captured_create );
			$this->assertSame( $configuration_id, $captured_create['payment_method_configuration'] );
		}
	}

	/**
	 * Data provider for {@see test_create_session_with_payment_method_configuration()}.
	 *
	 * @return array
	 */
	public function provide_create_session_payment_method_configuration_data(): array {
		return [
			'configuration available'   => [ 'pmc_test_123' ],
			'configuration unavailable' => [ null ],
		];
	}

	/**
	 * A later native cart total updates the server-owned session and increments its embedded revision.
	 */
	public function test_synchronize_updates_existing_session_after_cart_total_changes(): void {
		$product       = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$session_id       = 'cs_test_native_update';
		$requested_urls   = [];
		$captured_updates = [];
		$capture_body     = static function ( $request, $api ) use ( &$captured_updates, $session_id ) {
			if ( "checkout/sessions/$session_id" === $api ) {
				$captured_updates[] = $request;
			}
			return $request;
		};
		$mock_request     = static function ( $return_value, $parsed_args, $url ) use ( &$requested_urls, $session_id ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			$requested_urls[] = $url;
			$body             = [ 'id' => $session_id ];
			if ( 'https://api.stripe.com/v1/checkout/sessions' === $url ) {
				$body['client_secret'] = 'cs_test_native_secret';
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode( (object) $body ),
			];
		};
		add_filter( 'wc_stripe_request_body', $capture_body, 10, 2 );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();

			WC()->cart->set_quantity( $cart_item_key, 2 );
			WC()->cart->calculate_totals();
			$updated = $manager->synchronize();
		} finally {
			remove_filter( 'wc_stripe_request_body', $capture_body, 10 );
			remove_filter( 'pre_http_request', $mock_request, 10 );
		}

		$this->assertCount( 2, $requested_urls );
		$this->assertStringEndsWith( "/v1/checkout/sessions/$session_id", $requested_urls[1] );
		$this->assertNotSame( '', $updated['revision'] );
		$this->assertNotSame( $created['revision'], $updated['revision'], 'An update must issue a new revision token.' );
		$this->assertSame( $session_id, $updated['session_id'] );
		$this->assertCount( 1, $captured_updates );

		$expected_amount = WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() );
		$this->assertSame( $expected_amount, $captured_updates[0]['line_items'][0]['price_data']['unit_amount'] );
		$this->assertSame( 1, $captured_updates[0]['line_items'][0]['quantity'] );
	}

	/**
	 * Mock Stripe so creates hand out sequential session IDs and updates to the first one fail.
	 *
	 * @param string      $prefix           Session ID prefix.
	 * @param int         $http_status      HTTP status of the failed update.
	 * @param array       $error            Stripe error body for the failed update.
	 * @param array       $requested_urls   Receives every Checkout Session request, as "METHOD url", in order.
	 * @param int|null    $expires_at       expires_at for the first created session; later ones expire in a day.
	 * @param string|null $retrieved_status Status Stripe reports when the first session is retrieved; null answers resource_missing.
	 * @return callable The pre_http_request filter callback.
	 */
	private function mock_stripe_with_failing_update( string $prefix, int $http_status, array $error, array &$requested_urls, ?int $expires_at = null, ?string $retrieved_status = 'open' ): callable {
		$create_count = 0;
		$mock_request = static function ( $return_value, $parsed_args, $url ) use ( &$requested_urls, &$create_count, $prefix, $http_status, $error, $expires_at, $retrieved_status ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			$method           = strtoupper( (string) ( $parsed_args['method'] ?? 'GET' ) );
			$requested_urls[] = "$method $url";

			if ( 'GET' === $method ) {
				if ( 'empty-body' === $retrieved_status ) {
					// WC_Stripe_API::retrieve() turns an empty body into a WP_Error without recording an outage.
					return [
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'headers'  => [ 'Content-Type' => 'application/json' ],
						'body'     => '',
					];
				}

				if ( 'linked-concurrently' === $retrieved_status ) {
					// Simulate an order request that linked the session between the verdict and the replacement lock.
					WC_Stripe_Checkout_Session_Context::mark_linked_to_order( "{$prefix}_1", WC_Helper_Order::create_order() );
					$retrieved_status = 'expired';
				}

				if ( 'replaced-concurrently' === $retrieved_status ) {
					// Simulate another request that replaced the expired session while this one awaited Stripe.
					WC()->session->set(
						'wc_stripe_checkout_session',
						[
							'session_id'    => "{$prefix}_other",
							'client_secret' => "{$prefix}_secret_other",
							'revision'      => 'other-revision',
							'status'        => 'success',
							'message'       => '',
							'expires_at'    => time() + DAY_IN_SECONDS,
						]
					);
					$retrieved_status = 'expired';
				}

				if ( null === $retrieved_status ) {
					return [
						'response' => 404,
						'headers'  => [ 'Content-Type' => 'application/json' ],
						'body'     => wp_json_encode(
							[
								'error' => (object) [
									'type'    => 'invalid_request_error',
									'code'    => 'resource_missing',
									'message' => 'No such checkout session',
								],
							]
						),
					];
				}

				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode(
						(object) [
							'id'     => "{$prefix}_1",
							'status' => $retrieved_status,
						]
					),
				];
			}

			if ( 'https://api.stripe.com/v1/checkout/sessions' === $url ) {
				++$create_count;
				$body = [
					'id'            => "{$prefix}_{$create_count}",
					'client_secret' => "{$prefix}_secret_{$create_count}",
					'expires_at'    => 1 === $create_count && null !== $expires_at ? $expires_at : time() + DAY_IN_SECONDS,
				];

				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode( (object) $body ),
				];
			}

			return [
				'response' => $http_status,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode( [ 'error' => (object) $error ] ),
			];
		};

		return $mock_request;
	}

	/**
	 * @return array<string, array{0: int, 1: array, 2: string|null, 3: bool, 4: bool}>
	 */
	public function provide_update_errors(): array {
		$no_longer_active = [
			'type'    => 'invalid_request_error',
			'message' => 'This Checkout Session is no longer active and cannot be updated.',
		];

		// [ update HTTP status, update error, status Stripe reports on retrieve, expect retrieve, expect replacement ]
		return [
			'expired session'                       => [ 400, $no_longer_active, 'expired', true, true ],
			'completed session'                     => [ 400, $no_longer_active, 'complete', true, true ],
			'unknown session'                       => [
				404,
				[
					'type'    => 'invalid_request_error',
					'code'    => 'resource_missing',
					'message' => "No such checkout session: 'cs_test_replace_1'",
				],
				null,
				false,
				true,
			],
			'status lookup fails'                   => [ 400, $no_longer_active, 'empty-body', true, false ],
			'invalid parameters on an open session' => [
				400,
				[
					'type'    => 'invalid_request_error',
					'code'    => 'parameter_invalid_integer',
					'param'   => 'line_items[0][price_data][unit_amount]',
					'message' => 'Invalid integer: abc',
				],
				'open',
				true,
				false,
			],
			// A real 5xx makes WC_Stripe_API::request() throw before any body is parsed, so the
			// non-invalid_request_error branch is exercised with a 4xx type that reaches it.
			'other error type'                      => [
				400,
				[
					'type'    => 'idempotency_error',
					'message' => 'Keys for idempotent requests can only be used with the same parameters they were first used with.',
				],
				'open',
				false,
				false,
			],
		];
	}

	/**
	 * Stripe rejects every update to an expired, completed, or unknown session, so keeping that
	 * record would retry the unusable session on every cart change for the rest of the WooCommerce
	 * session. Stripe reports those with the same generic error type as a malformed request, so
	 * the session's retrieved status decides; a bad request against an open session, a lookup
	 * that fails, and any other error type must keep the record so the next sync retries it.
	 *
	 * @dataProvider provide_update_errors
	 */
	public function test_synchronize_replaces_session_only_when_stripe_can_no_longer_update_it( int $http_status, array $error, ?string $retrieved_status, bool $expect_retrieve, bool $expect_replacement ): void {
		$product       = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update( 'cs_test_replace', $http_status, $error, $requested_urls, null, $retrieved_status );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_replace_1', $created['session_id'] );
			$this->assertNotNull( WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_replace_1' ) );

			WC()->cart->set_quantity( $cart_item_key, 2 );
			WC()->cart->calculate_totals();

			$thrown = null;
			try {
				$result = $manager->synchronize();
			} catch ( WC_Stripe_Exception $e ) {
				$thrown = $e;
			}

			// Read before cleanup; the finally block deletes this context either way.
			$first_context_after_sync = WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_replace_1' );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_replace_1' );
		}

		$this->assertSame( 'POST https://api.stripe.com/v1/checkout/sessions/cs_test_replace_1', $requested_urls[1], 'The existing session must be updated first.' );

		$retrieves = array_filter(
			$requested_urls,
			static function ( string $request ): bool {
				return 'GET https://api.stripe.com/v1/checkout/sessions/cs_test_replace_1' === $request;
			}
		);
		$this->assertCount( $expect_retrieve ? 1 : 0, $retrieves, 'The session status must be retrieved exactly when the update is rejected as an invalid request.' );

		if ( ! $expect_replacement ) {
			$this->assertInstanceOf( WC_Stripe_Exception::class, $thrown );
			$this->assertSame( $error['message'], $thrown->getMessage(), 'The original Stripe error must surface when the session is kept.' );
			$this->assertNotContains( 'POST https://api.stripe.com/v1/checkout/sessions', array_slice( $requested_urls, 1 ), 'Keeping the session must not create a new one.' );
			$this->assertSame( 'cs_test_replace_1', $manager->get_current_data()['session_id'], 'The session must be kept for the next retry.' );
			$this->assertNotNull( $first_context_after_sync, 'The kept session’s context must survive.' );
			return;
		}

		$this->assertNull( $thrown, 'Replacing the session must not surface the unusable session’s error.' );
		$this->assertCount( $expect_retrieve ? 4 : 3, $requested_urls );
		$this->assertSame( 'POST https://api.stripe.com/v1/checkout/sessions', end( $requested_urls ), 'The unusable session must be replaced in the same synchronization.' );
		$this->assertSame( 'cs_test_replace_2', $result['session_id'] );
		$this->assertSame( 'cs_test_replace_secret_2', $result['client_secret'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertNotSame( $created['revision'], $result['revision'] );
		$this->assertNull( $first_context_after_sync, 'The unusable session’s context must be discarded.' );

		$expected_amount = WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() );
		$this->assertSame( $expected_amount, WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_replace_2' )['amount'], 'The replacement must carry the current cart total.' );
	}

	/**
	 * Two syncs can hit the same expired session; the one that finishes second must adopt the
	 * replacement already stored instead of discarding it and creating a third session.
	 */
	public function test_update_session_adopts_a_replacement_stored_by_a_concurrent_request(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update(
			'cs_test_concurrent',
			400,
			[
				'type'    => 'invalid_request_error',
				'message' => 'This Checkout Session is no longer active and cannot be updated.',
			],
			$requested_urls,
			null,
			'replaced-concurrently'
		);
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_concurrent_1', $created['session_id'] );

			$result = $manager->update_session( 'cs_test_concurrent_1' );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_concurrent_1' );
		}

		$this->assertSame(
			[
				'POST https://api.stripe.com/v1/checkout/sessions',
				'POST https://api.stripe.com/v1/checkout/sessions/cs_test_concurrent_1',
				'GET https://api.stripe.com/v1/checkout/sessions/cs_test_concurrent_1',
			],
			$requested_urls,
			'No third session may be created once another request has stored a replacement.'
		);
		$this->assertSame( 'cs_test_concurrent_other', $result['session_id'], 'The replacement stored by the other request must be adopted.' );
		$this->assertSame( 'cs_test_concurrent_other', $manager->get_current_data()['session_id'], 'The replacement record must not be cleared.' );
	}

	/**
	 * Option name the context class derives for a session's mutation lock.
	 *
	 * @param string $session_id Stripe Checkout Session ID.
	 * @return string
	 */
	private function get_mutation_lock_option_name( string $session_id ): string {
		return 'wc_stripe_checkout_session_lock_' . md5( $session_id );
	}

	/**
	 * Two requests can discover the same expired session at once. Replacement is serialized on
	 * the session's mutation lock, so the one that loses the lock must fail fast without creating
	 * a competing session, leaving its record and context for the next sync to adopt the winner's.
	 */
	public function test_update_session_does_not_replace_while_another_request_holds_the_lock(): void {
		$product       = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update(
			'cs_test_locked',
			400,
			[
				'type'    => 'invalid_request_error',
				'message' => 'This Checkout Session is no longer active and cannot be updated.',
			],
			$requested_urls,
			null,
			'expired'
		);
		$lock_name      = $this->get_mutation_lock_option_name( 'cs_test_locked_1' );
		$lock_owner     = null;
		// The status lookup runs outside the lock; a competing request grabs the lock right then.
		$grab_lock = static function ( $preempt, $parsed_args, $url ) use ( &$lock_owner, $lock_name ) {
			if ( null === $lock_owner && 'GET' === strtoupper( (string) ( $parsed_args['method'] ?? 'GET' ) ) && str_ends_with( $url, '/checkout/sessions/cs_test_locked_1' ) ) {
				$lock_owner = WC_Stripe_Option_Lock::acquire( $lock_name, 70 );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $grab_lock, 9, 3 );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_locked_1', $created['session_id'] );

			WC()->cart->set_quantity( $cart_item_key, 2 );
			WC()->cart->calculate_totals();

			$thrown = null;
			try {
				$manager->synchronize();
			} catch ( Exception $e ) {
				$thrown = $e;
			}

			$context_after = WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_locked_1' );
		} finally {
			remove_filter( 'pre_http_request', $grab_lock, 9 );
			remove_filter( 'pre_http_request', $mock_request, 10 );
			if ( null !== $lock_owner ) {
				WC_Stripe_Option_Lock::release( $lock_name, $lock_owner );
			}
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_locked_1' );
		}

		$this->assertNotNull( $lock_owner, 'The competing request must have taken the lock during the status lookup.' );
		$this->assertInstanceOf( Exception::class, $thrown );
		$this->assertSame( WC_Stripe_Checkout_Session_Context::get_unavailable_message(), $thrown->getMessage() );
		$this->assertSame(
			[
				'POST https://api.stripe.com/v1/checkout/sessions',
				'POST https://api.stripe.com/v1/checkout/sessions/cs_test_locked_1',
				'GET https://api.stripe.com/v1/checkout/sessions/cs_test_locked_1',
			],
			$requested_urls,
			'Losing the lock must not create a competing session.'
		);
		$this->assertSame( 'cs_test_locked_1', $manager->get_current_data()['session_id'], 'The record must be left for the next sync to adopt the winner’s replacement.' );
		$this->assertNotNull( $context_after, 'The context must survive so the winner can discard it.' );
	}

	/**
	 * An order can link the session between the status verdict and the replacement lock. The order
	 * flow owns that context from then on, so replacement must create the new session without
	 * deleting it, and must leave the lock in a releasable state afterwards.
	 */
	public function test_replacement_keeps_the_context_of_a_session_an_order_linked_meanwhile(): void {
		$product       = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update(
			'cs_test_linked',
			400,
			[
				'type'    => 'invalid_request_error',
				'message' => 'This Checkout Session is no longer active and cannot be updated.',
			],
			$requested_urls,
			null,
			'linked-concurrently'
		);
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_linked_1', $created['session_id'] );

			WC()->cart->set_quantity( $cart_item_key, 2 );
			WC()->cart->calculate_totals();

			$result      = $manager->synchronize();
			$old_context = WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_linked_1' );
			$lock_name   = $this->get_mutation_lock_option_name( 'cs_test_linked_1' );
			$lock_owner  = WC_Stripe_Option_Lock::acquire( $lock_name, 70 );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			if ( ! empty( $lock_owner ) ) {
				WC_Stripe_Option_Lock::release( $lock_name, $lock_owner );
			}
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_linked_1' );
		}

		$this->assertSame( 'cs_test_linked_2', $result['session_id'], 'The client must still receive a live replacement.' );
		$this->assertNotNull( $old_context, 'A context an order linked meanwhile must survive the replacement.' );
		$this->assertGreaterThan( 0, (int) $old_context['order_id'] );
		$this->assertNotNull( $lock_owner, 'The mutation lock must be free again once the replacement is done.' );
	}

	/**
	 * The expiry branch is serialized the same way.
	 */
	public function test_synchronize_does_not_recreate_an_expired_session_while_another_request_holds_the_lock(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update( 'cs_test_locked_expiry', 400, [ 'type' => 'invalid_request_error' ], $requested_urls, time() - 1 );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		$lock_name  = $this->get_mutation_lock_option_name( 'cs_test_locked_expiry_1' );
		$lock_owner = null;

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_locked_expiry_1', $created['session_id'] );

			$lock_owner = WC_Stripe_Option_Lock::acquire( $lock_name, 70 );
			$this->assertNotNull( $lock_owner );

			$thrown = null;
			try {
				$manager->synchronize();
			} catch ( Exception $e ) {
				$thrown = $e;
			}
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			if ( null !== $lock_owner ) {
				WC_Stripe_Option_Lock::release( $lock_name, $lock_owner );
			}
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_locked_expiry_1' );
		}

		$this->assertInstanceOf( Exception::class, $thrown );
		$this->assertSame( [ 'POST https://api.stripe.com/v1/checkout/sessions' ], $requested_urls, 'Losing the lock must not create a competing session.' );
		$this->assertSame( 'cs_test_locked_expiry_1', $manager->get_current_data()['session_id'] );
	}

	/**
	 * When the stored expiry already says the session is gone, a rejected update must not spend a
	 * retrieve confirming it. The mock answers "open" to any retrieve, so a lookup would wrongly
	 * keep the session and the assertions below would fail.
	 */
	public function test_update_session_skips_the_status_lookup_for_a_session_known_to_have_expired(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update(
			'cs_test_known_expired',
			400,
			[
				'type'    => 'invalid_request_error',
				'message' => 'This Checkout Session is no longer active and cannot be updated.',
			],
			$requested_urls,
			time() - 1,
			'open'
		);
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_known_expired_1', $created['session_id'] );

			// Call the update directly: synchronize() would already recreate from the stored expiry.
			$result = $manager->update_session( 'cs_test_known_expired_1' );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_known_expired_1' );
		}

		$this->assertSame(
			[
				'POST https://api.stripe.com/v1/checkout/sessions',
				'POST https://api.stripe.com/v1/checkout/sessions/cs_test_known_expired_1',
				'POST https://api.stripe.com/v1/checkout/sessions',
			],
			$requested_urls,
			'A session the record already knows has expired must be replaced without retrieving it.'
		);
		$this->assertSame( 'cs_test_known_expired_2', $result['session_id'] );
		$this->assertSame( 'success', $result['status'] );
	}

	/**
	 * Stripe expires sessions 24 hours after creation while the record and context can outlive
	 * that, so an unchanged cart must not keep handing the client a session Stripe will refuse.
	 */
	public function test_synchronize_recreates_session_once_it_has_expired(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update( 'cs_test_expired', 400, [ 'type' => 'invalid_request_error' ], $requested_urls, time() - 1 );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_expired_1', $created['session_id'] );

			$recreated = $manager->synchronize();
			$reused    = $manager->synchronize();

			// Read before cleanup; the finally block deletes this context either way.
			$expired_context_after_sync = WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_expired_1' );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_expired_1' );
		}

		$this->assertSame( [ 'POST https://api.stripe.com/v1/checkout/sessions', 'POST https://api.stripe.com/v1/checkout/sessions' ], $requested_urls, 'An expired session must be recreated without attempting an update.' );
		$this->assertSame( 'cs_test_expired_2', $recreated['session_id'] );
		$this->assertSame( 'success', $recreated['status'] );
		$this->assertNull( $expired_context_after_sync, 'The expired session’s context must be discarded.' );
		$this->assertSame( $recreated, $reused, 'A live replacement must be reused for an unchanged cart.' );
	}

	/**
	 * @return array<string, array{0: bool, 1: string|null, 2: bool, 3: bool}>
	 */
	public function provide_verification_outcomes(): array {
		// [ verify with Stripe, status Stripe reports, expect lookup, expect replacement ]
		return [
			'not asked to verify'       => [ false, 'open', false, false ],
			'verified and still open'   => [ true, 'open', true, false ],
			'verified and expired'      => [ true, 'expired', true, true ],
			'verified and completed'    => [ true, 'complete', true, true ],
			'verification inconclusive' => [ true, 'empty-body', true, false ],
		];
	}

	/**
	 * A session expired from the Dashboard or API leaves no local trace, so a reused session can be
	 * confirmed with Stripe on request. The lookup costs an API call, so it only happens when asked,
	 * and only an explicit "finished" answer replaces the session.
	 *
	 * @dataProvider provide_verification_outcomes
	 */
	public function test_synchronize_verifies_a_reused_session_with_stripe_only_when_asked( bool $verify, ?string $retrieved_status, bool $expect_lookup, bool $expect_replacement ): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 10 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update( 'cs_test_verify', 400, [ 'type' => 'invalid_request_error' ], $requested_urls, null, $retrieved_status );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_verify_1', $created['session_id'] );

			$result = $manager->synchronize( $verify );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_verify_1' );
		}

		$expected_urls = [ 'POST https://api.stripe.com/v1/checkout/sessions' ];
		if ( $expect_lookup ) {
			$expected_urls[] = 'GET https://api.stripe.com/v1/checkout/sessions/cs_test_verify_1';
		}
		if ( $expect_replacement ) {
			$expected_urls[] = 'POST https://api.stripe.com/v1/checkout/sessions';
		}
		$this->assertSame( $expected_urls, $requested_urls );
		$this->assertSame( $expect_replacement ? 'cs_test_verify_2' : 'cs_test_verify_1', $result['session_id'] );
		$this->assertSame( 'success', $result['status'] );
	}

	/**
	 * The classic placeholder is rendered once per checkout page load, so a reused session is
	 * confirmed with Stripe there and replaced before the client can mount it.
	 */
	public function test_classic_placeholder_replaces_a_session_stripe_reports_finished(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore        = $this->enable_adaptive_pricing();
		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update( 'cs_test_placeholder', 400, [ 'type' => 'invalid_request_error' ], $requested_urls, null, 'expired' );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$lifecycle = new WC_Stripe_Checkout_Session_Lifecycle();

			ob_start();
			$lifecycle->render_classic_placeholder();
			$first_render = ob_get_clean();
			$this->assertStringContainsString( 'uninitialized', $first_render, 'Without a stored session the placeholder must not create one.' );

			$lifecycle->add_classic_fragment( [] );

			ob_start();
			$lifecycle->render_classic_placeholder();
			$second_render = ob_get_clean();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_placeholder_1' );
			$restore();
		}

		$this->assertSame(
			[
				'POST https://api.stripe.com/v1/checkout/sessions',
				'GET https://api.stripe.com/v1/checkout/sessions/cs_test_placeholder_1',
				'POST https://api.stripe.com/v1/checkout/sessions',
			],
			$requested_urls,
			'The page load must look the stored session up once and replace it.'
		);
		$data = json_decode( $this->get_fragment_session_data( $second_render ), true );
		$this->assertSame( 'cs_test_placeholder_2', $data['session_id'] );
		$this->assertSame( 'success', $data['status'] );
	}

	/**
	 * The Blocks checkout asks for an explicit sync when it mounts; that request confirms a reused
	 * session with Stripe, while ordinary cart responses never pay for the lookup.
	 */
	public function test_store_api_sync_request_replaces_a_session_stripe_reports_finished(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore        = $this->enable_adaptive_pricing();
		$requested_urls = [];
		$mock_request   = $this->mock_stripe_with_failing_update( 'cs_test_store_verify', 400, [ 'type' => 'invalid_request_error' ], $requested_urls, null, 'expired' );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$created = $manager->synchronize();
			$this->assertSame( 'cs_test_store_verify_1', $created['session_id'] );

			$cart_response = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
			$this->assertSame( 'cs_test_store_verify_1', $cart_response['session_id'], 'An ordinary cart response must reuse the session without a lookup.' );

			WC_Stripe_Checkout_Session_Lifecycle::request_store_api_sync( [ 'action' => 'sync' ] );
			$mount_response = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_store_verify_1' );
			$restore();
		}

		$this->assertSame(
			[
				'POST https://api.stripe.com/v1/checkout/sessions',
				'GET https://api.stripe.com/v1/checkout/sessions/cs_test_store_verify_1',
				'POST https://api.stripe.com/v1/checkout/sessions',
			],
			$requested_urls
		);
		$this->assertSame( 'cs_test_store_verify_2', $mount_response['session_id'] );
		$this->assertSame( 'success', $mount_response['status'] );
	}

	/**
	 * A context that no longer matches the cart must be repaired, whatever left it behind. It is the
	 * value validate_for_order() enforces, so a stale one fails the order with an amount mismatch.
	 */
	public function test_synchronize_repairs_a_context_that_drifted_from_the_cart(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 23.04 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$session_id     = 'cs_test_drifted_context';
		$requested_urls = [];
		$mock_request   = static function ( $return_value, $parsed_args, $url ) use ( &$requested_urls, $session_id ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			$requested_urls[] = $url;
			$body             = [ 'id' => $session_id ];
			if ( 'https://api.stripe.com/v1/checkout/sessions' === $url ) {
				$body['client_secret'] = 'cs_test_drifted_secret';
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode( (object) $body ),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$manager = new WC_Stripe_Checkout_Session_Manager();
			$manager->synchronize();

			// Reproduce the drift seen in production: the context holds an amount the cart no
			// longer has, while the cart itself is unchanged and the session looks healthy.
			$context           = WC_Stripe_Checkout_Session_Context::get_context( $session_id );
			$context['amount'] = 5020;
			WC_Stripe_Checkout_Session_Context::set_context( $session_id, $context );

			$manager->synchronize();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
		}

		$this->assertCount( 2, $requested_urls, 'A drifted context must trigger a Stripe update.' );
		$this->assertStringEndsWith( "/v1/checkout/sessions/$session_id", $requested_urls[1] );

		$expected_amount = WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() );
		$repaired        = WC_Stripe_Checkout_Session_Context::get_context( $session_id );
		$this->assertSame( $expected_amount, $repaired['amount'] );
	}

	/**
	 * Run synchronize() across a login-state transition with the Stripe API mocked.
	 *
	 * Synchronizes once in the starting state, flips the login state (using a
	 * freshly created user), then synchronizes twice more to cover both the
	 * recreation and the subsequent reuse of the new session.
	 *
	 * @param bool          $start_logged_in Whether the first synchronization happens while logged in.
	 * @param string        $prefix          Fixture prefix for the mocked Stripe identifiers.
	 * @param callable|null $seed            Optional callback to seed a pre-existing record once the cart totals are known.
	 * @return array The three synchronize() results, the captured create requests, and the create count.
	 */
	private function run_synchronize_login_transition( bool $start_logged_in, string $prefix, ?callable $seed = null ): array {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 42 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		if ( null !== $seed ) {
			$seed();
		}

		$user_id           = self::factory()->user->create();
		$create_count      = 0;
		$captured_creates  = [];
		$original_customer = WC()->customer;
		$capture_body      = static function ( $request, $api ) use ( &$captured_creates ) {
			if ( 'checkout/sessions' === $api ) {
				$captured_creates[] = $request;
			}
			return $request;
		};
		$mock_request      = static function ( $return_value, $parsed_args, $url ) use ( &$create_count, $prefix ) {
			if ( false !== strpos( $url, '/v1/customers' ) ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode( (object) [ 'id' => "cus_{$prefix}" ] ),
				];
			}

			if ( 'https://api.stripe.com/v1/checkout/sessions' !== $url ) {
				return $return_value;
			}

			++$create_count;
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'id'            => "cs_test_{$prefix}_{$create_count}",
						'client_secret' => "cs_test_{$prefix}_secret_{$create_count}",
					]
				),
			];
		};

		$log_in  = static function () use ( $user_id ) {
			wp_set_current_user( $user_id );
			WC()->customer = new WC_Customer( $user_id );
		};
		$log_out = static function () use ( $original_customer ) {
			wp_set_current_user( 0 );
			WC()->customer = $original_customer;
		};

		add_filter( 'wc_stripe_request_body', $capture_body, 10, 2 );
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			if ( $start_logged_in ) {
				$log_in();
			} else {
				$log_out();
			}

			$manager = new WC_Stripe_Checkout_Session_Manager();
			$before  = $manager->synchronize();

			if ( $start_logged_in ) {
				$log_out();
			} else {
				$log_in();
			}

			$after        = $manager->synchronize();
			$still_reused = $manager->synchronize();
		} finally {
			remove_filter( 'wc_stripe_request_body', $capture_body, 10 );
			remove_filter( 'pre_http_request', $mock_request, 10 );
			wp_set_current_user( 0 );
			WC()->customer = $original_customer;
			WC_Stripe_Checkout_Session_Context::delete_context( "cs_test_{$prefix}_1" );
			WC_Stripe_Checkout_Session_Context::delete_context( "cs_test_{$prefix}_2" );
		}

		return [ $before, $after, $still_reused, $captured_creates, $create_count ];
	}

	/**
	 * A guest-created session cannot accept savePaymentMethod, so a login must recreate it
	 * rather than reuse the migrated guest one.
	 */
	public function test_synchronize_recreates_session_when_guest_logs_in(): void {
		[ $as_guest, $after_login, $still_reused, $captured_creates, $create_count ] =
			$this->run_synchronize_login_transition( false, 'login_transition' );

		$this->assertSame( 2, $create_count, 'The login must trigger exactly one session recreation.' );
		$this->assertCount( 2, $captured_creates );

		$this->assertArrayNotHasKey( 'saved_payment_method_options', $captured_creates[0] );
		$this->assertArrayNotHasKey( 'customer', $captured_creates[0] );
		$this->assertSame( 'always', $captured_creates[0]['customer_creation'], 'A guest session must ask Stripe to create a Customer at confirmation.' );
		$this->assertFalse( $as_guest['save_payment_method_enabled'] );

		$this->assertSame( [ 'payment_method_save' => 'enabled' ], $captured_creates[1]['saved_payment_method_options'] );
		$this->assertSame( 'cus_login_transition', $captured_creates[1]['customer'] );
		$this->assertArrayNotHasKey( 'customer_creation', $captured_creates[1], 'An attached customer must not also request customer creation.' );
		$this->assertSame( 'cs_test_login_transition_2', $after_login['session_id'] );
		$this->assertTrue( $after_login['save_payment_method_enabled'] );

		$this->assertSame( $after_login, $still_reused, 'An unchanged login state must keep reusing the recreated session.' );
	}

	/**
	 * The inverse transition: logging out must also recreate the session.
	 */
	public function test_synchronize_recreates_session_when_user_logs_out(): void {
		[ $while_logged_in, $after_logout, $still_reused, $captured_creates, $create_count ] =
			$this->run_synchronize_login_transition( true, 'logout_transition' );

		$this->assertSame( 2, $create_count, 'The logout must trigger exactly one session recreation.' );
		$this->assertCount( 2, $captured_creates );
		$this->assertTrue( $while_logged_in['save_payment_method_enabled'] );
		$this->assertArrayNotHasKey( 'customer_creation', $captured_creates[0] );
		$this->assertArrayNotHasKey( 'saved_payment_method_options', $captured_creates[1] );
		$this->assertArrayNotHasKey( 'customer', $captured_creates[1] );
		$this->assertSame( 'always', $captured_creates[1]['customer_creation'] );
		$this->assertSame( 'cs_test_logout_transition_2', $after_logout['session_id'] );
		$this->assertFalse( $after_logout['save_payment_method_enabled'] );

		$this->assertSame( $after_logout, $still_reused, 'An unchanged logged-out state must keep reusing the recreated session.' );
	}

	/**
	 * A record written before the save flag existed omits the key entirely. A guest must
	 * keep reusing it, and a login must recreate it rather than reuse it in a state that
	 * cannot save a payment method.
	 */
	public function test_synchronize_self_heals_legacy_record_without_save_flag(): void {
		$seed = static function (): void {
			WC()->session->set(
				'wc_stripe_checkout_session',
				[
					'session_id'    => 'cs_test_legacy_record',
					'client_secret' => 'cs_test_legacy_secret',
					'revision'      => 'legacy-revision',
					'status'        => 'success',
					'message'       => '',
				]
			);

			$currency = get_woocommerce_currency();
			WC_Stripe_Checkout_Session_Context::set_context(
				'cs_test_legacy_record',
				[
					'amount'   => WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), $currency ),
					'currency' => strtolower( $currency ),
				]
			);
		};

		try {
			[ $as_guest, $after_login, $still_reused, $captured_creates, $create_count ] =
				$this->run_synchronize_login_transition( false, 'legacy_heal', $seed );
		} finally {
			WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_legacy_record' );
		}

		$this->assertSame( 'cs_test_legacy_record', $as_guest['session_id'], 'A guest must keep reusing the legacy record.' );
		$this->assertFalse( $as_guest['save_payment_method_enabled'] );

		$this->assertSame( 1, $create_count, 'The login must recreate the legacy session exactly once.' );
		$this->assertSame( [ 'payment_method_save' => 'enabled' ], $captured_creates[0]['saved_payment_method_options'] );
		$this->assertSame( 'cs_test_legacy_heal_1', $after_login['session_id'] );
		$this->assertTrue( $after_login['save_payment_method_enabled'] );

		$this->assertSame( $after_login, $still_reused, 'An unchanged login state must keep reusing the recreated session.' );
	}

	/**
	 * Classic checkout refreshes arrive as wc-ajax requests against the home URL, so is_checkout()
	 * is false. Eligibility must still resolve or the fragment never carries a usable session.
	 */
	public function test_classic_fragment_synchronizes_without_page_context(): void {
		$this->assertFalse( is_checkout(), 'This test is only meaningful without checkout page context.' );

		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$session_id = 'cs_test_classic_fragment';
		$restore    = $this->enable_adaptive_pricing();

		$mock_request = static function ( $return_value, $parsed_args, $url ) use ( $session_id ) {
			if ( 'https://api.stripe.com/v1/checkout/sessions' !== $url ) {
				return $return_value;
			}

			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'id'            => $session_id,
						'client_secret' => 'cs_test_classic_secret',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$lifecycle = new WC_Stripe_Checkout_Session_Lifecycle();
			$fragments = $lifecycle->add_classic_fragment( [] );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			$restore();
		}

		$this->assertArrayHasKey( '#wc-stripe-checkout-session-data', $fragments );

		$data = json_decode( $this->get_fragment_session_data( $fragments['#wc-stripe-checkout-session-data'] ), true );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'session_id', $data );
		$this->assertSame( $session_id, $data['session_id'] );
		$this->assertArrayHasKey( 'client_secret', $data );
		$this->assertSame( 'cs_test_classic_secret', $data['client_secret'] );
		$this->assertSame( 'success', $data['status'] );
	}

	/**
	 * Lifecycle hooks expose a stable classic fragment and Store API contract.
	 */
	public function test_lifecycle_registers_native_checkout_contracts(): void {
		$manager = $this->getMockBuilder( WC_Stripe_Checkout_Session_Manager::class )
			->onlyMethods( [ 'get_current_data' ] )
			->getMock();
		$manager->method( 'get_current_data' )->willReturn(
			[
				'session_id'    => 'cs_test_embedded',
				'client_secret' => 'cs_test_embedded_secret',
				'revision'      => 'rev_embedded',
				'status'        => 'success',
				'message'       => '',
			]
		);

		$lifecycle = new WC_Stripe_Checkout_Session_Lifecycle( $manager );
		$lifecycle->init_classic_hooks();

		ob_start();
		$lifecycle->render_classic_placeholder();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="wc-stripe-checkout-session-data"', $html );
		$this->assertStringContainsString( 'cs_test_embedded_secret', html_entity_decode( $html ) );
		$this->assertNotFalse( has_filter( 'woocommerce_update_order_review_fragments', [ $lifecycle, 'add_classic_fragment' ] ) );

		$schema = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_schema();
		$this->assertSame( [ 'session_id', 'client_secret', 'revision', 'status', 'message', 'save_payment_method_enabled' ], array_keys( $schema ) );

		remove_action( 'woocommerce_review_order_after_payment', [ $lifecycle, 'render_classic_placeholder' ] );
		remove_filter( 'woocommerce_update_order_review_fragments', [ $lifecycle, 'add_classic_fragment' ], 20 );
	}

	public function test_classic_fragment_reports_stripe_api_error(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore = $this->enable_adaptive_pricing();

		$mock_request = static function ( $return_value, $parsed_args, $url ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			return [
				'response' => [
					'code'    => 402,
					'message' => 'Payment Required',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'error' => (object) [
							'type'    => 'card_error',
							'message' => 'Checkout Session creation was declined.',
						],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		$original_logger = WC_Stripe_Logger::$logger;
		$mock_logger     = $this->createMock( WC_Logger::class );
		$mock_logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'Native classic checkout session synchronization failed.',
				$this->callback(
					static function ( $context ): bool {
						return 'Checkout Session creation was declined.' === ( $context['error_message'] ?? '' );
					}
				)
			);
		WC_Stripe_Logger::$logger = $mock_logger;

		try {
			$lifecycle = new WC_Stripe_Checkout_Session_Lifecycle();
			$fragments = $lifecycle->add_classic_fragment( [] );
		} finally {
			WC_Stripe_Logger::$logger = $original_logger;
			remove_filter( 'pre_http_request', $mock_request, 10 );
			$restore();
		}

		$this->assertArrayHasKey( '#wc-stripe-checkout-session-data', $fragments );
		$data = json_decode( $this->get_fragment_session_data( $fragments['#wc-stripe-checkout-session-data'] ), true );
		$this->assertSame( 'error', $data['status'] );
		// The raw Stripe message is log-only; shoppers get the curated message.
		$this->assertSame( WC_Stripe_Checkout_Session_Manager::get_runtime_error_message(), $data['message'] );
		$this->assertSame( '', $data['session_id'] );
		$this->assertSame( '', $data['client_secret'] );

		// Confirm that the error was written to the session data.
		$record = WC()->session->get( 'wc_stripe_checkout_session' );
		$this->assertSame( 'error', $record['status'] );
	}

	public function test_classic_fragment_reports_an_empty_cart_before_calling_stripe(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore       = $this->enable_adaptive_pricing();
		$session_id    = 'cs_test_emptied_cart';
		$request_count = 0;
		$mock_request  = static function ( $return_value, $parsed_args, $url ) use ( &$request_count, $session_id ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			++$request_count;
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'id'            => $session_id,
						'client_secret' => 'cs_test_emptied_cart_secret',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$lifecycle = new WC_Stripe_Checkout_Session_Lifecycle();
			$lifecycle->add_classic_fragment( [] );

			WC()->cart->empty_cart();
			$fragments = $lifecycle->add_classic_fragment( [] );
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			$restore();
		}

		$this->assertSame( 1, $request_count, 'Only initial call should call Stripe; an empty cart should not call Stripe.' );

		$data = json_decode( $this->get_fragment_session_data( $fragments['#wc-stripe-checkout-session-data'] ), true );
		$this->assertSame( 'error', $data['status'] );
		$this->assertSame( 'Your cart is currently empty.', $data['message'] );

		$this->assertSame( $session_id, $data['session_id'] );
	}

	public function test_store_api_data_stays_uninitialized_until_sync_is_requested(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore       = $this->enable_adaptive_pricing();
		$request_count = 0;
		$mock_request  = static function ( $return_value, $parsed_args, $url ) use ( &$request_count ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			++$request_count;
			return $return_value;
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$data = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			$restore();
		}

		$this->assertSame( 0, $request_count );
		$this->assertSame( 'uninitialized', $data['status'] );
		$this->assertSame( '', $data['session_id'] );
		$this->assertSame( '', $data['client_secret'] );
		$this->assertSame( '', $data['revision'] );
	}

	public function test_store_api_data_creates_the_session_when_sync_is_requested(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore       = $this->enable_adaptive_pricing();
		$session_id    = 'cs_test_store_api_sync';
		$request_count = 0;
		$mock_request  = static function ( $return_value, $parsed_args, $url ) use ( &$request_count, $session_id ) {
			if ( 'https://api.stripe.com/v1/checkout/sessions' !== $url ) {
				return $return_value;
			}

			++$request_count;
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'id'            => $session_id,
						'client_secret' => 'cs_test_store_api_secret',
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			$initial_data = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();

			WC_Stripe_Checkout_Session_Lifecycle::request_store_api_sync( [ 'action' => 'sync' ] );
			$this->assertTrue( $this->is_store_api_sync_requested() );

			$data = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
			$restore();
		}

		$this->assertSame( 1, $request_count );
		$this->assertSame( 'uninitialized', $initial_data['status'] );
		$this->assertSame( '', $initial_data['session_id'] );
		$this->assertSame( '', $initial_data['client_secret'] );
		$this->assertSame( '', $initial_data['revision'] );

		$this->assertSame( 'success', $data['status'] );
		$this->assertSame( $session_id, $data['session_id'] );
		$this->assertSame( 'cs_test_store_api_secret', $data['client_secret'] );
		$this->assertIsString( $data['revision'] );
		$this->assertNotSame( '', $data['revision'] );
	}

	/**
	 * Only valid sync requests should trigger synchronization.
	 *
	 * @dataProvider provide_store_api_sync_requests
	 *
	 * @param array $request_data             Extension request data.
	 * @param bool  $adaptive_pricing_enabled Whether Adaptive Pricing is enabled.
	 * @param bool  $expected_flag_value      The expected value of the store_api_sync_requested property.
	 */
	public function test_request_store_api_sync_only_handles_valid_sync_requests( array $request_data, bool $adaptive_pricing_enabled, bool $expected_flag_value ): void {
		$restore = null;
		if ( $adaptive_pricing_enabled ) {
			$restore = $this->enable_adaptive_pricing();
		}

		try {
			WC_Stripe_Checkout_Session_Lifecycle::request_store_api_sync( $request_data );
		} finally {
			if ( null !== $restore ) {
				$restore();
			}
		}

		$this->assertSame( $expected_flag_value, $this->is_store_api_sync_requested() );
	}

	/**
	 * Data provider for {@see test_request_store_api_sync_only_handles_valid_sync_requests()}.
	 *
	 * @return array[]
	 */
	public function provide_store_api_sync_requests(): array {
		return [
			'sync request on an eligible store'   => [ [ 'action' => 'sync' ], true, true ],
			'sync request on an ineligible store' => [ [ 'action' => 'sync' ], false, false ],
			'unrelated action'                    => [ [ 'action' => 'refresh' ], true, false ],
			'no action'                           => [ [], true, false ],
		];
	}

	public function test_store_api_data_reports_failure_when_adaptive_pricing_gets_disabled(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$request_count = 0;
		$mock_request  = static function ( $return_value, $parsed_args, $url ) use ( &$request_count ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			++$request_count;
			return $return_value;
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		try {
			// Set flag to true to mock the session having been set up previously.
			$this->set_store_api_sync_requested( true );
			$data = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
		} finally {
			remove_filter( 'pre_http_request', $mock_request, 10 );
		}

		$this->assertSame( 0, $request_count );
		$this->assertSame( 'error', $data['status'] );
		$this->assertSame( WC_Stripe_Checkout_Session_Manager::get_runtime_error_message(), $data['message'] );
	}

	public function test_store_api_data_reports_stripe_api_error(): void {
		$product = WC_Helper_Product::create_simple_product( true, [ 'regular_price' => 25 ] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$restore = $this->enable_adaptive_pricing();

		$mock_request = static function ( $return_value, $parsed_args, $url ) {
			if ( ! str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions' ) ) {
				return $return_value;
			}

			return [
				'response' => [
					'code'    => 402,
					'message' => 'Payment Required',
				],
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					(object) [
						'error' => (object) [
							'type'    => 'invalid_request_error',
							'message' => 'Checkout Session creation was rejected.',
						],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock_request, 10, 3 );

		$original_logger = WC_Stripe_Logger::$logger;
		$mock_logger     = $this->createMock( WC_Logger::class );
		$mock_logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'Native Store API checkout session synchronization failed.',
				$this->callback(
					static function ( $context ): bool {
						return 'Checkout Session creation was rejected.' === ( $context['error_message'] ?? '' );
					}
				)
			);
		WC_Stripe_Logger::$logger = $mock_logger;

		try {
			WC_Stripe_Checkout_Session_Lifecycle::request_store_api_sync( [ 'action' => 'sync' ] );
			$data = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
		} finally {
			WC_Stripe_Logger::$logger = $original_logger;
			remove_filter( 'pre_http_request', $mock_request, 10 );
			$restore();
		}

		$this->assertSame( 'error', $data['status'] );
		$this->assertSame( WC_Stripe_Checkout_Session_Manager::get_runtime_error_message(), $data['message'] );
		$this->assertSame( '', $data['client_secret'] );
	}

	/**
	 * Replace the manager singleton instance for testing purposes.
	 *
	 * @param WC_Stripe_Checkout_Session_Manager|null $manager Manager to install, or null so get_instance() rebuilds a real one.
	 * @return void
	 */
	private function set_manager_instance( ?WC_Stripe_Checkout_Session_Manager $manager ): void {
		$property = new ReflectionProperty( WC_Stripe_Checkout_Session_Manager::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $manager );
	}

	/**
	 * Build a manager instance where synchronize() will fail with the given throwable,
	 * while mark_failed() should still be run.
	 *
	 * @param Throwable $error Error synchronize() should throw.
	 * @return WC_Stripe_Checkout_Session_Manager
	 */
	private function get_manager_with_failing_synchronize( Throwable $error ): WC_Stripe_Checkout_Session_Manager {
		$manager = $this->getMockBuilder( WC_Stripe_Checkout_Session_Manager::class )
			->onlyMethods( [ 'synchronize' ] )
			->getMock();
		$manager->method( 'synchronize' )->willReturnCallback(
			static function () use ( $error ): void {
				throw $error;
			}
		);

		return $manager;
	}

	/**
	 * Build a logger double that requires the expected log message to be logged.
	 *
	 * @param string    $expected_log_message Log entry that should be logged.
	 * @param Throwable $error                Error whose raw message content must appear in the log context.
	 * @return WC_Logger
	 */
	private function get_logger_expecting_error( string $expected_log_message, Throwable $error ): WC_Logger {
		$mock_logger = $this->createMock( WC_Logger::class );
		$mock_logger->expects( $this->once() )
			->method( 'error' )
			->with(
				$expected_log_message,
				$this->callback(
					static function ( $context ) use ( $error ): bool {
						return $error->getMessage() === ( $context['error_message'] ?? '' );
					}
				)
			);

		return $mock_logger;
	}

	/**
	 * The classic fragment error handling must limit messages returned to callers while logging details.
	 *
	 * @dataProvider provide_synchronization_failures
	 *
	 * @param Throwable   $error                   Error that should be thrown and handled.
	 * @param string|null $expected_public_message Expected shopper-facing message, or null for the generic runtime error message.
	 */
	public function test_classic_fragment_handles_errors( Throwable $error, ?string $expected_public_message ): void {
		$expected_message = $expected_public_message ?? WC_Stripe_Checkout_Session_Manager::get_runtime_error_message();
		$restore          = $this->enable_adaptive_pricing();

		$original_logger          = WC_Stripe_Logger::$logger;
		WC_Stripe_Logger::$logger = $this->get_logger_expecting_error( 'Native classic checkout session synchronization failed.', $error );

		try {
			$lifecycle = new WC_Stripe_Checkout_Session_Lifecycle( $this->get_manager_with_failing_synchronize( $error ) );
			$fragments = $lifecycle->add_classic_fragment( [] );
		} finally {
			WC_Stripe_Logger::$logger = $original_logger;
			$restore();
		}

		$this->assertArrayHasKey( '#wc-stripe-checkout-session-data', $fragments );
		$data = json_decode( $this->get_fragment_session_data( $fragments['#wc-stripe-checkout-session-data'] ), true );
		$this->assertSame( 'error', $data['status'] );
		$this->assertSame( $expected_message, $data['message'] );
		foreach ( $data as $value ) {
			if ( is_scalar( $value ) ) {
				$this->assertStringNotContainsString( $error->getMessage(), (string) $value );
			}
		}

		// The persisted record feeds later renders, so the masking must hold there too.
		$record = WC()->session->get( 'wc_stripe_checkout_session' );
		$this->assertSame( $expected_message, $record['message'] );
	}

	/**
	 * The Store API error handling must limit messages returned to callers while logging details.
	 *
	 * @dataProvider provide_synchronization_failures
	 *
	 * @param Throwable   $error                   Error that should be thrown and handled.
	 * @param string|null $expected_public_message Expected shopper-facing message, or null for the generic runtime error message.
	 */
	public function test_store_api_data_handles_errors( Throwable $error, ?string $expected_public_message ): void {
		$expected_message = $expected_public_message ?? WC_Stripe_Checkout_Session_Manager::get_runtime_error_message();
		$restore          = $this->enable_adaptive_pricing();
		$this->set_store_api_sync_requested( true );
		$this->set_manager_instance( $this->get_manager_with_failing_synchronize( $error ) );

		$original_logger          = WC_Stripe_Logger::$logger;
		WC_Stripe_Logger::$logger = $this->get_logger_expecting_error( 'Native Store API checkout session synchronization failed.', $error );

		try {
			$data = WC_Stripe_Checkout_Session_Lifecycle::get_store_api_data();
		} finally {
			WC_Stripe_Logger::$logger = $original_logger;
			$this->set_manager_instance( null );
			$restore();
		}

		$this->assertSame( 'error', $data['status'] );
		$this->assertSame( $expected_message, $data['message'] );
		foreach ( $data as $value ) {
			if ( is_scalar( $value ) ) {
				$this->assertStringNotContainsString( $error->getMessage(), (string) $value );
			}
		}
	}

	/**
	 * Data provider for the error handling tests.
	 *
	 * @return array[]
	 */
	public function provide_synchronization_failures(): array {
		return [
			'PHP engine error is logged and masked'                               => [
				new TypeError( 'WC_Stripe_Checkout_Session_Manager::build_line_items(): Argument #1 ($cart_context) must be of type array, null given, called in /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/includes/class-wc-stripe-checkout-session-manager.php on line 123' ),
				null,
			],
			'standard Exception is logged and masked'                             => [
				new Exception( 'SQLSTATE[HY000] [2002] Connection refused in /var/www/html/wp-content/plugins/some-plugin/includes/class-db.php:87' ),
				null,
			],
			'Localised WC_Stripe_Exception is logged and shows localized message' => [
				new WC_Stripe_Exception( 'Array ( [body] => raw Stripe response dump )', 'There was a problem sending a request to the Stripe API endpoint.' ),
				'There was a problem sending a request to the Stripe API endpoint.',
			],
		];
	}
}
