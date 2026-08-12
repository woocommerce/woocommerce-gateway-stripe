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

		WC_Stripe_Helper::update_main_stripe_settings(
			array_merge(
				$original_settings,
				[
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
					'pmc_enabled'                => 'yes',
					'capture'                    => 'yes',
					'webhook_data'               => [
						'id'     => 'we_live',
						'secret' => 'whsec_live',
					],
					'test_webhook_data'          => [
						'id'     => 'we_test',
						'secret' => 'whsec_test',
					],
				]
			)
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
		$this->assertSame( 1, $created['revision'] );
		$this->assertSame( $created, $reused );

		$context = WC_Stripe_Checkout_Session_Context::get_context( $session_id );
		$this->assertIsArray( $context );
		$this->assertSame(
			WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() ),
			$context['amount']
		);
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
			$manager->synchronize();

			WC()->cart->set_quantity( $cart_item_key, 2 );
			WC()->cart->calculate_totals();
			$updated = $manager->synchronize();
		} finally {
			remove_filter( 'wc_stripe_request_body', $capture_body, 10 );
			remove_filter( 'pre_http_request', $mock_request, 10 );
		}

		$this->assertCount( 2, $requested_urls );
		$this->assertStringEndsWith( "/v1/checkout/sessions/$session_id", $requested_urls[1] );
		$this->assertSame( 2, $updated['revision'] );
		$this->assertSame( $session_id, $updated['session_id'] );
		$this->assertCount( 1, $captured_updates );

		$expected_amount = WC_Stripe_Helper::get_stripe_amount( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() );
		$this->assertSame( $expected_amount, $captured_updates[0]['line_items'][0]['price_data']['unit_amount'] );
		$this->assertSame( 1, $captured_updates[0]['line_items'][0]['quantity'] );
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
				'revision'      => 3,
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
		$this->assertSame( [ 'session_id', 'client_secret', 'revision', 'status', 'message' ], array_keys( $schema ) );

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
		$this->assertSame( 0, $data['revision'] );
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
		$this->assertSame( 0, $initial_data['revision'] );

		$this->assertSame( 'success', $data['status'] );
		$this->assertSame( $session_id, $data['session_id'] );
		$this->assertSame( 'cs_test_store_api_secret', $data['client_secret'] );
		$this->assertSame( 1, $data['revision'] );
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
