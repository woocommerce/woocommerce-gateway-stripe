<?php

/**
 * Unit tests for WC_Stripe_OCS_Payment_Gateway, the Optimized Checkout gateway variant.
 *
 * Behavior shared with the classic UPE flow is covered in WC_Stripe_UPE_Payment_Gateway_Test.
 */
class WC_Stripe_OCS_Payment_Gateway_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * Mocked value of return_url.
	 *
	 * @var string
	 */
	const MOCK_RETURN_URL = 'test_url';

	/**
	 * Initial setup.
	 */
	public function set_up() {
		parent::set_up();

		update_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME, 'yes' );

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['sepa_tokens_for_ideal']      = 'yes';
		$stripe_settings['sepa_tokens_for_bancontact'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Cleanup after each test.
	 */
	public function tear_down() {
		delete_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME );

		// Mocked API keys trip the 401 rate-limiter on unmocked calls; clear it so later tests are unaffected.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		parent::tear_down();
	}

	/**
	 * Test that the Adaptive Pricing currency selector div is rendered or omitted in payment_fields()
	 * based on the OC enabled flag, valid OC page, checkout sessions feature flag, and adaptive pricing setting.
	 *
	 * @dataProvider provide_payment_fields_currency_selector_rendering
	 *
	 * @param bool   $oc_enabled       Whether the Optimized Checkout Suite is enabled.
	 * @param bool   $valid_oc_page    Whether is_valid_optimized_checkout_page() returns true.
	 * @param bool   $feature_flag     Whether the checkout sessions feature flag is enabled.
	 * @param string $adaptive_pricing The 'adaptive_pricing' settings value.
	 * @param bool   $expect_selector  Whether the currency selector div should appear in the output.
	 */
	public function test_payment_fields_renders_currency_selector_conditionally(
		bool $oc_enabled,
		bool $valid_oc_page,
		bool $feature_flag,
		string $adaptive_pricing,
		bool $expect_selector
	): void {
		// The gateway exposes is_adaptive_pricing_supported() as a protected instance method,
		// allowing us to mock it directly without depending on the full settings/API stack.
		$show_adaptive_pricing = $oc_enabled && $valid_oc_page && $feature_flag && 'yes' === $adaptive_pricing;

		// Currency-selector rendering under OCS lives on WC_Stripe_OCS_Payment_Gateway;
		// WC_Stripe_UPE_Payment_Gateway is the non-OCS variant and never renders it.
		$gateway_class  = $oc_enabled ? WC_Stripe_OCS_Payment_Gateway::class : WC_Stripe_UPE_Payment_Gateway::class;
		$mocked_methods = $oc_enabled
			? [ 'get_return_url', 'is_valid_optimized_checkout_page', 'is_adaptive_pricing_supported' ]
			: [ 'get_return_url', 'is_adaptive_pricing_supported' ];

		$gateway = $this->getMockBuilder( $gateway_class )
			->setConstructorArgs( [] )
			->onlyMethods( $mocked_methods )
			->getMock();
		$gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );
		if ( $oc_enabled ) {
			$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( $valid_oc_page );
		}
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( $show_adaptive_pricing );

		add_filter( 'woocommerce_is_checkout', '__return_true' );

		try {
			ob_start();
			$gateway->payment_fields();
			$output = ob_get_clean();
		} finally {
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
		}

		$selector_div = '<div id="wc-stripe-currency-selector" class="wc-stripe-currency-selector" style="margin-top: 12px;"></div>';
		if ( $expect_selector ) {
			$this->assertStringContainsString( $selector_div, $output );
			$selector_position    = strpos( $output, $selector_div );
			$upe_element_position = strpos( $output, 'class="wc-stripe-upe-element"' );
			$this->assertNotFalse( $selector_position, 'Currency selector position should be detectable.' );
			$this->assertNotFalse( $upe_element_position, 'Payment element should be present in output.' );
			$this->assertLessThan(
				$upe_element_position,
				$selector_position,
				'Currency selector should render before the payment element.'
			);
		} else {
			$this->assertStringNotContainsString( $selector_div, $output );
		}
	}

	/**
	 * Data provider for test_payment_fields_renders_currency_selector_conditionally.
	 *
	 * @return array[]
	 */
	public function provide_payment_fields_currency_selector_rendering(): array {
		return [
			'renders when all conditions are met'                    => [
				'oc_enabled'       => true,
				'valid_oc_page'    => true,
				'feature_flag'     => true,
				'adaptive_pricing' => 'yes',
				'expect_selector'  => true,
			],
			'hidden when OC is disabled'                             => [
				'oc_enabled'       => false,
				'valid_oc_page'    => true,
				'feature_flag'     => true,
				'adaptive_pricing' => 'yes',
				'expect_selector'  => false,
			],
			'hidden when not a valid OC page'                        => [
				'oc_enabled'       => true,
				'valid_oc_page'    => false,
				'feature_flag'     => true,
				'adaptive_pricing' => 'yes',
				'expect_selector'  => false,
			],
			'hidden when checkout sessions feature flag is disabled' => [
				'oc_enabled'       => true,
				'valid_oc_page'    => true,
				'feature_flag'     => false,
				'adaptive_pricing' => 'yes',
				'expect_selector'  => false,
			],
			'hidden when adaptive pricing setting is disabled'       => [
				'oc_enabled'       => true,
				'valid_oc_page'    => true,
				'feature_flag'     => true,
				'adaptive_pricing' => 'no',
				'expect_selector'  => false,
			],
		];
	}

	/**
	 * Test that in test mode with Optimized Checkout and Adaptive Pricing enabled,
	 * the test copy renders before the currency selector.
	 */
	public function test_payment_fields_renders_test_copy_before_currency_selector(): void {
		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'get_return_url', 'is_valid_optimized_checkout_page', 'is_adaptive_pricing_supported' ] )
			->getMock();
		$gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( true );
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( true );
		$gateway->testmode = true;

		add_filter( 'woocommerce_is_checkout', '__return_true' );

		try {
			ob_start();
			$gateway->payment_fields();
			$output = ob_get_clean();
		} finally {
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
		}

		$selector_div         = '<div id="wc-stripe-currency-selector"';
		$selector_position    = strpos( $output, $selector_div );
		$test_copy_position   = strpos( $output, 'wc-stripe-payment-method-instruction' );
		$upe_element_position = strpos( $output, 'class="wc-stripe-upe-element"' );

		$this->assertNotFalse( $test_copy_position, 'Test copy should be present in output.' );
		$this->assertNotFalse( $selector_position, 'Currency selector should be present in output.' );
		$this->assertNotFalse( $upe_element_position, 'Payment element should be present in output.' );
		$this->assertLessThan(
			$selector_position,
			$test_copy_position,
			'Test copy should render before the currency selector.'
		);
		$this->assertLessThan(
			$upe_element_position,
			$selector_position,
			'Currency selector should render before the payment element.'
		);
	}

	/**
	 * showSaveOptionByMethod must be false for methods saved as a different Stripe
	 * type (Bancontact/iDEAL → SEPA) when Adaptive Pricing is active, since the
	 * Checkout Sessions flow cannot save them.
	 *
	 * @dataProvider provide_show_save_option_by_method_adaptive_pricing
	 *
	 * @param bool $adaptive_pricing_active Whether Adaptive Pricing is supported.
	 * @param bool $expected_converting     Expected map value for Bancontact/iDEAL.
	 */
	public function test_show_save_option_by_method_hides_converting_methods_with_adaptive_pricing(
		bool $adaptive_pricing_active,
		bool $expected_converting
	): void {
		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->onlyMethods( [ 'is_valid_optimized_checkout_page', 'is_adaptive_pricing_supported', 'get_upe_enabled_at_checkout_payment_method_ids' ] )
			->getMock();
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( true );
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( $adaptive_pricing_active );
		$gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )->willReturn(
			[
				WC_Stripe_Payment_Methods::CARD,
				WC_Stripe_Payment_Methods::BANCONTACT,
				WC_Stripe_Payment_Methods::IDEAL,
				WC_Stripe_Payment_Methods::SEPA_DEBIT,
			]
		);

		$get_config = new ReflectionMethod( WC_Stripe_OCS_Payment_Gateway::class, 'get_enabled_payment_method_config' );
		$get_config->setAccessible( true );
		$config = $get_config->invoke( $gateway );

		$this->assertIsArray( $config[ WC_Stripe_Payment_Methods::CARD ]['showSaveOptionByMethod'] ?? null );
		$by_method = $config[ WC_Stripe_Payment_Methods::CARD ]['showSaveOptionByMethod'];
		$this->assertSame( $expected_converting, $by_method[ WC_Stripe_Payment_Methods::BANCONTACT ] );
		$this->assertSame( $expected_converting, $by_method[ WC_Stripe_Payment_Methods::IDEAL ] );
		// Methods saved under their own type are unaffected by Adaptive Pricing.
		$this->assertTrue( $by_method[ WC_Stripe_Payment_Methods::SEPA_DEBIT ] );
	}

	/**
	 * Data provider for test_show_save_option_by_method_hides_converting_methods_with_adaptive_pricing.
	 *
	 * @return array[]
	 */
	public function provide_show_save_option_by_method_adaptive_pricing(): array {
		return [
			'Adaptive Pricing active: converting methods not savable' => [
				'adaptive_pricing_active' => true,
				'expected_converting'     => false,
			],
			'Adaptive Pricing inactive: converting methods savable'   => [
				'adaptive_pricing_active' => false,
				'expected_converting'     => true,
			],
		];
	}

	/**
	 * Under OCS, save_payment_method_to_store must be re-evaluated against the resolved method type, not the OC pseudo-method.
	 *
	 * @dataProvider provide_test_prepare_payment_information_oc_drops_save_flag_when_resolved_method_not_reusable
	 */
	public function test_prepare_payment_information_oc_drops_save_flag_when_resolved_method_not_reusable( string $resolved_type, bool $is_reusable, bool $expected_save_flag ): void {
		$order             = WC_Helper_Order::create_order();
		$payment_method_id = 'pm_test_' . $resolved_type;

		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'get_stripe_customer_id' ] )
			->getMock();

		$resolved_method_stub = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_reusable' ] )
			->getMockForAbstractClass();
		$resolved_method_stub->method( 'is_reusable' )->willReturn( $is_reusable );
		$gateway->payment_methods[ $resolved_type ] = $resolved_method_stub;

		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => $payment_method_id,
			'wc-stripe-new-payment-method' => 'true',
		];

		$payment_method_pre_http_filter = function ( $result, $args, $url ) use ( $payment_method_id, $resolved_type ) {
			if ( false !== strpos( $url, 'payment_methods/' . $payment_method_id ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode(
						[
							'id'     => $payment_method_id,
							'object' => 'payment_method',
							'type'   => $resolved_type,
						]
					),
				];
			}
			return $result;
		};
		add_filter( 'pre_http_request', $payment_method_pre_http_filter, 10, 3 );

		$gateway
			->method( 'get_stripe_customer_id' )
			->willReturn( 'cus_mock' );

		$reflection = new \ReflectionClass( WC_Stripe_UPE_Payment_Gateway::class );
		$method     = $reflection->getMethod( 'prepare_payment_information_from_request' );
		$method->setAccessible( true );

		try {
			$payment_information = $method->invoke( $gateway, $order );
		} finally {
			remove_filter( 'pre_http_request', $payment_method_pre_http_filter, 10 );
			$_POST = [];
		}

		$this->assertSame( $resolved_type, $payment_information['selected_payment_type'] );
		$this->assertSame( $expected_save_flag, $payment_information['save_payment_method_to_store'] );
	}

	/**
	 * Provider for `test_prepare_payment_information_oc_drops_save_flag_when_resolved_method_not_reusable`.
	 *
	 * @return array<string, array{string, bool, bool}>
	 */
	public function provide_test_prepare_payment_information_oc_drops_save_flag_when_resolved_method_not_reusable(): array {
		return [
			'iDEAL with per-method toggle disabled (bug)'            => [ WC_Stripe_Payment_Methods::IDEAL, false, false ],
			'iDEAL with per-method toggle enabled (regression)'      => [ WC_Stripe_Payment_Methods::IDEAL, true, true ],
			'Bancontact with per-method toggle disabled (bug)'       => [ WC_Stripe_Payment_Methods::BANCONTACT, false, false ],
			'Bancontact with per-method toggle enabled (regression)' => [ WC_Stripe_Payment_Methods::BANCONTACT, true, true ],
			'Card via OCS (regression)'                              => [ WC_Stripe_Payment_Methods::CARD, true, true ],
		];
	}

	/**
	 * Tests for `is_valid_optimized_checkout_page`.
	 *
	 * @dataProvider provide_test_is_valid_optimized_checkout_page
	 *
	 * @param bool $is_add_payment_method      Whether the current page is the "Add payment method" page.
	 * @param bool $is_changing_payment_method Whether the customer is changing their payment method for a subscription.
	 * @param bool $is_checkout                Whether the current page is the checkout page.
	 * @param bool $expected                   Whether `is_valid_optimized_checkout_page` should return true.
	 */
	public function test_is_valid_optimized_checkout_page( bool $is_add_payment_method, bool $is_changing_payment_method, bool $is_checkout, bool $expected ) {
		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->onlyMethods( [ 'is_on_add_payment_method_page', 'is_changing_payment_method_for_subscription' ] )
			->getMock();

		$gateway->method( 'is_on_add_payment_method_page' )->willReturn( $is_add_payment_method );
		$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( $is_changing_payment_method );
		$is_checkout_return = $is_checkout ? '__return_true' : '__return_false';
		add_filter( 'woocommerce_is_checkout', $is_checkout_return );

		try {
			$result = $gateway->is_valid_optimized_checkout_page();
		} finally {
			remove_filter( 'woocommerce_is_checkout', $is_checkout_return );
		}

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for `test_is_valid_optimized_checkout_page`.
	 *
	 * @return array[]
	 */
	public function provide_test_is_valid_optimized_checkout_page() {
		return [
			'Regular checkout page'                  => [
				'is_add_payment_method'      => false,
				'is_changing_payment_method' => false,
				'is_checkout'                => true,
				'expected'                   => true,
			],
			'Add payment method page'                => [
				'is_add_payment_method'      => true,
				'is_changing_payment_method' => false,
				'is_checkout'                => true,
				'expected'                   => false,
			],
			'Change payment method for subscription' => [
				'is_add_payment_method'      => false,
				'is_changing_payment_method' => true,
				'is_checkout'                => true,
				'expected'                   => false,
			],
			'All special pages'                      => [
				'is_add_payment_method'      => true,
				'is_changing_payment_method' => true,
				'is_checkout'                => true,
				'expected'                   => false,
			],
			'Non-checkout page'                      => [
				'is_add_payment_method'      => false,
				'is_changing_payment_method' => false,
				'is_checkout'                => false,
				'expected'                   => false,
			],
		];
	}

	/**
	 * Tests for `is_optimized_checkout_active`.
	 *
	 * Unlike `is_valid_optimized_checkout_page`, this helper must NOT depend on `is_checkout()`
	 * because OCS-aware token handling has to fire on My Account → Payment Methods (where
	 * `is_checkout()` is false) so that sub-gateway tokens still surface under the consolidated
	 * 'stripe' gateway and existing tokens are not orphaned by the cleanup sweep.
	 *
	 * @dataProvider provide_test_is_optimized_checkout_active
	 *
	 * @param bool $oc_enabled                 Value of the `oc_enabled` property on the gateway.
	 * @param bool $is_add_payment_method      Whether the current page is the "Add payment method" page.
	 * @param bool $is_changing_payment_method Whether the customer is changing their payment method for a subscription.
	 * @param bool $expected                   Whether `is_optimized_checkout_active` should return true.
	 */
	public function test_is_optimized_checkout_active( bool $oc_enabled, bool $is_add_payment_method, bool $is_changing_payment_method, bool $expected ) {
		// OCS-aware behavior lives on the OCS gateway; the base UPE gateway always reports inactive.
		// `oc_enabled` therefore maps to which gateway class is the active one.
		$class   = $oc_enabled ? WC_Stripe_OCS_Payment_Gateway::class : WC_Stripe_UPE_Payment_Gateway::class;
		$gateway = $this->getMockBuilder( $class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_on_add_payment_method_page', 'is_changing_payment_method_for_subscription' ] )
			->getMock();

		$gateway->oc_enabled = $oc_enabled;
		$gateway->method( 'is_on_add_payment_method_page' )->willReturn( $is_add_payment_method );
		$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( $is_changing_payment_method );

		$this->assertSame( $expected, $gateway->is_optimized_checkout_active() );
	}

	/**
	 * Data provider for `test_is_optimized_checkout_active`.
	 *
	 * @return array[]
	 */
	public function provide_test_is_optimized_checkout_active() {
		return [
			'OCS enabled, neutral page (e.g. My Account)' => [
				'oc_enabled'                 => true,
				'is_add_payment_method'      => false,
				'is_changing_payment_method' => false,
				'expected'                   => true,
			],
			'OCS disabled'                                => [
				'oc_enabled'                 => false,
				'is_add_payment_method'      => false,
				'is_changing_payment_method' => false,
				'expected'                   => false,
			],
			'OCS enabled, add payment method page'        => [
				'oc_enabled'                 => true,
				'is_add_payment_method'      => true,
				'is_changing_payment_method' => false,
				'expected'                   => false,
			],
			'OCS enabled, change payment method'          => [
				'oc_enabled'                 => true,
				'is_add_payment_method'      => false,
				'is_changing_payment_method' => true,
				'expected'                   => false,
			],
		];
	}

	/**
	 * On the admin order-details screen — which is not a checkout page — the OCS gateway must
	 * still surface the payment-method title stored on the order, rather than falling through
	 * to the parent's generic title. Guards the regression where the order-title resolution was
	 * gated behind the checkout-only is_valid_optimized_checkout_page() render check.
	 */
	public function test_get_title_uses_stored_order_title_on_order_details_page() {
		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->onlyMethods( [ 'is_on_add_payment_method_page', 'is_changing_payment_method_for_subscription', 'is_order_details_page' ] )
			->getMock();

		// Order-details screen: not a checkout page, not add-payment / change-payment.
		$gateway->method( 'is_on_add_payment_method_page' )->willReturn( false );
		$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( false );
		$gateway->method( 'is_order_details_page' )->willReturn( true );
		add_filter( 'woocommerce_is_checkout', '__return_false' );

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method_title( 'Cartes Bancaires' );
		$order->save();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, 'cs_test_title' );

		global $theorder;
		$previous_theorder = $theorder;
		$theorder          = $order;

		try {
			$title = $gateway->get_title();
		} finally {
			$theorder = $previous_theorder;
			remove_filter( 'woocommerce_is_checkout', '__return_false' );
		}

		$this->assertSame( 'Cartes Bancaires', $title );
	}

	/**
	 * get_title() must use the static title helper, not instantiate WC_Stripe_UPE_Payment_Method_OC.
	 *
	 * The OC payment method mimics the 'stripe' gateway id and re-registers subscription hooks in
	 * its constructor; instantiating it from the broadly-called title path previously caused
	 * duplicate renewal charges (see #5325). This guards against reintroducing that instantiation.
	 */
	public function test_get_title_does_not_instantiate_oc_payment_method() {
		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->onlyMethods( [ 'is_on_add_payment_method_page', 'is_changing_payment_method_for_subscription' ] )
			->getMock();
		$gateway->method( 'is_on_add_payment_method_page' )->willReturn( false );
		$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( false );
		add_filter( 'woocommerce_is_checkout', '__return_true' );

		// Reset the shared hook manager so that *any* OC-method instantiation would register
		// its per-id subscription hooks, which we can then detect.
		$manager_prop = new ReflectionProperty( WC_Stripe_Hook_Manager::class, 'instance' );
		$manager_prop->setAccessible( true );
		$previous_manager = $manager_prop->getValue();
		$manager_prop->setValue( null, null );

		$hook         = 'woocommerce_scheduled_subscription_payment_stripe';
		$count_before = isset( $GLOBALS['wp_filter'][ $hook ] ) ? count( $GLOBALS['wp_filter'][ $hook ]->callbacks[10] ?? [] ) : 0;

		try {
			$title = $gateway->get_title();
		} finally {
			$manager_prop->setValue( null, $previous_manager );
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
		}

		$count_after = isset( $GLOBALS['wp_filter'][ $hook ] ) ? count( $GLOBALS['wp_filter'][ $hook ]->callbacks[10] ?? [] ) : 0;

		$this->assertSame( WC_Stripe_UPE_Payment_Method_OC::get_alternative_title(), $title );
		$this->assertSame( $count_before, $count_after, 'get_title() must not instantiate the OC payment method (which registers subscription hooks).' );
	}

	/**
	 * The OCS gateway is a subclass of the UPE gateway, so the subscriptions trait must register
	 * its once-global integration hooks (e.g. mandate-info injection, renewal-meta cleanup) for it.
	 *
	 * Guards the regression where the trait's strict class-equality guard skipped subclasses,
	 * silently dropping subscription hooks whenever Optimized Checkout was the active gateway.
	 */
	public function test_ocs_gateway_registers_subscription_integration_hooks() {
		$gateway = new WC_Stripe_OCS_Payment_Gateway();

		// Plugin-level hook registration is tracked by the shared hook manager; reset its
		// singleton so registration can run again for this gateway instance.
		$manager_prop = new ReflectionProperty( WC_Stripe_Hook_Manager::class, 'instance' );
		$manager_prop->setAccessible( true );
		$previous_manager = $manager_prop->getValue();
		$manager_prop->setValue( null, null );

		$filter   = 'wc_stripe_generate_create_intent_request';
		$callback = [ $gateway, 'add_subscription_information_to_intent' ];
		remove_filter( $filter, $callback );

		try {
			$gateway->maybe_init_subscriptions();
			$registered = has_filter( $filter, $callback );
		} finally {
			remove_filter( $filter, $callback );
			remove_action( 'woocommerce_scheduled_subscription_payment_stripe', [ $gateway, 'scheduled_subscription_payment' ] );
			$manager_prop->setValue( null, $previous_manager );
		}

		$this->assertNotFalse( $registered, 'OCS gateway (a UPE subclass) must register the once-global subscription hooks.' );
	}

	/**
	 * javascript_params() must expose the OCS keys, gated on the render check: the OC flags
	 * mirror is_valid_optimized_checkout_page(), and the OCS-only keys (OCLayout, etc.) are
	 * present only when the Payment Element may render.
	 *
	 * @dataProvider provide_test_javascript_params_ocs_keys
	 *
	 * @param bool $valid_oc_page Whether is_valid_optimized_checkout_page() returns true.
	 */
	public function test_javascript_params_exposes_ocs_keys( bool $valid_oc_page ) {
		$gateway = $this->getMockBuilder( WC_Stripe_OCS_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods(
				[
					'get_return_url',
					'get_stripe_return_url',
					'is_changing_payment_method_for_subscription',
					'is_subscription_item_in_cart',
					'is_valid_optimized_checkout_page',
					'is_adaptive_pricing_supported',
					'get_excluded_payment_method_types',
				]
			)
			->getMock();

		$gateway->method( 'get_return_url' )->willReturn( '' );
		$gateway->method( 'get_stripe_return_url' )->willReturn( '' );
		$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( false );
		$gateway->method( 'is_subscription_item_in_cart' )->willReturn( false );
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( $valid_oc_page );
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( true );
		$gateway->method( 'get_excluded_payment_method_types' )->willReturn( [] );

		$this->set_stripe_account_data( [ 'country' => 'US' ] );

		$params = $gateway->javascript_params();

		$this->assertSame( $valid_oc_page, $params['isOCEnabled'] );
		$this->assertSame( $valid_oc_page, $params['shouldShowOptimizedCheckout'] );
		$this->assertSame( $valid_oc_page, $params['isAdaptivePricingEnabled'] );

		if ( $valid_oc_page ) {
			$this->assertSame( WC_Stripe_UPE_Payment_Gateway::OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT, $params['OCLayout'] );
			$this->assertArrayHasKey( 'paymentMethodConfigurationId', $params );
			$this->assertSame( [], $params['excludedPaymentMethodTypes'] );
			$this->assertSame( WC_Stripe_UPE_Payment_Method_OC::get_classic_title(), $params['optimizedCheckoutClassicTitle'] );
		} else {
			$this->assertArrayNotHasKey( 'OCLayout', $params );
			$this->assertArrayNotHasKey( 'paymentMethodConfigurationId', $params );
			$this->assertArrayNotHasKey( 'excludedPaymentMethodTypes', $params );
			$this->assertArrayNotHasKey( 'optimizedCheckoutClassicTitle', $params );
		}
	}

	/**
	 * Data provider for `test_javascript_params_exposes_ocs_keys`.
	 *
	 * @return array[]
	 */
	public function provide_test_javascript_params_ocs_keys(): array {
		return [
			'valid OC page: OCS keys present'      => [ 'valid_oc_page' => true ],
			'not a valid OC page: OCS keys absent' => [ 'valid_oc_page' => false ],
		];
	}
}
