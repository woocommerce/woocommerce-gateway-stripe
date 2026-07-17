<?php

use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Unit tests for the UPE payment gateway
 */
class WC_Stripe_UPE_Payment_Gateway_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Asserts the exact persisted marker without exposing a production getter only for tests.
	 *
	 * @var string
	 */
	private const ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION = 'wc_stripe_adaptive_pricing_session_amount_mismatch_detected';

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
	 * Array of available payment methods.
	 *
	 * @var array
	 */
	private $available_payment_methods;

	/**
	 * Mocked value of return_url.
	 *
	 * @var string
	 */
	const MOCK_RETURN_URL = 'test_url';

	/**
	 * The form `MOCK_RETURN_URL` takes after being passed through `wp_safe_redirect()` /
	 * `wp_sanitize_redirect()` — a leading `/` is prepended to scheme-less URLs.
	 *
	 * @var string
	 */
	const MOCK_RETURN_URL_AFTER_REDIRECT = '/test_url';

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
		'id'                   => 'pi_mock',
		'object'               => 'payment_intent',
		'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
		'last_payment_error'   => [],
		'client_secret'        => 'cs_mock',
		'charges'              => [
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
		'payment_method_types' => [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::LINK,
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

		update_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME, 'yes' );

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['sepa_tokens_for_ideal']      = 'yes';
		$stripe_settings['sepa_tokens_for_bancontact'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods(
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
					'update_saved_payment_method',
				]
			)
			->getMock();

		$this->mock_gateway
			->method( 'get_return_url' )
			->will(
				$this->returnValue( self::MOCK_RETURN_URL )
			);

		$this->mock_gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->onlyMethods( [ 'create_and_confirm_payment_intent', 'update_and_confirm_payment_intent', 'create_and_confirm_setup_intent' ] )
			->getMock();

		$this->mock_stripe_customer = $this->getMockBuilder( WC_Stripe_Customer::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'create_customer',
					'update_customer',
				]
			)
			->getMock();

		$this->mock_stripe_customer
			->method( 'create_customer' )
			->will(
				$this->returnValue( 'cus_mock' )
			);
		$this->mock_stripe_customer
			->method( 'update_customer' )
			->will(
				$this->returnValue( 'cus_mock' )
			);

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);

		$order_helper
			->method( 'lock_order_payment' )
			->will(
				$this->returnValue( false )
			);

		$order_helper->method( 'unlock_order_payment' );

		WC_Stripe_Order_Helper::set_instance( $order_helper );

		// Clear any notices left over from previous tests so per-test assertions
		// against wc_get_notices() are reliable.
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}
	}

	public function tear_down() {
		delete_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME );
		delete_option( self::ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION );

		// The tests in this file do not mock ALL the calls to the Stripe API, and as we use mocked API keys they trigger the 401 rate-limiter,
		// this is not a problem for these tests as they don't depend on the reponses.
		//
		// TODO: Remove this once we've mocked all calls to the Stripe API (either using the pre_http_request filter, or by using a mocked WC_Stripe_API class).
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		parent::tear_down();
	}

	/**
	 * Helper function to ensure that scripts and styles are de-registered and de-queued.
	 *
	 * @param string[] $script_handles The script handles to clean up.
	 * @param string[] $style_handles  The style handles to clean up.
	 * @return void
	 */
	protected function clean_up_scripts( array $script_handles = [], array $style_handles = [] ): void {
		foreach ( $script_handles as $script_handle ) {
			wp_deregister_script( $script_handle );
			wp_dequeue_script( $script_handle );
		}

		foreach ( $style_handles as $style_handle ) {
			wp_deregister_style( $style_handle );
			wp_dequeue_style( $style_handle );
		}
	}

	/**
	 * Helper function to set $_POST vars for saved payment method.
	 */
	private function set_postvars_for_saved_payment_method() {
		$token = WC_Helper_Token::create_token( 'pm_mock' );
		$_POST = [
			'payment_method'                                             => WC_Stripe_UPE_Payment_Gateway::ID,
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
			'customer_name'              => 'Jeroen Sormani',
			'customer_email'             => 'admin@example.org',
			'site_url'                   => 'http://example.org',
			'order_id'                   => $order_number,
			'order_key'                  => $order_key,
			'payment_type'               => 'single',
			'signature'                  => sprintf( '%d:%s', $order->get_id(), md5( implode( '-', [ absint( $order->get_id() ), $order->get_order_key(), $order->get_customer_id(), $amount ] ) ) ),
			'tax_amount'                 => WC_Stripe_Helper::get_stripe_amount( $total_tax, strtolower( $currency ) ),
			'is_legacy_checkout_enabled' => 'no',
			'is_oc_enabled'              => 'no',
			'pmc_enabled'                => 'no',
		];
		return [ $amount, $description, $metadata, strtolower( $currency ) ];
	}

	/**
	 * Helper method to create a mock express checkout payment method.
	 *
	 * @param string $payment_method_id      The payment method ID.
	 * @param string $express_payment_method The express payment method type.
	 * @return object The mock express checkout payment method.
	 */
	private function get_mock_express_checkout_payment_method( string $payment_method_id, string $express_payment_method ): object {
		return (object) [
			'id'              => $payment_method_id,
			'object'          => 'payment_method',
			'billing_details' => [
				'address' => [
					'city'        => 'San Francisco',
					'country'     => 'US',
					'line1'       => '60 29th Street 343',
					'line2'       => '',
					'postal_code' => '94110',
					'state'       => 'CA',
				],
				'email'   => 'test.express.checkout@example.com',
				'name'    => 'Test Express Checkout',
				'phone'   => '+1234567890',
				'tax_id'  => null,
			],
			'type'            => 'card',
			'card'            => [
				'brand'       => 'visa',
				'last4'       => '4242',
				'country'     => 'US',
				'exp_month'   => '12',
				'exp_year'    => '2025',
				'funding'     => 'credit',
				'fingerprint' => 'FingerMOCK',
				'wallet'      => [
					'type'                  => $express_payment_method,
					$express_payment_method => [
						'type' => $express_payment_method,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider get_upe_available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods( $country, $available_payment_methods ) {
		$this->mock_payment_method_configurations( $available_payment_methods );
		$this->set_stripe_account_data( [ 'country' => $country ] ); // TODO: Verify if the country is actually changing in the gateway.
		$this->assertSame( $available_payment_methods, $this->mock_gateway->get_upe_available_payment_methods(), "Available payment methods are not the same for $country" );
	}

	/**
	 * Data provider for {@see test_get_upe_available_payment_methods()}.
	 *
	 * @return array[]
	 */
	public function get_upe_available_payment_methods_provider(): array {
		return [
			[
				'US',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Amazon_Pay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_BLIK::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Affirm::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
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
					WC_Stripe_UPE_Payment_Method_BLIK::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
			[
				'PL',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_BLIK::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Multibanco::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
		];
	}

	/**
	 * Tests for `get_upe_enabled_at_checkout_payment_method_ids`.
	 *
	 * @param array $available_methods The available payment methods.
	 * @param bool $oc_enabled Whether the OC feature is enabled.
	 * @param array $expected The expected payment method IDs.
	 * @return void
	 *
	 * @dataProvider provide_test_get_upe_enabled_at_checkout_payment_method_ids
	 */
	public function test_get_upe_enabled_at_checkout_payment_method_ids( $available_methods, $oc_enabled, $expected ) {
		$this->mock_gateway->oc_enabled = $oc_enabled;

		$this->mock_payment_method_configurations( $available_methods );

		$actual = $this->mock_gateway->get_upe_enabled_at_checkout_payment_method_ids();

		// Clean up.
		$this->mock_gateway->oc_enabled = false;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_get_upe_enabled_at_checkout_payment_method_ids`.
	 *
	 * @return array[]
	 */
	public function provide_test_get_upe_enabled_at_checkout_payment_method_ids() {
		return [
			'Default'    => [
				'available methods' => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
				'OC enabled'        => false,
				'expected'          => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
			],
			'OC enabled' => [
				'available methods (ignored)' => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				],
				'OC enabled'                  => true,
				'expected'                    => [
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
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

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'get_return_url', 'is_valid_optimized_checkout_page', 'is_adaptive_pricing_supported' ] )
			->getMock();
		$gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( $valid_oc_page );
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( $show_adaptive_pricing );
		$gateway->oc_enabled = $oc_enabled;

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
	 * Test that in test mode with Optimized Checkout and Adaptive Pricing enabled,
	 * the test copy renders before the currency selector.
	 */
	public function test_payment_fields_renders_test_copy_before_currency_selector(): void {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'get_return_url', 'is_valid_optimized_checkout_page', 'is_adaptive_pricing_supported' ] )
			->getMock();
		$gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( true );
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( true );
		$gateway->oc_enabled = true;
		$gateway->testmode   = true;

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
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
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
		$gateway->oc_enabled = true;

		$get_config = new ReflectionMethod( WC_Stripe_UPE_Payment_Gateway::class, 'get_enabled_payment_method_config' );
		$get_config->setAccessible( true );
		$config = $get_config->invoke( $gateway );

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
	 * OC must represent Stripe in the Cart/Checkout block editor, even though is_checkout() (and thus
	 * is_valid_optimized_checkout_page()) is false there.
	 *
	 * @dataProvider provide_should_render_optimized_checkout
	 *
	 * @param bool $oc_enabled      Whether Optimized Checkout is enabled.
	 * @param bool $valid_oc_page   Whether is_valid_optimized_checkout_page() returns true.
	 * @param bool $in_block_editor Whether we are editing a post that hosts the Checkout block in admin.
	 * @param bool $expected        Expected return value.
	 */
	public function test_should_render_optimized_checkout(
		bool $oc_enabled,
		bool $valid_oc_page,
		bool $in_block_editor,
		bool $expected
	): void {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'is_valid_optimized_checkout_page' ] )
			->getMock();
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( $valid_oc_page );
		$gateway->oc_enabled = $oc_enabled;

		$initial_get            = $_GET;
		$initial_current_screen = $GLOBALS['current_screen'] ?? null;

		if ( $in_block_editor ) {
			$post_id = self::factory()->post->create( [ 'post_content' => '<!-- wp:woocommerce/checkout /-->' ] );
			// is_editing_cart_or_checkout_block() reads the edited post ID from the request.
			$_GET['post'] = (string) $post_id;
			// is_admin() reads the current screen; set an admin screen so the admin branch is exercised.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = WP_Screen::get( 'post.php' );
		}

		try {
			$this->assertSame( $expected, $gateway->should_render_optimized_checkout() );
		} finally {
			$_GET = $initial_get;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = $initial_current_screen;
		}
	}

	/**
	 * Data provider for test_should_render_optimized_checkout.
	 *
	 * @return array[]
	 */
	public function provide_should_render_optimized_checkout(): array {
		return [
			'OC disabled is never rendered'                   => [
				'oc_enabled'      => false,
				'valid_oc_page'   => true,
				'in_block_editor' => true,
				'expected'        => false,
			],
			'OC enabled on a valid OC checkout page'          => [
				'oc_enabled'      => true,
				'valid_oc_page'   => true,
				'in_block_editor' => false,
				'expected'        => true,
			],
			'OC enabled while editing the Checkout block'     => [
				'oc_enabled'      => true,
				'valid_oc_page'   => false,
				'in_block_editor' => true,
				'expected'        => true,
			],
			'OC enabled but neither checkout page nor editor' => [
				'oc_enabled'      => true,
				'valid_oc_page'   => false,
				'in_block_editor' => false,
				'expected'        => false,
			],
		];
	}

	/**
	 * In the block editor with OC enabled, the Blocks config must expose the OC element (keyed under
	 * the 'card'/OC id, mapped to 'stripe' on the client) even when only Cash App Pay is enabled.
	 */
	public function test_get_enabled_payment_method_config_exposes_oc_method_in_block_editor(): void {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'should_render_optimized_checkout', 'get_upe_enabled_at_checkout_payment_method_ids', 'is_adaptive_pricing_supported' ] )
			->getMock();
		// Editor context: OC stands in for Stripe even though is_valid_optimized_checkout_page() is false.
		$gateway->method( 'should_render_optimized_checkout' )->willReturn( true );
		$gateway->method( 'is_adaptive_pricing_supported' )->willReturn( false );
		// Card disabled; only Cash App Pay enabled.
		$gateway->method( 'get_upe_enabled_at_checkout_payment_method_ids' )->willReturn( [ WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
		$gateway->oc_enabled = true;

		$get_config = new ReflectionMethod( WC_Stripe_UPE_Payment_Gateway::class, 'get_enabled_payment_method_config' );
		$get_config->setAccessible( true );
		$config = $get_config->invoke( $gateway );

		// The OC element (id 'card', mapped to 'stripe' client-side) must be present; Cash App Pay
		// renders inside it rather than as a standalone method.
		$this->assertArrayHasKey( WC_Stripe_Payment_Methods::OC, $config );
		$this->assertArrayNotHasKey( WC_Stripe_Payment_Methods::CASHAPP_PAY, $config );
	}

	/**
	 * Test that payment_scripts registers the wc-stripe-upe-classic script with the correct version and dependencies.
	 *
	 * Because build/upe-classic.asset.php may not be present in test environments, we have conditional logic as follows:
	 *  - When build/upe-classic.asset.php exists, we verify the script version and dependencies from that file are used.
	 *  - When build/upe-classic.asset.php does not exist, we verify the fallback values are used.
	 */
	public function test_payment_scripts_registers_script_with_correct_version(): void {
		$asset_path = WC_STRIPE_PLUGIN_PATH . '/build/upe-classic.asset.php';

		// Determine the expected version without modifying any files: if the compiled asset file
		// is present (i.e. a build has been run), mirror the same logic used in payment_scripts().
		$expected_version      = WC_STRIPE_VERSION;
		$expected_dependencies = [ 'stripe', 'wc-checkout' ];
		if ( file_exists( $asset_path ) ) {
			$asset = require $asset_path;
			if ( is_array( $asset ) ) {
				if ( isset( $asset['version'] ) ) {
					$expected_version = $asset['version'];
				}
				if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
					$expected_dependencies = array_merge( $expected_dependencies, $asset['dependencies'] );
				}
			}
		} else {
			$expected_dependencies = array_merge( $expected_dependencies, [ 'wp-i18n' ] );
		}

		// Build a gateway mock that stubs javascript_params to avoid full WooCommerce/Stripe
		// account setup, which is not the subject of this test.
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'javascript_params', 'get_return_url' ] )
			->getMock();
		$gateway->method( 'javascript_params' )->willReturn( [] );
		$gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );
		$gateway->enabled = 'yes';

		// Make is_checkout() return true so payment_scripts() passes its page guard.
		add_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->clean_up_scripts(
			[ 'stripe', 'wc-stripe-upe-classic' ],
			[ 'stripelink_styles', 'wc-stripe-upe-classic' ]
		);

		$gateway->payment_scripts();

		// The script should be registered with the version we derived above.
		$script_is_registered = wp_script_is( 'wc-stripe-upe-classic', 'registered' );
		$registered_script    = wp_scripts()->registered['wc-stripe-upe-classic'] ?? null;

		// Clean up registered scripts/styles and the filter so subsequent tests are not affected.
		remove_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->clean_up_scripts(
			[ 'stripe', 'wc-stripe-upe-classic' ],
			[ 'stripelink_styles', 'wc-stripe-upe-classic' ]
		);

		$this->assertTrue( $script_is_registered, 'wc-stripe-upe-classic script is not registered' );
		$this->assertNotNull( $registered_script, 'wc-stripe-upe-classic script is not a valid object' );
		$this->assertSame( $expected_version, $registered_script->ver, 'wc-stripe-upe-classic script version is not the same as the expected version' );
		$this->assertSame( $expected_dependencies, $registered_script->deps, 'wc-stripe-upe-classic script dependencies are not the same as the expected dependencies' );
	}

	/**
	 * The billing source for the deferred-payment flows is selected all-or-nothing:
	 * the order on a validated pay-for-order page (so guests still get full address
	 * details), otherwise the customer, with a wholesale fallback to the customer when
	 * the order has no usable billing email.
	 *
	 * @dataProvider provide_deferred_flow_billing_data_scenarios
	 *
	 * @param bool   $is_pay_for_order Whether the page is the validated pay-for-order endpoint.
	 * @param bool   $order_has_email  Whether the order carries a billing email.
	 * @param bool   $with_customer    Whether a customer is available as a source.
	 * @param string $expected_source  Expected source: 'order', 'customer', or 'none'.
	 */
	public function test_get_deferred_flow_billing_data( bool $is_pay_for_order, bool $order_has_email, bool $with_customer, string $expected_source ) {
		$order_billing    = [
			'name'    => 'Order Buyer',
			'email'   => 'order-buyer@example.com',
			'phone'   => '+15551234567',
			'address' => [
				'country'     => 'US',
				'line1'       => '123 Order St',
				'line2'       => 'Suite 5',
				'city'        => 'Orderville',
				'state'       => 'CA',
				'postal_code' => '90001',
			],
		];
		$customer_billing = [
			'name'    => 'Cust Omer',
			'email'   => 'customer@example.com',
			'phone'   => '+447700900000',
			'address' => [
				'country'     => 'GB',
				'line1'       => '999 Customer Ave',
				'line2'       => 'Flat 2',
				'city'        => 'London',
				'state'       => 'LND',
				'postal_code' => 'SW1A 2AA',
			],
		];

		// A bare (unsaved) order keeps the test free of the DB and of helper-seeded defaults.
		$order = new WC_Order();
		$order->set_billing_first_name( 'Order' );
		$order->set_billing_last_name( 'Buyer' );
		$order->set_billing_phone( $order_billing['phone'] );
		$order->set_billing_country( $order_billing['address']['country'] );
		$order->set_billing_address_1( $order_billing['address']['line1'] );
		$order->set_billing_address_2( $order_billing['address']['line2'] );
		$order->set_billing_city( $order_billing['address']['city'] );
		$order->set_billing_state( $order_billing['address']['state'] );
		$order->set_billing_postcode( $order_billing['address']['postal_code'] );
		if ( $order_has_email ) {
			$order->set_billing_email( $order_billing['email'] );
		}

		$customer = null;
		if ( $with_customer ) {
			$customer = new WC_Customer();
			$customer->set_billing_first_name( 'Cust' );
			$customer->set_billing_last_name( 'Omer' );
			$customer->set_billing_email( $customer_billing['email'] );
			$customer->set_billing_phone( $customer_billing['phone'] );
			$customer->set_billing_country( $customer_billing['address']['country'] );
			$customer->set_billing_address_1( $customer_billing['address']['line1'] );
			$customer->set_billing_address_2( $customer_billing['address']['line2'] );
			$customer->set_billing_city( $customer_billing['address']['city'] );
			$customer->set_billing_state( $customer_billing['address']['state'] );
			$customer->set_billing_postcode( $customer_billing['address']['postal_code'] );
		}

		$result = $this->mock_gateway->get_deferred_flow_billing_data( $is_pay_for_order, $order, $customer );

		switch ( $expected_source ) {
			case 'order':
				$this->assertSame( $order_billing, $result );
				break;
			case 'customer':
				// Asserting the whole array proves the fallback is all-or-nothing: no order field leaks in.
				$this->assertSame( $customer_billing, $result );
				break;
			default:
				$this->assertNull( $result );
		}
	}

	/**
	 * Data provider for test_get_deferred_flow_billing_data.
	 *
	 * @return array<string, array{0: bool, 1: bool, 2: bool, 3: string}>
	 */
	public function provide_deferred_flow_billing_data_scenarios(): array {
		return [
			'pay-for-order uses the order when it has an email'                    => [ true, true, true, 'order' ],
			'pay-for-order falls back to the customer when the order has no email' => [ true, false, true, 'customer' ],
			'change/add payment method uses the customer even with an order'       => [ false, true, true, 'customer' ],
			'returns null when neither order nor customer is usable'               => [ true, false, false, 'none' ],
		];
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
	 * Arranges a gateway that will charge a card successfully, and returns the order to charge.
	 *
	 * @param string $cart_hash     The cart hash to stamp on the order.
	 * @param string $billing_email The billing email to set on the order.
	 * @param int    $expected_charges How many times the gateway is expected to reach Stripe across the test.
	 * @return WC_Order
	 */
	private function arrange_paid_cart_gateway( string $cart_hash, string $billing_email, int $expected_charges ): WC_Order {
		$mock_intent = (object) wp_parse_args(
			[ 'payment_method' => 'pm_mock' ],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$this->mock_gateway->intent_controller
			->expects( $this->exactly( $expected_charges ) )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway->method( 'get_stripe_customer_id' )->willReturn( 'cus_mock' );

		// The shared mock stubs this to null, which would leave the order unpaid and never record the marker.
		// Each charge needs its own id as Stripe would issue: process_response() rejects a charge already consumed by another order.
		$charge_count = 0;
		$this->mock_gateway->method( 'get_latest_charge_from_intent' )->willReturnCallback(
			function () use ( &$charge_count ) {
				++$charge_count;

				return (object) [
					'id'       => 'ch_mock_' . $charge_count,
					'captured' => true,
					'status'   => 'succeeded',
				];
			}
		);

		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-confirmation-token' => '',
		];

		return $this->create_checkout_order( $cart_hash, $billing_email );
	}

	/**
	 * Creates an order that looks like it came from the given cart.
	 *
	 * @param string $cart_hash     The cart hash to stamp on the order.
	 * @param string $billing_email The billing email to set on the order.
	 * @return WC_Order
	 */
	private function create_checkout_order( string $cart_hash, string $billing_email ): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->set_cart_hash( $cart_hash );
		$order->set_billing_email( $billing_email );
		$order->save();

		return $order;
	}

	/**
	 * Fills the cart, so a test can tell a cart that was emptied from one that was never populated.
	 *
	 * @return void
	 */
	private function arrange_cart_with_a_product(): void {
		WC()->session->init();
		WC()->cart->empty_cart();

		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		WC()->cart->add_to_cart( $product->get_id(), 1 );
	}

	/**
	 * A declined card leaves the order unpaid, and the shopper still needs their cart to try another one.
	 *
	 * @return void
	 */
	public function test_process_payment_leaves_the_cart_when_the_charge_is_declined(): void {
		$order = $this->create_checkout_order( 'declined_hash', 'shopper@example.com' );
		$this->arrange_cart_with_a_product();

		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-confirmation-token' => '',
		];

		$this->mock_gateway->method( 'get_stripe_customer_id' )->willReturn( 'cus_mock' );
		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willThrowException( new WC_Stripe_Exception( 'card_declined', 'Your card was declined.' ) );

		$response = $this->mock_gateway->process_payment( $order->get_id() );

		$this->assertEquals( 'failure', $response['result'] );
		$this->assertNull( wc_get_order( $order->get_id() )->get_date_paid( 'edit' ) );
		$this->assertFalse( WC()->cart->is_empty(), 'A declined payment must leave the cart alone so the shopper can retry.' );
	}

	/**
	 * An intent awaiting 3DS reaches the end of processing with no charge: the card is only charged once the shopper
	 * authenticates, so clearing the cart here would strand a failed authentication with nothing to retry.
	 *
	 * @return void
	 */
	public function test_process_payment_leaves_the_cart_when_the_intent_still_needs_authentication(): void {
		$order = $this->create_checkout_order( '3ds_hash', 'shopper@example.com' );
		$this->arrange_cart_with_a_product();

		$_POST = [
			'payment_method'               => 'stripe',
			'wc-stripe-payment-method'     => 'pm_mock',
			'wc-stripe-confirmation-token' => '',
		];

		$mock_intent = (object) wp_parse_args(
			[ 'payment_method' => 'pm_mock' ],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$this->mock_gateway->method( 'get_stripe_customer_id' )->willReturn( 'cus_mock' );
		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		// The shared mock stubs get_latest_charge_from_intent() to null, which is the shape of an intent still awaiting 3DS.
		$this->mock_gateway->process_payment( $order->get_id() );

		$this->assertNull( wc_get_order( $order->get_id() )->get_date_paid( 'edit' ) );
		$this->assertFalse( WC()->cart->is_empty(), 'An unauthenticated payment must leave the cart alone.' );
	}

	/**
	 * A lost checkout response leaves the cart intact, so core builds a second order for a cart Stripe already charged.
	 *
	 * @return void
	 */
	public function test_process_payment_prevents_a_duplicate_charge_for_the_same_cart(): void {
		// Only the first submission may reach Stripe.
		$first_order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 1 );

		$first_response = $this->mock_gateway->process_payment( $first_order->get_id() );

		$this->assertEquals( 'success', $first_response['result'] );
		$this->assertNotNull( wc_get_order( $first_order->get_id() )->get_date_paid( 'edit' ) );

		$second_order    = $this->create_checkout_order( 'dupe_hash', 'shopper@example.com' );
		$second_response = $this->mock_gateway->process_payment( $second_order->get_id() );

		$this->assertEquals( 'success', $second_response['result'] );
		$this->assertNull( wc_get_order( $second_order->get_id() )->get_date_paid( 'edit' ), 'The resubmitted order must never be charged.' );
	}

	/**
	 * Once the shopper has seen an order's confirmation page, a genuine repurchase of the same items must go through.
	 *
	 * @return void
	 */
	public function test_process_payment_allows_a_repurchase_after_the_thankyou_page(): void {
		$first_order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 2 );
		$this->mock_gateway->process_payment( $first_order->get_id() );

		// The shopper reaching the confirmation page is what tells the guard the next same-cart order is deliberate.
		do_action( 'woocommerce_thankyou', $first_order->get_id() );

		$repurchase = $this->create_checkout_order( 'dupe_hash', 'shopper@example.com' );
		$this->mock_gateway->process_payment( $repurchase->get_id() );

		$this->assertNotNull( wc_get_order( $repurchase->get_id() )->get_date_paid( 'edit' ), 'A repurchase after confirmation must be charged, not treated as a duplicate.' );
	}

	/**
	 * A confirmation page for an unrelated order must not retire a still-valid marker.
	 *
	 * @return void
	 */
	public function test_thankyou_for_another_order_keeps_the_marker(): void {
		$first_order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 1 );
		$this->mock_gateway->process_payment( $first_order->get_id() );

		// A different order's confirmation page is not proof this cart was seen, so the guard must still fire.
		do_action( 'woocommerce_thankyou', $first_order->get_id() + 1000 );

		$second_order = $this->create_checkout_order( 'dupe_hash', 'shopper@example.com' );
		$this->mock_gateway->process_payment( $second_order->get_id() );

		$this->assertNull( wc_get_order( $second_order->get_id() )->get_date_paid( 'edit' ), 'The resubmitted order must still be caught as a duplicate.' );
	}

	/**
	 * Core creates the order before the gateway runs, so the resubmission leaves one behind that is never paid.
	 *
	 * @return void
	 */
	public function test_process_payment_cancels_the_order_superseded_by_the_paid_one(): void {
		$first_order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 1 );
		$this->mock_gateway->process_payment( $first_order->get_id() );

		$second_order = $this->create_checkout_order( 'dupe_hash', 'shopper@example.com' );
		$this->mock_gateway->process_payment( $second_order->get_id() );

		$superseded = wc_get_order( $second_order->get_id() );

		$this->assertEquals( OrderStatus::CANCELLED, $superseded->get_status(), 'The superseded order must not be left pending.' );
		$this->assertNull( $superseded->get_date_paid( 'edit' ) );

		$notes = wp_list_pluck( wc_get_order_notes( [ 'order_id' => $second_order->get_id() ] ), 'content' );
		$this->assertStringContainsString( (string) $first_order->get_order_number(), implode( ' ', $notes ) );
	}

	/**
	 * Core rejects a checkout with an empty cart before the gateway is reached, so clearing the cart on payment would
	 * bail the resubmission out with "your session has expired" and the guard would never run to redirect the shopper.
	 *
	 * @return void
	 */
	public function test_process_payment_leaves_the_cart_for_the_duplicate_charge_guard(): void {
		WC()->session->init();
		WC()->cart->empty_cart();

		try {
			$product = WC_Helper_Product::create_simple_product();
			$product->save();
			WC()->cart->add_to_cart( $product->get_id(), 1 );
			WC()->cart->calculate_totals();

			$order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 1 );
			$this->mock_gateway->process_payment( $order->get_id() );

			$this->assertNotNull( wc_get_order( $order->get_id() )->get_date_paid( 'edit' ) );
			$this->assertFalse( WC()->cart->is_empty(), 'The cart must survive payment or the guard becomes unreachable.' );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * The guard has to point the shopper at the order that was actually paid, not at the one they just resubmitted.
	 *
	 * @return void
	 */
	public function test_duplicate_charge_guard_redirects_to_the_paid_order(): void {
		$first_order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 1 );
		$this->mock_gateway->process_payment( $first_order->get_id() );

		$second_order = $this->create_checkout_order( 'dupe_hash', 'shopper@example.com' );

		// The shared mock stubs get_return_url to a constant, so only a real gateway can prove which order it points at.
		// The guard returns ahead of the first Stripe call, so this short-circuits without touching the API.
		$response = ( new WC_Stripe_UPE_Payment_Gateway() )->process_payment( $second_order->get_id() );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertStringContainsString( $first_order->get_order_key(), $response['redirect'] );
		$this->assertStringNotContainsString( $second_order->get_order_key(), $response['redirect'] );
	}

	/**
	 * @param string $second_cart_hash     The cart hash on the second submission.
	 * @param string $second_billing_email The billing email on the second submission.
	 * @param bool   $disable_guard        Whether to switch the guard off through its filter.
	 * @return void
	 * @dataProvider provide_process_payment_charges_a_genuinely_new_submission
	 */
	public function test_process_payment_charges_a_genuinely_new_submission( string $second_cart_hash, string $second_billing_email, bool $disable_guard ): void {
		// Both submissions are legitimate, so both must reach Stripe.
		$first_order = $this->arrange_paid_cart_gateway( 'dupe_hash', 'shopper@example.com', 2 );

		try {
			if ( $disable_guard ) {
				add_filter( 'wc_stripe_duplicate_charge_detection_window', '__return_zero' );
			}

			$this->mock_gateway->process_payment( $first_order->get_id() );

			$second_order = $this->create_checkout_order( $second_cart_hash, $second_billing_email );
			$this->mock_gateway->process_payment( $second_order->get_id() );

			$this->assertNotNull( wc_get_order( $second_order->get_id() )->get_date_paid( 'edit' ) );
		} finally {
			if ( $disable_guard ) {
				remove_filter( 'wc_stripe_duplicate_charge_detection_window', '__return_zero' );
			}
		}
	}

	/**
	 * Data provider for `test_process_payment_charges_a_genuinely_new_submission`
	 *
	 * @return array
	 */
	public function provide_process_payment_charges_a_genuinely_new_submission(): array {
		return [
			'a different cart'             => [
				'second_cart_hash'     => 'other_hash',
				'second_billing_email' => 'shopper@example.com',
				'disable_guard'        => false,
			],
			'a changed billing email'      => [
				'second_cart_hash'     => 'dupe_hash',
				'second_billing_email' => 'someone-else@example.com',
				'disable_guard'        => false,
			],
			'the guard disabled by filter' => [
				'second_cart_hash'     => 'dupe_hash',
				'second_billing_email' => 'shopper@example.com',
				'disable_guard'        => true,
			],
		];
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
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
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

		$_POST = [
			'payment_method'           => 'stripe',
			'wc-stripe-payment-method' => 'pm_mock',
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

		$_POST = [
			'payment_method'           => 'stripe_' . $payment_method,
			'wc-stripe-payment-method' => 'pm_mock',
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
		$this->assertEquals( OrderStatus::FAILED, $processed_order->get_status() );
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
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'stripe',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
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
		$this->assertEquals( OrderStatus::FAILED, $processed_order->get_status() );
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
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => '',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
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
		$this->assertEquals( OrderStatus::FAILED, $processed_order->get_status() );
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
				],
			],
			'with-confirmation-token' => [
				[
					'payment_method'               => 'some_invalid_type',
					'wc-stripe-payment-method'     => '',
					'wc-stripe-confirmation-token' => 'ctoken_mock',
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

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
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

		$final_order  = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Arranges a redirect (3DS/APM return) that will pay the given order, and returns its id.
	 *
	 * @param string $cart_hash        Cart hash to stamp on the order.
	 * @param string $payment_intent_id Intent id the return carries.
	 * @return int
	 */
	private function arrange_redirect_payment( string $cart_hash, string $payment_intent_id = 'pi_mock' ): int {
		$order = WC_Helper_Order::create_order();
		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_cart_hash( $cart_hash );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = 'pm_mock';
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
		$payment_intent_mock['last_payment_error'] = [];
		$payment_intent_mock['payment_method']     = $payment_method_mock;
		$payment_intent_mock['latest_charge']      = 'ch_mock';

		$this->mock_gateway->method( 'stripe_request' )->willReturn( $this->array_to_object( $payment_intent_mock ) );
		$this->mock_gateway->method( 'get_latest_charge_from_intent' )->willReturn(
			$this->array_to_object(
				[
					'id'                     => 'ch_mock',
					'captured'               => true,
					'status'                 => 'succeeded',
					'payment_method_details' => $payment_method_mock,
				]
			)
		);

		return $order->get_id();
	}

	/**
	 * A 3DS or redirect payment method completes on the browser return, so the marker must be recorded there too.
	 *
	 * @return void
	 */
	public function test_process_upe_redirect_payment_records_the_paid_cart_marker(): void {
		WC()->session->init();
		$order_id = $this->arrange_redirect_payment( 'redirect_hash' );

		// Buffer output so the session cookie save_data() writes doesn't trip "headers already sent" under the test harness.
		ob_start();
		$this->mock_gateway->process_upe_redirect_payment( $order_id, 'pi_mock', false );
		ob_end_clean();

		$this->assertNotNull( wc_get_order( $order_id )->get_date_paid( 'edit' ) );

		$marker = WC()->session->get( 'wc_stripe_paid_cart' );
		$this->assertIsArray( $marker );
		$this->assertEquals( $order_id, $marker['order_id'] );
		$this->assertEquals( 'redirect_hash', $marker['cart_hash'] );
	}

	/**
	 * Pay-for-order settles an existing order, so its cart is not what was charged and must not be recorded.
	 *
	 * @return void
	 */
	public function test_process_upe_redirect_payment_skips_the_marker_for_pay_for_order(): void {
		WC()->session->init();
		$order_id = $this->arrange_redirect_payment( 'redirect_hash' );

		ob_start();
		$this->mock_gateway->process_upe_redirect_payment( $order_id, 'pi_mock', false, true );
		ob_end_clean();

		$this->assertNotNull( wc_get_order( $order_id )->get_date_paid( 'edit' ) );
		$this->assertNull( WC()->session->get( 'wc_stripe_paid_cart' ) );
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

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
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
		$note          = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 2,
			]
		)[1];
		$order_helper  = WC_Stripe_Order_Helper::get_instance();

		// assert successful order processing
		$this->assertEquals( OrderStatus::PROCESSING, $success_order->get_status() );
		$this->assertEquals( 'Credit / Debit Card', $success_order->get_payment_method_title() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $success_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $success_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );

		// simulate an order getting marked as failed as if from a webhook
		$order->set_status( OrderStatus::FAILED );
		$order->save();

		// attempt to reprocess the order and confirm status is unchanged
		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );

		$final_order = wc_get_order( $order_id );

		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
	}

	/**
	 * Test locking for process redirect payment.
	 */
	public function test_process_redirect_payment_locks_order() {
		$payment_intent_id = 'pi_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$order_helper = $this->createPartialMock(
			WC_Stripe_Order_Helper::class,
			[ 'lock_order_payment', 'unlock_order_payment' ]
		);

		$order_helper->expects( $this->once() )
			->method( 'lock_order_payment' )
			->will(
				$this->returnValue( true )
			);

		$order_helper->expects( $this->once() )
			->method( 'unlock_order_payment' );

		WC_Stripe_Order_Helper::set_instance( $order_helper );

		// Expect the process to bail early.
		$this->mock_gateway->expects( $this->never() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" );

		$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );
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

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
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

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
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

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
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

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
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
		$payment_intent_mock['currency']           = $currency;
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
				'type'                                => WC_Stripe_Payment_Methods::BANCONTACT,
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

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $generated_payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
	}

	/**
	 * handle_saving_payment_method() must enforce the per-method toggle when the resolved type is non-reusable.
	 *
	 * @dataProvider provider_handle_saving_payment_method_respects_per_method_toggle
	 */
	public function test_handle_saving_payment_method_respects_per_method_toggle( string $sepa_tokens_for_ideal, bool $expected_save ) {
		$stripe_settings                          = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['sepa_tokens_for_ideal'] = $sepa_tokens_for_ideal;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		$this->mock_gateway->oc_enabled = true;

		$user_id = $this->factory()->user->create();
		$order   = WC_Helper_Order::create_order( $user_id );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_object = (object) [
			'id'         => 'pm_ideal_mock',
			'object'     => 'payment_method',
			'type'       => WC_Stripe_Payment_Methods::IDEAL,
			'customer'   => 'cus_mock',
			'sepa_debit' => (object) [
				'last4'       => '1234',
				'fingerprint' => 'fp_mock',
			],
		];

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_id' )
			->willReturn( 'cus_mock' );

		$action_calls = 0;
		$listener     = function () use ( &$action_calls ) {
			++$action_calls;
		};
		add_action( 'woocommerce_stripe_add_payment_method', $listener );

		try {
			$this->mock_gateway->handle_saving_payment_method( $order, $payment_method_object, WC_Stripe_Payment_Methods::IDEAL );
		} finally {
			remove_action( 'woocommerce_stripe_add_payment_method', $listener );
		}

		$this->assertSame( $expected_save ? 1 : 0, $action_calls );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function provider_handle_saving_payment_method_respects_per_method_toggle(): array {
		return [
			'iDEAL save toggle disabled — no token persisted' => [ 'no', false ],
			'iDEAL save toggle enabled — token persisted'     => [ 'yes', true ],
		];
	}

	/**
	 * Reusing a saved card never refreshes its wallet_type (create-time state),
	 * so a manually-saved card can't flip to a wallet brand.
	 *
	 * @dataProvider provider_handle_saving_payment_method_preserves_wallet_type
	 *
	 * @param string $initial_wallet_type wallet_type already stored on the saved token.
	 * @param string $new_wallet_type      wallet_type on the incoming payment method ('' for a plain card).
	 * @param string $expected_wallet_type wallet_type expected on the token after reuse (always the initial value).
	 */
	public function test_handle_saving_payment_method_preserves_wallet_type_on_reused_token( $initial_wallet_type, $new_wallet_type, $expected_wallet_type ) {
		$this->mock_gateway->oc_enabled = true;

		$user_id = $this->factory()->user->create();
		$order   = WC_Helper_Order::create_order( $user_id );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		// An existing saved token for the same card, branded with the initial wallet type.
		$existing = new WC_Stripe_Payment_Token_CC();
		$existing->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$existing->set_token( 'pm_existing' );
		$existing->set_card_type( 'visa' );
		$existing->set_last4( '4242' );
		$existing->set_expiry_month( '12' );
		$existing->set_expiry_year( '2030' );
		$existing->set_fingerprint( 'fp_match' );
		$existing->set_wallet_type( $initial_wallet_type );
		$existing->set_user_id( $user_id );
		$existing->save();

		$this->mock_gateway->expects( $this->any() )
			->method( 'get_stripe_customer_id' )
			->willReturn( 'cus_mock' );

		$card = [
			'exp_month'   => 12,
			'exp_year'    => 2030,
			'brand'       => 'visa',
			'last4'       => '4242',
			'fingerprint' => 'fp_match',
		];
		// A plain card carries no wallet object; a wallet payment nests its type under card.wallet.
		if ( '' !== $new_wallet_type ) {
			$card['wallet'] = (object) [ 'type' => $new_wallet_type ];
		}

		// The same card (matching fingerprint) tokenized again through the new method.
		$payment_method_object = (object) [
			'id'       => 'pm_reused',
			'object'   => 'payment_method',
			'type'     => WC_Stripe_Payment_Methods::CARD,
			'customer' => 'cus_mock',
			'card'     => (object) $card,
		];

		$this->mock_gateway->handle_saving_payment_method( $order, $payment_method_object, WC_Stripe_Payment_Methods::CARD );

		$refreshed = WC_Payment_Tokens::get( $existing->get_id() );
		$this->assertInstanceOf( WC_Stripe_Payment_Token_CC::class, $refreshed );
		// The reused token keeps its create-time wallet branding; only the Stripe id refreshes.
		$this->assertSame( $expected_wallet_type, $refreshed->get_wallet_type() );
		$this->assertSame( 'pm_reused', $refreshed->get_token() );
	}

	/**
	 * Provider: expected wallet_type always equals the initial — the incoming method's is ignored.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function provider_handle_saving_payment_method_preserves_wallet_type(): array {
		return [
			'plain card reused via apple pay stays plain'        => [ '', 'apple_pay', '' ],
			'plain card reused via google pay stays plain'       => [ '', 'google_pay', '' ],
			'plain card reused directly stays plain'             => [ '', '', '' ],
			'apple pay card reused directly stays apple'         => [ 'apple_pay', '', 'apple_pay' ],
			'apple pay card reused via google pay stays apple'   => [ 'apple_pay', 'google_pay', 'apple_pay' ],
			'apple pay card reused via apple pay stays apple'    => [ 'apple_pay', 'apple_pay', 'apple_pay' ],
			'google pay card reused via apple pay stays google'  => [ 'google_pay', 'apple_pay', 'google_pay' ],
			'google pay card reused directly stays google'       => [ 'google_pay', '', 'google_pay' ],
			'google pay card reused via google pay stays google' => [ 'google_pay', 'google_pay', 'google_pay' ],
		];
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
				'type'                                => WC_Stripe_Payment_Methods::BANCONTACT,
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

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $generated_payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
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
	 * Test that customer-cancelled redirects throw WC_Stripe_Payment_Cancelled_Exception so
	 * the order is not permanently failed.
	 *
	 * @dataProvider provider_cancellation_error_codes
	 */
	public function test_intent_error_with_requires_payment_method_throws_cancellation_exception( $error_code ) {
		$payment_intent_id = 'pi_mock';
		$order             = WC_Helper_Order::create_order();

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['status']             = WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD;
		$payment_intent_mock['last_payment_error'] = [
			'code'    => $error_code,
			'message' => 'Customer cancelled checkout on Klarna',
		];

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->willReturn( $this->array_to_object( $payment_intent_mock ) );

		$exception = null;
		try {
			$this->mock_gateway->process_order_for_confirmed_intent( $order, $payment_intent_id, false );
		} catch ( WC_Stripe_Payment_Cancelled_Exception $e ) {
			$exception = $e;
		}

		$this->assertNotNull( $exception, 'Expected WC_Stripe_Payment_Cancelled_Exception to be thrown' );
		$this->assertInstanceOf( WC_Stripe_Payment_Cancelled_Exception::class, $exception );
		$this->assertStringContainsString( 'Customer cancelled checkout on Klarna', $exception->getMessage() );
	}

	public function provider_cancellation_error_codes() {
		return [
			'customer closed popup' => [ 'payment_method_customer_decline' ],
			'session expired'       => [ 'payment_intent_payment_attempt_expired' ],
		];
	}

	/**
	 * Test that a hard payment error (non-cancellation) still throws a generic WC_Stripe_Exception.
	 *
	 * @dataProvider provider_hard_payment_error_intent_statuses
	 */
	public function test_intent_hard_error_throws_generic_exception( $intent_status, $error_code ) {
		$payment_intent_id = 'pi_mock';
		$order             = WC_Helper_Order::create_order();

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['status']             = $intent_status;
		$payment_intent_mock['last_payment_error'] = [
			'code'    => $error_code,
			'message' => 'Your card was declined.',
		];

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->willReturn( $this->array_to_object( $payment_intent_mock ) );

		$exception = null;
		try {
			$this->mock_gateway->process_order_for_confirmed_intent( $order, $payment_intent_id, false );
		} catch ( WC_Stripe_Exception $e ) {
			$exception = $e;
		}

		$this->assertNotNull( $exception, 'Expected WC_Stripe_Exception to be thrown' );
		$this->assertNotInstanceOf( WC_Stripe_Payment_Cancelled_Exception::class, $exception );
		$this->assertMatchesRegularExpression( '/not able to process this payment\./', $exception->getMessage() );
	}

	public function provider_hard_payment_error_intent_statuses() {
		return [
			'succeeded status'                                          => [ WC_Stripe_Intent_Status::SUCCEEDED, 'card_declined' ],
			'canceled status'                                           => [ WC_Stripe_Intent_Status::CANCELED, 'card_declined' ],
			'processing status'                                         => [ WC_Stripe_Intent_Status::PROCESSING, 'card_declined' ],
			'requires_payment_method + card_declined'                   => [ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, 'card_declined' ],
			'requires_payment_method + payment_method_provider decline' => [ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, 'payment_method_provider_decline' ],
			'requires_payment_method + insufficient_funds'              => [ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, 'insufficient_funds' ],
		];
	}

	/**
	 * Test that a customer-cancelled redirect (e.g. Klarna popup closed) during
	 * process_upe_redirect_payment does NOT fail the order and redirects to checkout.
	 */
	public function test_process_upe_redirect_payment_cancellation_does_not_fail_order() {
		$payment_intent_id = 'pi_mock';
		$order             = WC_Helper_Order::create_order();
		$order_id          = $order->get_id();

		list( $amount ) = $this->get_order_details( $order );

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['status']             = WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD;
		$payment_intent_mock['last_payment_error'] = [
			'code'    => 'payment_method_customer_decline',
			'message' => 'Customer cancelled checkout on Klarna',
		];

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( "payment_intents/$payment_intent_id?expand[]=payment_method" )
			->willReturn( $this->array_to_object( $payment_intent_mock ) );

		// Intercept wp_safe_redirect so that exit() is never reached, allowing assertions to run.
		$redirect_url = null;
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirect_url ) {
				$redirect_url = $location;
				throw new \RuntimeException( 'redirect_intercepted' );
			}
		);

		try {
			$this->mock_gateway->process_upe_redirect_payment( $order_id, $payment_intent_id, false );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		// Order must NOT be set to failed — the customer should be able to retry.
		$final_order = wc_get_order( $order_id );
		$this->assertNotEquals( OrderStatus::FAILED, $final_order->get_status() );

		// A 'notice' (not 'error') should be added so checkout remains retryable.
		$notices = wc_get_notices( 'notice' );
		$this->assertNotEmpty( $notices );

		// Should redirect back to the checkout URL, not to an error page.
		$this->assertSame( wc_get_checkout_url(), $redirect_url );
	}

	/**
	 * Helper: build an order linked to a Checkout Session and return the order plus the
	 * `checkout/sessions/{id}?expand[]=payment_intent` Stripe URL we expect the handler
	 * to fetch.
	 *
	 * @param string $session_id The Stripe Checkout Session id to attach.
	 * @return array{0: WC_Order, 1: string}
	 */
	private function create_order_with_checkout_session( string $session_id ): array {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		// update_stripe_checkout_session_id() only writes to in-memory meta;
		// persist it so the handler's wc_get_order() sees it.
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $session_id );
		$order->save();

		// Make sure no stale cache leaks across tests in this class.
		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $session_id );

		return [ $order, 'checkout/sessions/' . $session_id . '?expand[]=payment_intent' ];
	}

	/**
	 * Helper: install a wp_redirect filter that captures the URL and bails out via
	 * a RuntimeException so the calling test can run assertions after the handler.
	 *
	 * @param string|null &$captured Out-param: receives the captured URL when triggered.
	 */
	private function intercept_wp_redirect( ?string &$captured ): void {
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$captured ) {
				$captured = $location;
				throw new \RuntimeException( 'redirect_intercepted' );
			}
		);
	}

	/**
	 * Store Checkout Session context that matches an order total for Checkout Session processing.
	 *
	 * @param string   $session_id Stripe Checkout Session id.
	 * @param WC_Order $order WooCommerce order.
	 * @param array    $overrides Context values to override.
	 * @return void
	 */
	private function store_checkout_session_context_for_order( string $session_id, WC_Order $order, array $overrides = [] ): void {
		WC()->session->init();

		$currency = $order->get_currency();

		WC_Stripe_Checkout_Session_Context::set_context(
			$session_id,
			array_merge(
				[
					'amount'   => WC_Stripe_Helper::get_stripe_amount( (float) $order->get_total(), $currency ),
					'currency' => strtolower( $currency ),
					'order_id' => 0,
				],
				$overrides
			)
		);
	}

	/**
	 * WooCommerce mutates this protected value during guest-to-user session migration.
	 *
	 * @param string $customer_id Customer ID to set on the current WC session.
	 * @return void
	 */
	private function set_wc_session_customer_id( string $customer_id ): void {
		$reflection = new ReflectionClass( WC()->session );
		$property   = $reflection->getProperty( '_customer_id' );
		$property->setAccessible( true );
		$property->setValue( WC()->session, $customer_id );
	}

	/**
	 * Assert that a Checkout Session processing failure is surfaced through Woo checkout notices.
	 *
	 * @param string $expected_message Expected notice message.
	 * @return void
	 */
	private function assert_checkout_session_failure_notice( string $expected_message ): void {
		$notices = wc_get_notices( 'error' );

		$this->assertNotEmpty( $notices );
		$this->assertSame( $expected_message, $notices[0]['notice'] );
	}

	/**
	 * Test that the Checkout Sessions return URL carries the disambiguation params
	 * + the `wc_stripe_process_checkout_session_redirect_nonce` so the handler can
	 * recognise the customer return.
	 */
	public function test_process_payment_with_checkout_session_returns_disambiguation_url() {
		$_POST['wc_stripe_checkout_session_id'] = 'cs_test_disambig';
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$this->store_checkout_session_context_for_order( 'cs_test_disambig', $order );

		try {
			$result = $this->mock_gateway->process_payment( $order->get_id() );
		} finally {
			unset( $_POST['wc_stripe_checkout_session_id'], $_POST['payment_method'], $_POST['wc-stripe-payment-method'] );
		}

		$this->assertSame( 'success', $result['result'] );
		$this->assertNotEmpty( $result['redirect'] );

		$query        = [];
		$query_string = wp_parse_url( $result['redirect'], PHP_URL_QUERY );
		parse_str( is_string( $query_string ) ? $query_string : '', $query );

		$this->assertSame( '1', $query['wc_stripe_cs'] ?? null );
		$this->assertSame( WC_Stripe_UPE_Payment_Gateway::ID, $query['wc_payment_method'] ?? null );
		$this->assertSame( (string) $order->get_id(), $query['order_id'] ?? null );
		$this->assertNotEmpty( $query['_wpnonce'] ?? null );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], 'wc_stripe_process_checkout_session_redirect_nonce' ) );

		// The session id must have been linked to the order.
		$this->assertSame(
			'cs_test_disambig',
			WC_Stripe_Order_Helper::get_instance()->get_stripe_checkout_session_id( wc_get_order( $order->get_id() ) )
		);

		$context = WC_Stripe_Checkout_Session_Context::get_context( 'cs_test_disambig' );
		$this->assertIsArray( $context );
		$this->assertSame( $order->get_id(), $context['order_id'] );

		WC_Stripe_Checkout_Session_Context::delete_context( 'cs_test_disambig' );
	}

	/**
	 * Checkout Session payment processing requires context from the session creation request.
	 */
	public function test_process_payment_with_checkout_session_rejects_missing_context() {
		$session_id = 'cs_test_missing_context';
		wc_clear_notices();

		$original_stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings                     = $original_stripe_settings;
		$stripe_settings['adaptive_pricing'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$_POST['wc_stripe_checkout_session_id'] = $session_id;
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		try {
			$result                   = $this->mock_gateway->process_payment( $order->get_id() );
			$adaptive_pricing_setting = WC_Stripe_Helper::get_settings( null, 'adaptive_pricing' );
			$mismatch_detected        = WC_Stripe_Checkout_Session_Context::was_amount_mismatch_detected();
		} finally {
			WC_Stripe_Checkout_Session_Context::delete_context( $session_id );
			unset( $_POST['wc_stripe_checkout_session_id'], $_POST['payment_method'], $_POST['wc-stripe-payment-method'] );
			WC_Stripe_Helper::update_main_stripe_settings( $original_stripe_settings );
			delete_option( self::ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION );
		}

		$this->assertSame( 'failure', $result['result'] );
		$this->assertSame( WC_Stripe_Checkout_Session_Context::get_unavailable_message(), $result['message'] );
		$this->assertSame( 'yes', $adaptive_pricing_setting );
		$this->assertFalse( $mismatch_detected );
		$this->assert_checkout_session_failure_notice( WC_Stripe_Checkout_Session_Context::get_unavailable_message() );
		$this->assertEmpty(
			WC_Stripe_Order_Helper::get_instance()->get_stripe_checkout_session_id( wc_get_order( $order->get_id() ) )
		);

		wc_clear_notices();
	}

	/**
	 * Checkout Session payment processing rejects sessions whose amount no longer matches the order total.
	 */
	public function test_process_payment_with_checkout_session_rejects_amount_mismatch() {
		$session_id       = 'cs_test_amount_mismatch';
		$expected_message = 'The payment amount no longer matches the order total. Please refresh the page and try again.';
		wc_clear_notices();

		$original_stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings                     = $original_stripe_settings;
		$stripe_settings['adaptive_pricing'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$_POST['wc_stripe_checkout_session_id'] = $session_id;
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$this->store_checkout_session_context_for_order( $session_id, $order, [ 'amount' => 100 ] );

		try {
			$result                   = $this->mock_gateway->process_payment( $order->get_id() );
			$adaptive_pricing_setting = WC_Stripe_Helper::get_settings( null, 'adaptive_pricing' );
			$mismatch_detected        = WC_Stripe_Checkout_Session_Context::was_amount_mismatch_detected();
		} finally {
			WC_Stripe_Checkout_Session_Context::delete_context( $session_id );
			unset( $_POST['wc_stripe_checkout_session_id'], $_POST['payment_method'], $_POST['wc-stripe-payment-method'] );
			WC_Stripe_Helper::update_main_stripe_settings( $original_stripe_settings );
			delete_option( self::ADAPTIVE_PRICING_AMOUNT_MISMATCH_OPTION );
		}

		$this->assertSame( 'failure', $result['result'] );
		$this->assertSame( $expected_message, $result['message'] );
		$this->assertSame( 'no', $adaptive_pricing_setting );
		$this->assertTrue( $mismatch_detected );
		$this->assert_checkout_session_failure_notice( $expected_message );
		$this->assertEmpty(
			WC_Stripe_Order_Helper::get_instance()->get_stripe_checkout_session_id( wc_get_order( $order->get_id() ) )
		);

		wc_clear_notices();
	}

	/**
	 * Checkout Session payment processing rejects sessions created by a different browser session.
	 */
	public function test_process_payment_with_checkout_session_rejects_other_owner() {
		$session_id = 'cs_test_other_owner';
		wc_clear_notices();

		$_POST['wc_stripe_checkout_session_id'] = $session_id;
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$this->store_checkout_session_context_for_order( $session_id, $order, [ 'owner_key' => 'session:another-customer' ] );

		try {
			$result = $this->mock_gateway->process_payment( $order->get_id() );
		} finally {
			WC_Stripe_Checkout_Session_Context::delete_context( $session_id );
			unset( $_POST['wc_stripe_checkout_session_id'], $_POST['payment_method'], $_POST['wc-stripe-payment-method'] );
		}

		$this->assertSame( 'failure', $result['result'] );
		$this->assertSame( WC_Stripe_Checkout_Session_Context::get_unavailable_message(), $result['message'] );
		$this->assert_checkout_session_failure_notice( WC_Stripe_Checkout_Session_Context::get_unavailable_message() );
		$this->assertEmpty(
			WC_Stripe_Order_Helper::get_instance()->get_stripe_checkout_session_id( wc_get_order( $order->get_id() ) )
		);

		wc_clear_notices();
	}

	/**
	 * Checkout Session ownership stays valid when Woo logs a guest in while processing checkout.
	 */
	public function test_process_payment_with_checkout_session_allows_guest_session_after_checkout_login() {
		$session_id = 'cs_test_guest_to_login';

		wc_clear_notices();
		wp_set_current_user( 0 );
		WC()->customer = new WC_Customer( 0, true );

		$_POST['wc_stripe_checkout_session_id'] = $session_id;
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$this->store_checkout_session_context_for_order( $session_id, $order );
		$guest_session_id = WC()->session->get_customer_id();

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		WC()->customer = new WC_Customer( $user_id, true );
		$this->set_wc_session_customer_id( (string) $user_id );
		do_action( 'woocommerce_guest_session_to_user_id', $guest_session_id, (string) $user_id );

		try {
			$result  = $this->mock_gateway->process_payment( $order->get_id() );
			$notices = wc_get_notices( 'error' );
		} finally {
			WC_Stripe_Checkout_Session_Context::delete_context( $session_id );
			WC()->session->set( 'wc_stripe_migrated_guest_session_id', '' );
			$this->set_wc_session_customer_id( $guest_session_id );
			unset( $_POST['wc_stripe_checkout_session_id'], $_POST['payment_method'], $_POST['wc-stripe-payment-method'] );
			wp_set_current_user( 0 );
			wc_clear_notices();
		}

		$this->assertSame( 'success', $result['result'] );
		$this->assertEmpty( $notices );
		$this->assertSame(
			$session_id,
			WC_Stripe_Order_Helper::get_instance()->get_stripe_checkout_session_id( wc_get_order( $order->get_id() ) )
		);
	}

	/**
	 * Test that the early-return branch (order already paid) also returns the
	 * disambiguation URL so the handler can short-circuit cleanly on a refresh.
	 */
	public function test_process_payment_with_checkout_session_returns_disambiguation_url_for_already_paid_order() {
		$_POST['wc_stripe_checkout_session_id'] = 'cs_test_already_paid';
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PROCESSING );
		$order->save();

		try {
			$result = $this->mock_gateway->process_payment( $order->get_id() );
		} finally {
			unset( $_POST['wc_stripe_checkout_session_id'], $_POST['payment_method'], $_POST['wc-stripe-payment-method'] );
		}

		$this->assertSame( 'success', $result['result'] );
		$query        = [];
		$query_string = wp_parse_url( $result['redirect'], PHP_URL_QUERY );
		parse_str( is_string( $query_string ) ? $query_string : '', $query );
		$this->assertSame( '1', $query['wc_stripe_cs'] ?? null );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'] ?? '', 'wc_stripe_process_checkout_session_redirect_nonce' ) );
	}

	/**
	 * Saving through the Adaptive Pricing / Checkout Sessions flow must persist the save-payment-method
	 * flag on the order without any server-side session update: saving is requested client-side via
	 * `checkout.confirm()`, and the Checkout Session update API does not accept `payment_method_options`.
	 *
	 * Part of STRIPE-1205.
	 *
	 * @dataProvider provide_checkout_session_save_payment_method_types
	 *
	 * @param string $payment_method_type The UPE payment method type selected at checkout.
	 */
	public function test_process_payment_with_checkout_session_persists_save_flag_without_session_update( string $payment_method_type ) {
		$session_id = 'cs_test_save_' . $payment_method_type;

		$_POST['wc_stripe_checkout_session_id'] = $session_id;
		$_POST['payment_method']                = WC_Stripe_UPE_Payment_Gateway::ID;
		$_POST['wc-stripe-payment-method']      = WC_Stripe_UPE_Payment_Gateway::ID;
		// Optimized Checkout reports 'card' as the gateway type; the real method is sent in `wc_stripe_selected_upe_payment_type`.
		$_POST['wc_stripe_selected_upe_payment_type'] = $payment_method_type;
		$_POST['wc-stripe-new-payment-method']        = 'true';

		// Saved cards must be enabled for the save-payment-method checkbox to take effect.
		$this->mock_gateway->saved_cards = true;

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_status( OrderStatus::PENDING );
		$order->save();

		$this->store_checkout_session_context_for_order( $session_id, $order );

		$session_update_called = false;
		$pre_http_filter       = function ( $return_value, $parsed_args, $url ) use ( $session_id, &$session_update_called ) {
			if ( WC_Stripe_API::ENDPOINT . 'checkout/sessions/' . $session_id === $url ) {
				$session_update_called = true;
			}
			return $return_value;
		};
		add_filter( 'pre_http_request', $pre_http_filter, 10, 3 );

		try {
			$result = $this->mock_gateway->process_payment( $order->get_id() );
		} finally {
			remove_filter( 'pre_http_request', $pre_http_filter );
			WC_Stripe_Checkout_Session_Context::delete_context( $session_id );
			unset(
				$_POST['wc_stripe_checkout_session_id'],
				$_POST['payment_method'],
				$_POST['wc-stripe-payment-method'],
				$_POST['wc_stripe_selected_upe_payment_type'],
				$_POST['wc-stripe-new-payment-method']
			);
		}

		$this->assertSame( 'success', $result['result'] );
		$this->assertFalse( $session_update_called, 'Saving a payment method must not trigger a checkout session update.' );

		// The save flag must be persisted so the webhook can save the payment method (or its generated SEPA mandate).
		$this->assertTrue(
			WC_Stripe_Order_Helper::get_instance()->get_should_save_stripe_payment_method( wc_get_order( $order->get_id() ) )
		);
	}

	/**
	 * Provides payment method types for the checkout session save-payment-method test.
	 *
	 * @return array[]
	 */
	public function provide_checkout_session_save_payment_method_types(): array {
		return [
			'redirect APM tokenized as SEPA (bancontact)' => [ WC_Stripe_Payment_Methods::BANCONTACT ],
			'method saved under its own type (card)'      => [ WC_Stripe_Payment_Methods::CARD ],
		];
	}

	/**
	 * Test the success short-circuit: an order already in `processing` status must
	 * not trigger any Stripe API call and must redirect to the clean order-received
	 * URL (stripping the disambiguation query args from the address bar).
	 */
	public function test_process_checkout_session_redirect_short_circuits_for_already_processed_order() {
		[ $order ] = $this->create_order_with_checkout_session( 'cs_test_short_circuit' );
		$order->set_status( OrderStatus::PROCESSING );
		$order->save();

		// stripe_request must NOT be called.
		$this->mock_gateway->expects( $this->never() )->method( 'stripe_request' );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$this->assertSame( self::MOCK_RETURN_URL_AFTER_REDIRECT, $captured );
		$this->assertEquals( OrderStatus::PROCESSING, wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * Test the replay-protection short-circuit: a second invocation must be a no-op
	 * (no API call, no notice, no status change) and must redirect to the clean
	 * order-received URL.
	 */
	public function test_process_checkout_session_redirect_short_circuits_when_already_processed() {
		[ $order ] = $this->create_order_with_checkout_session( 'cs_test_replay' );

		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_redirect_processed( $order, true );
		$order->save();

		// stripe_request must NOT be called.
		$this->mock_gateway->expects( $this->never() )->method( 'stripe_request' );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		// No notices, no status change.
		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'notice' ) );
		$this->assertEquals( OrderStatus::PENDING, wc_get_order( $order->get_id() )->get_status() );
		$this->assertSame( self::MOCK_RETURN_URL_AFTER_REDIRECT, $captured );
	}

	/**
	 * Test the success path: a `complete` Stripe Checkout Session must mark the
	 * replay flag, leave the order pending (the webhook will finalise it), and
	 * redirect to the clean order-received URL — stripping the disambiguation
	 * query args from the address bar.
	 *
	 * @dataProvider provider_checkout_session_complete_states
	 *
	 * @param string $payment_status `paid` (sync) or `unpaid` (async — Boleto/voucher).
	 */
	public function test_process_checkout_session_redirect_success_redirects_to_clean_url( string $payment_status ) {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_success_' . $payment_status );

		$session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_success_' . $payment_status,
				'status'         => 'complete',
				'payment_status' => $payment_status,
				'payment_intent' => [
					'id'                 => 'pi_mock_success',
					'last_payment_error' => null,
				],
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$this->assertSame( self::MOCK_RETURN_URL_AFTER_REDIRECT, $captured );

		$final = wc_get_order( $order->get_id() );
		$this->assertEquals( OrderStatus::PENDING, $final->get_status() );
		$this->assertTrue( (bool) WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_redirect_processed( $final ) );
		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'notice' ) );
	}

	public function provider_checkout_session_complete_states(): array {
		return [
			'paid (sync)'             => [ 'paid' ],
			'unpaid (async / Boleto)' => [ 'unpaid' ],
		];
	}

	/**
	 * Test the recoverable-cancel path: an `open` session with a cancellation-coded
	 * intent error must add a 'notice'-type notice, leave the order pending, and
	 * redirect to the checkout URL.
	 *
	 * @dataProvider provider_checkout_session_cancellation_codes
	 *
	 * @param string $code Stripe error code that represents a customer-initiated cancel.
	 */
	public function test_process_checkout_session_redirect_cancel_redirects_to_checkout( string $code ) {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_cancel_' . $code );

		$session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_cancel_' . $code,
				'status'         => 'open',
				'payment_intent' => [
					'id'                 => 'pi_mock_cancel',
					'last_payment_error' => [
						'code'    => $code,
						'message' => 'Customer cancelled on the redirect provider',
					],
				],
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$final = wc_get_order( $order->get_id() );
		$this->assertNotEquals( OrderStatus::FAILED, $final->get_status(), 'Cancel must NOT fail the order — customer should be able to retry.' );
		// Replay flag must NOT be set on cancel: the customer may retry on the same
		// order with a new Checkout Session and that return must still be processed.
		$this->assertFalse( (bool) WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_redirect_processed( $final ) );

		$notices = wc_get_notices( 'notice' );
		$this->assertNotEmpty( $notices );

		$this->assertSame( wc_get_checkout_url(), $captured );
	}

	public function provider_checkout_session_cancellation_codes(): array {
		return [
			'customer decline'        => [ 'payment_method_customer_decline' ],
			'payment attempt expired' => [ 'payment_intent_payment_attempt_expired' ],
		];
	}

	/**
	 * Test the hard-failure path: an `open` session with a non-cancellation intent
	 * error must move the order to FAILED, add an 'error' notice with the Stripe
	 * message, and redirect to the checkout URL.
	 */
	public function test_process_checkout_session_redirect_hard_failure_marks_order_failed() {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_hard_fail' );

		$session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_hard_fail',
				'status'         => 'open',
				'payment_intent' => [
					'id'                 => 'pi_mock_hard_fail',
					'last_payment_error' => [
						'code'    => 'card_declined',
						'message' => 'Your card was declined.',
					],
				],
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$final = wc_get_order( $order->get_id() );
		$this->assertEquals( OrderStatus::FAILED, $final->get_status() );
		// Replay flag must NOT be set on hard failure: the customer may retry on the
		// same order with a new Checkout Session and that return must still be processed.
		$this->assertFalse( (bool) WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_redirect_processed( $final ) );

		$errors = wc_get_notices( 'error' );
		$this->assertNotEmpty( $errors );

		$this->assertSame( wc_get_checkout_url(), $captured );
	}

	/**
	 * Test the abandon-without-error path: an `open` session with no intent error
	 * (customer simply navigated back) is treated like a recoverable cancel.
	 */
	public function test_process_checkout_session_redirect_open_without_error_redirects_to_checkout() {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_abandon' );

		$session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_abandon',
				'status'         => 'open',
				'payment_intent' => null,
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$final = wc_get_order( $order->get_id() );
		$this->assertNotEquals( OrderStatus::FAILED, $final->get_status() );
		$this->assertNotEmpty( wc_get_notices( 'notice' ) );
		$this->assertSame( wc_get_checkout_url(), $captured );
	}

	/**
	 * Test the expired-session path: an `expired` session is treated like a cancel.
	 */
	public function test_process_checkout_session_redirect_expired_session_redirects_to_checkout() {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_expired' );

		$session_mock = $this->array_to_object(
			[
				'id'     => 'cs_test_expired',
				'status' => 'expired',
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$final = wc_get_order( $order->get_id() );
		$this->assertNotEquals( OrderStatus::FAILED, $final->get_status() );
		$this->assertNotEmpty( wc_get_notices( 'notice' ) );
		$this->assertSame( wc_get_checkout_url(), $captured );
	}

	/**
	 * Regression: an `expired` session that carries a stale `last_payment_error`
	 * with a hard-failure code (e.g. the customer tried a declined card earlier
	 * in the flow and then let the session expire) must NOT be treated as a
	 * hard failure. The order stays retryable; the customer gets a cancel notice.
	 */
	public function test_process_checkout_session_redirect_expired_with_stale_error_stays_retryable() {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_expired_with_error' );

		$session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_expired_with_error',
				'status'         => 'expired',
				'payment_intent' => [
					'id'                 => 'pi_mock_expired_with_error',
					'last_payment_error' => [
						'code'    => 'card_declined',
						'message' => 'Your card was declined.',
					],
				],
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$final = wc_get_order( $order->get_id() );
		$this->assertNotEquals( OrderStatus::FAILED, $final->get_status() );
		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertNotEmpty( wc_get_notices( 'notice' ) );
		$this->assertSame( wc_get_checkout_url(), $captured );
	}

	/**
	 * Test the pay-for-order variant: cancel must redirect to the order pay URL,
	 * not the generic checkout URL.
	 */
	public function test_process_checkout_session_redirect_pay_for_order_redirects_to_order_pay_url() {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_pay_for_order' );

		$session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_pay_for_order',
				'status'         => 'open',
				'payment_intent' => [
					'id'                 => 'pi_mock_pfo',
					'last_payment_error' => [
						'code'    => 'payment_method_customer_decline',
						'message' => 'Customer cancelled',
					],
				],
			]
		);

		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->willReturn( $session_mock );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id(), true );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$this->assertSame( $order->get_checkout_payment_url(), $captured );
	}

	/**
	 * Test the cancel-then-retry flow on the same order: after a cancelled return,
	 * the customer retries with a new Checkout Session on the same order. The second
	 * return (a success) must still be processed — i.e. the cancel path must NOT
	 * have armed the order-scoped replay flag, otherwise the retry's success return
	 * would be silently short-circuited.
	 */
	public function test_process_checkout_session_redirect_cancel_then_retry_success_is_processed() {
		[ $order, $cancel_request_url ] = $this->create_order_with_checkout_session( 'cs_test_retry_cancel' );

		// First return: the customer cancelled on the redirect provider.
		$cancel_session_mock = $this->array_to_object(
			[
				'id'             => 'cs_test_retry_cancel',
				'status'         => 'open',
				'payment_intent' => [
					'id'                 => 'pi_mock_retry_cancel',
					'last_payment_error' => [
						'code'    => 'payment_method_customer_decline',
						'message' => 'Customer cancelled on the redirect provider',
					],
				],
			]
		);

		// Second return: customer retried with a new Checkout Session on the same
		// order and paid successfully.
		$success_session_id   = 'cs_test_retry_success';
		$success_request_url  = 'checkout/sessions/' . $success_session_id . '?expand[]=payment_intent';
		$success_session_mock = $this->array_to_object(
			[
				'id'             => $success_session_id,
				'status'         => 'complete',
				'payment_status' => 'paid',
				'payment_intent' => [
					'id'                 => 'pi_mock_retry_success',
					'last_payment_error' => null,
				],
			]
		);

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ $cancel_request_url ],
				[ $success_request_url ]
			)
			->willReturnOnConsecutiveCalls( $cancel_session_mock, $success_session_mock );

		// ----- First return: cancel -----
		$captured_cancel = null;
		$this->intercept_wp_redirect( $captured_cancel );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered on cancel' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		// Cancel must bounce to /checkout and leave the order retryable.
		$this->assertSame( wc_get_checkout_url(), $captured_cancel );
		$after_cancel = wc_get_order( $order->get_id() );
		$this->assertNotEquals( OrderStatus::FAILED, $after_cancel->get_status() );
		$this->assertFalse( (bool) WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_redirect_processed( $after_cancel ) );

		// Clear the cancel notice so we can assert cleanly on the retry.
		wc_clear_notices();

		// Simulate the customer retrying on the same order with a new Checkout Session.
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $after_cancel, $success_session_id );
		$after_cancel->save();
		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $success_session_id );

		// ----- Second return: success -----
		$captured_success = null;
		$this->intercept_wp_redirect( $captured_success );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered on retry success' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		// The retry's success return must have been processed — not short-circuited.
		$this->assertSame( self::MOCK_RETURN_URL_AFTER_REDIRECT, $captured_success );
		$final = wc_get_order( $order->get_id() );
		// Success path arms the replay flag so a refresh is a no-op.
		$this->assertTrue( (bool) WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_redirect_processed( $final ) );
	}

	/**
	 * Test that a Stripe API failure (get_checkout_session_from_order returns null)
	 * leaves the order untouched and redirects to the clean order-received URL —
	 * the webhook will take over, and the redirect prevents an API-call loop on refresh.
	 */
	public function test_process_checkout_session_redirect_api_failure_redirects_to_clean_url() {
		[ $order, $request_url ] = $this->create_order_with_checkout_session( 'cs_test_api_fail' );

		// stripe_request throws — get_checkout_session_from_order should catch and return null.
		$this->mock_gateway->expects( $this->once() )
			->method( 'stripe_request' )
			->with( $request_url )
			->will( $this->throwException( new WC_Stripe_Exception( 'boom', 'API down' ) ) );

		$captured = null;
		$this->intercept_wp_redirect( $captured );

		try {
			$this->mock_gateway->process_checkout_session_redirect( $order->get_id() );
			$this->fail( 'Expected redirect to be triggered' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		$this->assertSame( self::MOCK_RETURN_URL_AFTER_REDIRECT, $captured );

		$final = wc_get_order( $order->get_id() );
		$this->assertEquals( OrderStatus::PENDING, $final->get_status() );
		// Replay flag must NOT be set so a later request (e.g. with cache primed) can still process.
		$this->assertFalse( (bool) WC_Stripe_Order_Helper::get_instance()->get_stripe_upe_redirect_processed( $final ) );
	}

	/**
	 * Test that an unknown order id is a safe no-op.
	 */
	public function test_process_checkout_session_redirect_handles_missing_order() {
		$this->mock_gateway->expects( $this->never() )->method( 'stripe_request' );

		// Should not throw, should not redirect.
		$this->mock_gateway->process_checkout_session_redirect( 999999999 );
		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'notice' ) );
	}

	/**
	 * Test that the dispatcher is a no-op when guard conditions aren't met.
	 *
	 * These guards (missing `wc_stripe_cs`, wrong `wc_payment_method`, invalid
	 * nonce) all return before `is_order_received_page()` is evaluated, making
	 * them fully testable in PHPUnit without WordPress query-state setup.
	 *
	 * @dataProvider provider_dispatcher_invalid_requests
	 *
	 * @param array $get_params The $_GET parameters to set for the request.
	 */
	public function test_maybe_process_checkout_session_redirect_is_noop_for_invalid_request( array $get_params ) {
		$this->mock_gateway->expects( $this->never() )->method( 'stripe_request' );

		$_GET = array_merge( $_GET, $get_params );

		try {
			$this->mock_gateway->maybe_process_checkout_session_redirect();
		} finally {
			foreach ( array_keys( $get_params ) as $key ) {
				unset( $_GET[ $key ] );
			}
		}

		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'notice' ) );
	}

	/**
	 * Data provider for dispatcher guard tests.
	 *
	 * @return array
	 */
	public function provider_dispatcher_invalid_requests(): array {
		return [
			'missing wc_stripe_cs'   => [ [] ],
			'wrong payment method'   => [
				[
					'wc_stripe_cs'      => '1',
					'wc_payment_method' => 'paypal',
				],
			],
			'invalid nonce'          => [
				[
					'wc_stripe_cs'      => '1',
					'wc_payment_method' => WC_Stripe_UPE_Payment_Gateway::ID,
					'_wpnonce'          => 'invalid_nonce_value',
				],
			],
			'missing nonce entirely' => [
				[
					'wc_stripe_cs'      => '1',
					'wc_payment_method' => WC_Stripe_UPE_Payment_Gateway::ID,
				],
			],
		];
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
		$this->assertEquals( OrderStatus::ON_HOLD, $test_order->get_status() );

		// Test charge succeeds.
		$charge_mock['captured'] = true;
		$charge_mock['id']       = 'ch_mock_2';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'yes', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( OrderStatus::PROCESSING, $test_order->get_status() );

		// Test charge pending.
		$charge_mock['status'] = 'pending';
		$charge_mock['id']     = 'ch_mock_3';
		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );
		$test_order = wc_get_order( $order_id );

		$this->assertEquals( 'yes', $test_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $test_order->get_transaction_id() );
		$this->assertEquals( OrderStatus::ON_HOLD, $test_order->get_status() );

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
	 * Test that the wc_gateway_stripe_process_payment_charge action is triggered when process_response() is called for synchronous payment paths.
	 */
	public function test_process_response_triggers_wc_gateway_stripe_process_payment_charge_action() {
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
		$charge_mock['captured']               = true;
		$charge_mock['status']                 = 'succeeded';
		$charge_mock['id']                     = 'ch_mock_success';

		$mock_action_process_payment = new MockAction();
		add_action(
			'wc_gateway_stripe_process_payment_charge',
			[ &$mock_action_process_payment, 'action' ]
		);

		$this->mock_gateway->process_response( $this->array_to_object( $charge_mock ), wc_get_order( $order_id ) );

		$final_order = wc_get_order( $order_id );

		// Test the action was called only once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );

		// Test the order was processed successfully.
		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( 'yes', $final_order->get_meta( '_stripe_charge_captured', true ) );
		$this->assertEquals( $charge_mock['id'], $final_order->get_transaction_id() );
	}

	/**
	 * TESTS FOR SAVED PAYMENTS.
	 */

	/**
	 * Test basic checkout with saved payment method.
	 */
	public function test_process_payment_with_saved_method_returns_valid_response() {
		$token = $this->set_postvars_for_saved_payment_method();

		$_POST['payment_method']           = 'stripe';
		$_POST['wc-stripe-payment-method'] = 'pm_mock';

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
					function ( $passed_order ) use ( $order ) {
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

		$response     = $this->mock_gateway->process_payment( $order_id );
		$final_order  = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Test SCA 3DS flow with saved payment method.
	 */
	public function test_sca_checkout_with_saved_payment_method_redirects_client() {
		$token = $this->set_postvars_for_saved_payment_method();

		$_POST['payment_method']           = 'stripe';
		$_POST['wc-stripe-payment-method'] = 'pm_mock';

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
				'status'         => WC_Stripe_Intent_Status::REQUIRES_ACTION,
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
					function ( $passed_order ) use ( $order ) {
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
		$order_helper  = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( OrderStatus::PENDING, $final_order->get_status() ); // Order status should be pending until 3DS is completed.
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:$order_id:$client_secret/", $response['redirect'] );
	}

	/**
	 * Test error state with fatal test during checkout with saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_non_retryable_error_throws_exception() {
		$token = $this->set_postvars_for_saved_payment_method();

		$_POST['payment_method']           = 'stripe';
		$_POST['wc-stripe-payment-method'] = 'pm_mock';

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
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$response     = $this->mock_gateway->process_payment( $order_id );
		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'failure', $response['result'] );
		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
	}

	/**
	 * Tests retryable error during checkout using saved payment method.
	 */
	public function test_checkout_with_saved_payment_method_retries_error_when_possible() {
		$token = $this->set_postvars_for_saved_payment_method();

		$_POST['payment_method']           = 'stripe';
		$_POST['wc-stripe-payment-method'] = 'pm_mock';

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
					function ( $passed_order ) use ( $order ) {
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

		$response     = $this->mock_gateway->process_payment( $order_id );
		$final_order  = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'success', $response['result'] );
		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Tests that retryable error fails after 6 attempts.
	 */
	public function test_checkout_with_saved_payment_method_fails_after_six_attempts() {
		$token = $this->set_postvars_for_saved_payment_method();

		$_POST['payment_method']           = 'stripe';
		$_POST['wc-stripe-payment-method'] = 'pm_mock';

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
					function ( $passed_order ) use ( $order ) {
						return $order->get_id() === $passed_order->get_id();
					}
				)
			);

		$response    = $this->mock_gateway->process_payment( $order_id );
		$final_order = wc_get_order( $order_id );

		$this->assertEquals( 'failure', $response['result'] );
		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
		$this->assertEquals( '', WC_Stripe_Order_Helper::get_instance()->get_stripe_customer_id( $final_order ) );
	}

	/**
	 * TESTS FOR SUBSCRIPTIONS.
	 */

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
			'wc_gateway_stripe_process_payment_charge',
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

		$this->assertEquals( OrderStatus::PROCESSING, $final_order->get_status() );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
		// Assert: Our hook was called once.
		$this->assertEquals( 1, $mock_action_process_payment->get_call_count() );
		// Assert: Only our hook was called.
		$this->assertEquals( [ 'wc_gateway_stripe_process_payment_charge' ], $mock_action_process_payment->get_tags() );
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

		$final_order   = wc_get_order( $order_id );
		$note_contents = array_map(
			static function ( $note ) {
				return $note->content;
			},
			wc_get_order_notes( [ 'order_id' => $order_id ] )
		);

		$this->assertEquals( OrderStatus::FAILED, $final_order->get_status() );
		$this->assertEquals( 'ch_mock', $final_order->get_transaction_id() );
		$this->assertNotEmpty(
			preg_grep( '/pending/i', $note_contents ),
			'Failed asserting that an order note mentions the pending payment. Notes: ' . implode( ' | ', $note_contents )
		);
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

		list( $amount, $description, $metadata, $currency ) = $this->get_order_details( $order );
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$payment_method_mock                     = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		$payment_method_mock['id']               = $payment_method_id;
		$payment_method_mock['customer']         = $customer_id;
		$payment_method_mock['card']['exp_year'] = intval( gmdate( 'Y' ) ) + 1;

		$payment_intent_mock                       = self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE;
		$payment_intent_mock['id']                 = $payment_intent_id;
		$payment_intent_mock['amount']             = $amount;
		$payment_intent_mock['currency']           = $currency;
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

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( 'Credit / Debit Card', $final_order->get_payment_method_title() );
		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertEquals( $payment_intent_id, $order_helper->get_stripe_intent_id( $final_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $final_order ) );
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

		$final_order  = wc_get_order( $order_id );
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$this->assertEquals( $payment_method_id, $order_helper->get_stripe_source_id( $final_order ) );
		$this->assertEquals( $customer_id, $order_helper->get_stripe_customer_id( $final_order ) );
		$this->assertTrue( (bool) $order_helper->get_stripe_upe_redirect_processed( $final_order ) );
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

		list( $amount ) = $this->get_order_details( $order );

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
				'status'               => WC_Stripe_Intent_Status::REQUIRES_ACTION,
				'amount'               => $amount,
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$mock_payment_method = (object) self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;

		$_POST = [
			'payment_method'           => 'stripe',
			'wc-stripe-payment-method' => 'pm_mock',
		];

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_intent_from_order' )
			->willReturn( $mock_intent );

		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ 'payment_methods/pm_mock' ],
				[ 'payment_intents/' . $mock_intent->id ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_payment_method,
				$mock_intent
			);

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );
		$this->assertMatchesRegularExpression( "/#wc-stripe-confirm-pi:{$order_id}:{$mock_intent->client_secret}/", $response['redirect'] );
	}

	/**
	 * Test that a successful payment intent is reused instead of creating a new one.
	 * This prevents duplicate charges when the shopper retries a payment after
	 * a successful charge but failed order completion.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_reuses_successful_payment_intent() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();

		$mock_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_mock',
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
				'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$mock_payment_method = (object) self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;

		$_POST = [
			'payment_method'           => 'stripe',
			'wc-stripe-payment-method' => 'pm_mock',
		];

		// Mock that we find an existing successful intent on the order
		$this->mock_gateway
			->expects( $this->exactly( 1 ) )
			->method( 'get_intent_from_order' )
			->willReturn( $mock_intent );

		// Mock both the payment method retrieval and payment intent retrieval
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ 'payment_methods/pm_mock' ],
				[ "payment_intents/{$mock_intent->id}", null, null, 'POST' ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_payment_method,
				$mock_intent
			);

		// We should never try to create a new intent since we have a successful one
		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		// Verify the response indicates success
		$this->assertEquals( 'success', $response['result'] );
	}

	/**
	 * Test that a failed payment intent is not reused and a new one is created instead.
	 *
	 * @return void
	 * @throws Exception If test fails.
	 */
	public function test_process_payment_creates_new_intent_when_existing_intent_failed() {
		$customer_id = 'cus_mock';
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();

		$mock_payment_method = (object) self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE;
		list( $amount )      = $this->get_order_details( $order );

		// Create a mock failed payment intent that would be attached to the order
		$mock_failed_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_mock_failed',
				'payment_method'       => 'pm_mock',
				'status'               => WC_Stripe_Intent_Status::CANCELED,
				'payment_method_types' => [ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ],
				'charges'              => (object) [
					'data' => [],
				],
				'amount'               => $amount,
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Create a mock successful payment intent that will be created
		$mock_success_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_mock_new',
				'payment_method'       => 'pm_mock',
				'status'               => WC_Stripe_Intent_Status::SUCCEEDED,
				'payment_method_types' => [ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ],
				'charges'              => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		// Set the appropriate POST data for the payment request
		$_POST = [
			'payment_method'           => 'stripe',
			'wc-stripe-payment-method' => 'pm_mock',
		];

		// Save the failed intent ID to the order
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $mock_failed_intent->id );
		$order->save();

		// Mock that we find an existing failed intent on the order
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_intent_from_order' )
			->willReturn( $mock_failed_intent );

		// Mock both the payment method retrieval and payment intent retrieval
		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'stripe_request' )
			->withConsecutive(
				[ 'payment_methods/pm_mock' ],
				[ "payment_intents/{$mock_failed_intent->id}", null, null, 'POST' ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_payment_method,
				$mock_failed_intent
			);

		// We should create a new intent since the existing one failed
		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_success_intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$response = $this->mock_gateway->process_payment( $order_id );

		// Verify the response indicates success
		$this->assertEquals( 'success', $response['result'] );
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

		$_POST['payment_method']           = 'stripe';
		$_POST['wc-stripe-payment-method'] = 'pm_mock';

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
			'id'       => 'ch_mock',
			'captured' => true,
			'status'   => 'succeeded',
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
					function ( $passed_order ) use ( $order ) {
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
		$this->assertEquals( $payment_method_id, WC_Stripe_Order_Helper::get_instance()->get_stripe_source_id( $final_order ) );
		$this->assertMatchesRegularExpression( '/Charge ID: ch_mock/', $note->content );
	}

	/**
	 * Data provider for {@see test_process_payment_with_express_checkout_payment_method()}.
	 *
	 * @return array
	 */
	public function provide_test_process_payment_with_express_checkout_payment_method(): array {
		return [
			'Amazon Pay with OC enabled'  => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::AMAZON_PAY,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => true,
			],
			'Amazon Pay with OC disabled' => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::AMAZON_PAY,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => false,
			],
			'Apple Pay with OC enabled'   => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::APPLE_PAY,
				'payment_method_id'          => 'pm_mock321',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => true,
			],
			'Apple Pay with OC disabled'  => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::APPLE_PAY,
				'payment_method_id'          => 'pm_mock321',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => false,
			],
			'Google Pay with OC enabled'  => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::GOOGLE_PAY,
				'payment_method_id'          => 'pm_mock123',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => true,
			],
			'Google Pay with OC disabled' => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::GOOGLE_PAY,
				'payment_method_id'          => 'pm_mock123',
				'confirmation_token_id'      => '',
				'optimized_checkout_enabled' => false,
			],
			'Link with OC enabled'        => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::LINK,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => true,
			],
			'Link with OC disabled'       => [
				'express_payment_method'     => WC_Stripe_Payment_Methods::LINK,
				'payment_method_id'          => '',
				'confirmation_token_id'      => 'ctoken_mock789',
				'optimized_checkout_enabled' => false,
			],
		];
	}

	/**
	 * Test for `process_payment` with an express checkout payment method.
	 *
	 * @param string $express_payment_method     The express payment method.
	 * @param string $payment_method_id          The payment method ID.
	 * @param string $confirmation_token_id      The confirmation token ID.
	 * @param bool   $optimized_checkout_enabled Whether optimized checkout is enabled.
	 * @return void
	 *
	 * @dataProvider provide_test_process_payment_with_express_checkout_payment_method
	 */
	public function test_process_payment_with_express_checkout_payment_method( string $express_payment_method, string $payment_method_id, string $confirmation_token_id, bool $optimized_checkout_enabled ): void {
		$order         = WC_Helper_Order::create_order();
		$order_id      = $order->get_id();
		$customer_id   = 'cus_mock1234567890';
		$stripe_amount = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );

		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-confirmation-token'] = $confirmation_token_id;
		$_POST['wc-stripe-payment-method']     = $payment_method_id;
		$_POST['express_payment_type']         = $express_payment_method;

		$this->mock_gateway->oc_enabled = $optimized_checkout_enabled;

		$payment_method_pre_http_filter = null;
		if ( '' !== $payment_method_id ) {
			$payment_method_pre_http_filter = function ( $result, $args, $url ) use ( $payment_method_id, $express_payment_method ) {
				if ( 'payment_methods/' . $payment_method_id === $url ) {
					return $this->get_mock_express_checkout_payment_method( $payment_method_id, $express_payment_method );
				}
				return $result;
			};
			add_filter( 'pre_http_request', $payment_method_pre_http_filter, 10, 3 );
		}

		$mock_intent = (object) [
			'id'                   => 'pi_mock1234567890',
			'object'               => 'payment_intent',
			'amount'               => $stripe_amount,
			'amount_received'      => $stripe_amount,
			'currency'             => strtolower( $order->get_currency() ),
			'customer'             => 'cus_mock1234567890',
			'description'          => 'Test Store - Order ' . $order_id,
			'latest_charge'        => 'ch_mock1234567890',
			'payment_method'       => '' === $payment_method_id ? 'pm_mock1234' : $payment_method_id,
			'payment_method_types' => [ WC_Stripe_Payment_Methods::AMAZON_PAY === $express_payment_method ? WC_Stripe_Payment_Methods::AMAZON_PAY : WC_Stripe_Payment_Methods::CARD ],
			'status'               => 'succeeded',
			'created'              => time(),
		];

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->with(
				$this->callback(
					function ( $payment_information ) use ( $payment_method_id, $express_payment_method, $confirmation_token_id, $order_id ) {
						if ( '' === $confirmation_token_id ) {
							$this->assertArrayNotHasKey( 'confirmation_token', $payment_information );
						} else {
							$this->assertArrayHasKey( 'confirmation_token', $payment_information );
							$this->assertEquals( $confirmation_token_id, $payment_information['confirmation_token'] );
						}
						if ( '' === $payment_method_id ) {
							$this->assertArrayNotHasKey( 'payment_method', $payment_information );
						} else {
							$this->assertEquals( $payment_method_id, $payment_information['payment_method'] );
						}
						$this->assertInstanceOf( WC_Order::class, $payment_information['order'] );
						$this->assertEquals( $order_id, $payment_information['order']->get_id() );
						$expected_selected_payment_type = WC_Stripe_Payment_Methods::AMAZON_PAY === $express_payment_method ? WC_Stripe_Payment_Methods::AMAZON_PAY : WC_Stripe_Payment_Methods::CARD;
						$this->assertEquals( $expected_selected_payment_type, $payment_information['selected_payment_type'] );
						$this->assertIsArray( $payment_information['payment_method_types'] );
						$this->assertContains( $expected_selected_payment_type, $payment_information['payment_method_types'] );

						return true;
					}
				)
			)
			->willReturn( $mock_intent );

		$mock_charge = (object) [
			'id'       => 'ch_mock1234567890',
			'captured' => true,
			'status'   => 'succeeded',
		];

		$this->mock_gateway
			->expects( $this->exactly( 2 ) )
			->method( 'get_latest_charge_from_intent' )
			->willReturn( $mock_charge );

		$response = $this->mock_gateway->process_payment( $order_id );

		if ( null !== $payment_method_pre_http_filter ) {
			remove_filter( 'pre_http_request', $payment_method_pre_http_filter, 10 );
		}

		$this->assertIsArray( $response );
		$this->assertEquals( 'success', $response['result'] );
	}

	/**
	 * Paid statuses for which WC core's payment_complete() skips set_transaction_id().
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_paid_statuses_for_confirmation_token_backfill(): array {
		return [
			'processing' => [ OrderStatus::PROCESSING ],
			'completed'  => [ OrderStatus::COMPLETED ],
		];
	}

	/**
	 * Tests that the confirmation token flow persists the charge ID as the transaction ID even
	 * when the order is already in a paid status, where payment_complete() would otherwise skip it.
	 *
	 * @dataProvider provide_paid_statuses_for_confirmation_token_backfill
	 */
	public function test_process_payment_with_confirmation_token_backfills_missing_transaction_id( string $paid_status ) {
		$order = WC_Helper_Order::create_order();
		// An already-paid status makes WC core's payment_complete() skip set_transaction_id().
		$order->set_status( $paid_status );
		$order->save();
		$order_id = $order->get_id();

		$_POST['payment_method']               = 'stripe';
		$_POST['wc-stripe-confirmation-token'] = 'ctoken_mock789';
		$_POST['wc-stripe-payment-method']     = '';
		$_POST['express_payment_type']         = WC_Stripe_Payment_Methods::APPLE_PAY;

		$this->mock_gateway->oc_enabled = false;

		$stripe_amount = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );

		$mock_intent = (object) [
			'id'                   => 'pi_mock1234567890',
			'object'               => 'payment_intent',
			'amount'               => $stripe_amount,
			'currency'             => strtolower( $order->get_currency() ),
			'customer'             => 'cus_mock1234567890',
			'latest_charge'        => 'ch_mock1234567890',
			'payment_method'       => 'pm_mock1234',
			'payment_method_types' => [ WC_Stripe_Payment_Methods::CARD ],
			'status'               => 'succeeded',
			'created'              => time(),
		];

		$mock_charge = (object) [
			'id'       => 'ch_mock1234567890',
			'captured' => true,
			'status'   => 'succeeded',
		];

		$this->mock_gateway->method( 'get_stripe_customer_id' )->willReturn( 'cus_mock1234567890' );
		$this->mock_gateway->method( 'stripe_request' )->willReturn(
			(object) [
				'id'   => 'pm_mock1234',
				'type' => 'card',
			]
		);
		$this->mock_gateway->method( 'get_latest_charge_from_intent' )->willReturn( $mock_charge );
		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'create_and_confirm_payment_intent' )
			->willReturn( $mock_intent );

		// Before processing the order has no transaction ID.
		$this->assertSame( '', $order->get_transaction_id() );

		$response = $this->mock_gateway->process_payment( $order_id );

		$this->assertEquals( 'success', $response['result'] );

		$reloaded = wc_get_order( $order_id );
		$this->assertSame( 'ch_mock1234567890', $reloaded->get_transaction_id() );
	}

	/**
	 * The confirmation token flow must still send Klarna's preferred_locale, so cross-border
	 * customers aren't routed through the Stripe account country's identity verification.
	 */
	public function test_prepare_payment_information_sets_klarna_preferred_locale_for_confirmation_token() {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_country( 'FI' );
		$order->save();

		$this->mock_gateway->oc_enabled = true;

		$_POST = [
			'payment_method'               => 'stripe_klarna',
			'wc-stripe-confirmation-token' => 'ctoken_mock',
		];

		$locale_filter = static function () {
			return 'fi_FI';
		};
		add_filter( 'locale', $locale_filter );

		$this->mock_gateway->method( 'get_stripe_customer_id' )->willReturn( 'cus_mock' );

		$reflection = new \ReflectionClass( WC_Stripe_UPE_Payment_Gateway::class );
		$method     = $reflection->getMethod( 'prepare_payment_information_from_request' );
		$method->setAccessible( true );

		try {
			$payment_information = $method->invoke( $this->mock_gateway, $order );
		} finally {
			remove_filter( 'locale', $locale_filter );
			$_POST = [];
		}

		$this->assertSame( 'ctoken_mock', $payment_information['confirmation_token'] );
		$this->assertSame( 'fi-FI', $payment_information['payment_method_options'][ WC_Stripe_Payment_Methods::KLARNA ]['preferred_locale'] );
	}

	/**
	 * Under OCS, save_payment_method_to_store must be re-evaluated against the resolved method type, not the OC pseudo-method.
	 *
	 * @dataProvider provide_test_prepare_payment_information_oc_drops_save_flag_when_resolved_method_not_reusable
	 */
	public function test_prepare_payment_information_oc_drops_save_flag_when_resolved_method_not_reusable( string $resolved_type, bool $is_reusable, bool $expected_save_flag ): void {
		$order             = WC_Helper_Order::create_order();
		$payment_method_id = 'pm_test_' . $resolved_type;

		$this->mock_gateway->oc_enabled = true;

		$resolved_method_stub = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_reusable' ] )
			->getMockForAbstractClass();
		$resolved_method_stub->method( 'is_reusable' )->willReturn( $is_reusable );
		$this->mock_gateway->payment_methods[ $resolved_type ] = $resolved_method_stub;

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

		$this->mock_gateway
			->method( 'get_stripe_customer_id' )
			->willReturn( 'cus_mock' );

		$reflection = new \ReflectionClass( WC_Stripe_UPE_Payment_Gateway::class );
		$method     = $reflection->getMethod( 'prepare_payment_information_from_request' );
		$method->setAccessible( true );

		try {
			$payment_information = $method->invoke( $this->mock_gateway, $order );
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
	 * Test test_set_payment_method_title_for_order.
	 *
	 */
	public function test_set_payment_method_title_for_order() {
		$order = WC_Helper_Order::create_order();

		// Subscriptions - note that orders are used here as subscriptions. Subscriptions inherit all order methods so should suffice for testing.
		$mock_subscription_0 = WC_Helper_Order::create_order();
		$mock_subscription_1 = WC_Helper_Order::create_order();

		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order = [ $mock_subscription_0, $mock_subscription_1 ];

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
		$this->assertEquals( 'iDEAL | Wero', $order->get_payment_method_title() );

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

		/**
		 * Link
		 */
		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID );
		// Cards should be set to `stripe`.
		$this->assertEquals( 'stripe', $order->get_payment_method() );
		$this->assertEquals( 'Link', $order->get_payment_method_title() );

		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
		$this->assertEquals( 'stripe', $mock_subscription_0->get_payment_method() );
	}

	/**
	 * Test test_set_payment_method_title_for_order with ECE wallet PM.
	 */
	public function test_set_payment_method_title_for_order_ECE_title() {
		$order = WC_Helper_Order::create_order();

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
	}

	/**
	 * With Optimized Checkout enabled, a plain `card` payment must still resolve to the
	 * CC method's title and ID, not the generic Optimized Checkout fallback.
	 */
	public function test_set_payment_method_title_for_order_with_oc_enabled_keeps_card_title() {
		$init_oc_enabled                = $this->mock_gateway->oc_enabled;
		$this->mock_gateway->oc_enabled = true;

		$order = WC_Helper_Order::create_order();

		$this->mock_gateway->set_payment_method_title_for_order( $order, WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID );

		$this->assertEquals( 'stripe', $order->get_payment_method() );
		$this->assertEquals( 'Credit / Debit Card', $order->get_payment_method_title() );

		$this->mock_gateway->oc_enabled = $init_oc_enabled;
	}

	/**
	 * Test for `filter_my_account_my_orders_actions`.
	 *
	 * @param string $payment_method_title   The payment method title.
	 * @param bool   $has_checkout_session   Whether the order has a Stripe checkout session ID (hides Pay/Cancel).
	 * @param array  $expected_action_keys   The action keys that should remain after the filter.
	 * @return void
	 * @dataProvider filter_my_account_my_orders_actions_provider
	 */
	public function test_filter_my_account_my_orders_actions( $payment_method_title, $has_checkout_session, $expected_action_keys ) {
		add_filter(
			'woocommerce_is_order_received_page',
			function () {
				return true;
			}
		);

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method_title( $payment_method_title );
		$order->set_status( OrderStatus::PENDING );

		if ( $has_checkout_session ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, 'cs_test_123' );
		}

		$actions = [
			'pay'    => [
				'url'        => $order->get_checkout_payment_url(),
				'name'       => 'Pay',
				'aria-label' => sprintf( 'Pay for order %s', $order->get_order_number() ),
			],
			'view'   => [
				'url'        => $order->get_view_order_url(),
				'name'       => 'View',
				'aria-label' => sprintf( 'View order %s', $order->get_order_number() ),
			],
			'cancel' => [
				'url'        => $order->get_cancel_order_url( wc_get_page_permalink( 'myaccount' ) ),
				'name'       => 'Cancel',
				'aria-label' => sprintf( 'Cancel order %s', $order->get_order_number() ),
			],
		];

		$actual = $this->mock_gateway->filter_my_account_my_orders_actions( $actions, $order );

		$this->assertEquals( $expected_action_keys, array_keys( $actual ) );
	}

	/**
	 * Data provider for `test_filter_my_account_my_orders_actions`.
	 *
	 * @return array
	 */
	public function filter_my_account_my_orders_actions_provider() {
		return [
			'Bacs (delayed confirmation)'                       => [
				'payment_method_title' => WC_Stripe_Payment_Methods::BACS_DEBIT_LABEL,
				'has_checkout_session' => false,
				'expected_action_keys' => [ 'view' ],
			],
			'Bacs (delayed confirmation) with checkout session' => [
				'payment_method_title' => WC_Stripe_Payment_Methods::BACS_DEBIT_LABEL,
				'has_checkout_session' => true,
				'expected_action_keys' => [ 'view' ],
			],
			'Card'                                              => [
				'payment_method_title' => WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
				'has_checkout_session' => false,
				'expected_action_keys' => [ 'pay', 'view', 'cancel' ],
			],
			'Card with checkout session'                        => [
				'payment_method_title' => WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
				'has_checkout_session' => true,
				'expected_action_keys' => [ 'view' ],
			],
		];
	}

	/**
	 * Test that a failed payment intent is not reused and a new one is created instead.
	 *
	 * @param bool $pmc_enabled Whether the payment method configurations are enabled.
	 * @param bool $setting_enabled Whether the optimized checkout setting is enabled.
	 * @param bool $expected The expected result of the `is_oc_enabled` method.
	 * @return void
	 *
	 * @dataProvider provide_test_is_oc_enabled
	 */
	public function test_is_oc_enabled( $pmc_enabled, $setting_enabled, $expected ) {
		if ( $pmc_enabled ) {
			PMC_Test_Helper::enable_pmc();

			// Mock the payment method configuration for the test, to avoid it being disabled by default.
			PMC_Test_Helper::cache_mocked_configuration();
		}

		if ( $setting_enabled ) {
			OC_Test_Helper::enable_oc();
		}

		$gateway = new WC_Stripe_UPE_Payment_Gateway();
		$actual  = $gateway->is_oc_enabled();

		// Clean up
		PMC_Test_Helper::disable_pmc();
		PMC_Test_Helper::delete_cached_configuration();
		OC_Test_Helper::disable_oc();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_is_oc_enabled`.
	 *
	 * @return array[]
	 */
	public function provide_test_is_oc_enabled() {
		return [
			'Disabled (all disabled)' => [
				'pmc enabled'   => false,
				'setting value' => false,
				'expected'      => false,
			],
			'Disabled (pmc enabled)'  => [
				'pmc enabled'   => true,
				'setting value' => false,
				'expected'      => false,
			],
			'Enabled'                 => [
				'pmc enabled'   => true,
				'setting value' => true,
				'expected'      => true,
			],
		];
	}

	/**
	 * Test for `get_payment_method_instance`.
	 *
	 * @return void
	 */
	public function test_get_payment_method_instance() {
		$actual = $this->mock_gateway->get_payment_method_instance( WC_Stripe_Payment_Methods::CARD );
		$this->assertInstanceOf( WC_Stripe_UPE_Payment_Method_CC::class, $actual );
	}

	/**
	 * Test for `add_bnpl_debug_metadata`.
	 *
	 * @return void
	 */
	public function test_add_bnpl_debug_metadata() {
		$init_oc_enabled  = $this->mock_gateway->oc_enabled;
		$init_pmc_enabled = $this->mock_gateway->settings['pmc_enabled'] ?? null;

		$this->mock_gateway->oc_enabled              = true;
		$this->mock_gateway->settings['pmc_enabled'] = true;

		$order = WC_Helper_Order::create_order();

		$result = apply_filters( 'wc_stripe_intent_metadata', [], $order );

		// Reset all variables and filters.
		$this->mock_gateway->oc_enabled = $init_oc_enabled;
		if ( null === $init_pmc_enabled ) {
			unset( $this->mock_gateway->settings['pmc_enabled'] );
		} else {
			$this->mock_gateway->settings['pmc_enabled'] = $init_pmc_enabled ? 'yes' : 'no';
		}

		$this->assertArrayHasKey( 'is_legacy_checkout_enabled', $result );
		$this->assertArrayHasKey( 'is_oc_enabled', $result );
		$this->assertEquals( 'yes', $result['is_oc_enabled'] );
		$this->assertArrayHasKey( 'pmc_enabled', $result );
		$this->assertEquals( 'yes', $result['pmc_enabled'] );
	}

	/**
	 * Test that get_customer_id_for_order() correctly creates or updates customers with billing details.
	 *
	 * For guest users, billing details are retrieved from the order object.
	 * For logged-in users, billing details come from user meta (user email and user meta fields),
	 * with the order parameter available as a fallback when user data is missing.
	 *
	 * @dataProvider provide_get_customer_id_for_order_billing_details_test_cases
	 *
	 * @param string $scenario_name Description of the test scenario.
	 * @param bool   $is_guest Whether the order is for a guest user.
	 * @param string $existing_stripe_customer_id Existing Stripe customer ID for the user (empty for new customer).
	 * @param string $expected_customer_id Expected Stripe customer ID to be returned.
	 * @param string $api_url_pattern Pattern to match the API URL.
	 * @param array  $billing_data Billing data to set on the order (and user meta for logged-in users).
	 * @param array  $expected_customer_data Expected customer data in the API request.
	 *
	 * @return void
	 */
	public function test_get_customer_id_for_order_retrieves_billing_details_from_order( string $scenario_name, bool $is_guest, string $existing_stripe_customer_id, string $expected_customer_id, string $api_url_pattern, array $billing_data, array $expected_customer_data ) {
		// Create user if needed.
		$user_id     = 0;
		$customer_id = 0;
		$user_email  = '';
		if ( ! $is_guest ) {
			// For logged-in users, the code uses user email and user meta, not order data.
			// Set user email to match expected data, and set user meta to match order billing data.
			$user_email  = $billing_data['email'];
			$user_id     = wp_create_user( 'testuser_' . uniqid(), 'password', $user_email );
			$customer_id = $user_id;
			if ( ! empty( $existing_stripe_customer_id ) ) {
				update_user_option( $user_id, '_stripe_customer_id', $existing_stripe_customer_id );
			}
			// Set user meta to match the order billing data.
			// For logged-in users, user meta takes precedence over order data.
			update_user_meta( $user_id, 'billing_first_name', $billing_data['first_name'] );
			update_user_meta( $user_id, 'billing_last_name', $billing_data['last_name'] );
			update_user_meta( $user_id, 'billing_address_1', $billing_data['address_1'] );
			update_user_meta( $user_id, 'billing_address_2', $billing_data['address_2'] ?? '' );
			update_user_meta( $user_id, 'billing_city', $billing_data['city'] );
			update_user_meta( $user_id, 'billing_state', $billing_data['state'] );
			update_user_meta( $user_id, 'billing_postcode', $billing_data['postcode'] );
			update_user_meta( $user_id, 'billing_country', $billing_data['country'] );
		}

		// Create an order with specific billing details.
		$order = WC_Helper_Order::create_order( $customer_id );
		$order->set_billing_email( $billing_data['email'] );
		$order->set_billing_first_name( $billing_data['first_name'] );
		$order->set_billing_last_name( $billing_data['last_name'] );
		$order->set_billing_address_1( $billing_data['address_1'] );
		$order->set_billing_address_2( $billing_data['address_2'] ?? '' );
		$order->set_billing_city( $billing_data['city'] );
		$order->set_billing_state( $billing_data['state'] );
		$order->set_billing_postcode( $billing_data['postcode'] );
		$order->set_billing_country( $billing_data['country'] );
		$order->save();

		// Ensure no customer ID is set on the order.
		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order_helper->delete_stripe_customer_id( $order );

		// Mock the API request to verify billing details are used.
		$api_called    = false;
		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$api_called, &$captured_args, $expected_customer_data, $api_url_pattern, $expected_customer_id ) {
				if ( preg_match( $api_url_pattern, $url ) ) {
					$api_called    = true;
					$captured_args = $parsed_args;

					// Return a mock successful response.
					return [
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'headers'  => [ 'Content-Type' => 'application/json' ],
						'body'     => wp_json_encode(
							[
								'id'    => $expected_customer_id,
								'email' => $expected_customer_data['email'],
								'name'  => $expected_customer_data['name'],
							]
						),
					];
				}

				return $preempt;
			},
			10,
			3
		);

		// Create a mock gateway instance with specific methods mocked.
		// The mock inherits all methods from WC_Stripe_UPE_Payment_Gateway, including the private method we'll test.
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->onlyMethods( [ 'get_stripe_customer_id', 'get_user_from_order', 'is_valid_pay_for_order_endpoint' ] )
			->getMock();

		// Use reflection to access the private method on the mock instance.
		// The mock inherits the method from the parent class, so reflection works correctly.
		$reflection = new \ReflectionClass( $gateway );
		$method     = $reflection->getMethod( 'get_customer_id_for_order' );
		$method->setAccessible( true );

		$gateway->expects( $this->once() )
			->method( 'get_stripe_customer_id' )
			->with( $order )
			->willReturn( '' ); // No customer ID on order.

		if ( ! $is_guest ) {
			$user = get_user_by( 'id', $user_id );
		} else {
			$user     = new \WP_User();
			$user->ID = 0;
		}

		$gateway->expects( $this->once() )
			->method( 'get_user_from_order' )
			->with( $order )
			->willReturn( $user );

		$gateway->expects( $this->any() )
			->method( 'is_valid_pay_for_order_endpoint' )
			->willReturn( false );

		// Call the method.
		$result_customer_id = $method->invoke( $gateway, $order );

		// Verify the API was called and billing details were used.
		$this->assertTrue( $api_called, "Stripe API should have been called to {$scenario_name}." );
		$this->assertEquals( $expected_customer_id, $result_customer_id );

		// Verify the request body contains the expected billing details.
		// The body is passed as an array to wp_safe_remote_post, so we check it directly.
		if ( $captured_args && isset( $captured_args['body'] ) ) {
			$request_body = $captured_args['body'];
			// Ensure we have an array (wp_safe_remote_post receives body as array).
			$this->assertIsArray( $request_body, 'Request body should be an array.' );

			// Verify that the order object is NOT included in the API request (main purpose of this PR).
			$this->assertArrayNotHasKey( 'order', $request_body, 'Order object should not be included in the API request.' );

			// Verify billing details from the order are used in the customer creation/update request.
			$this->assertEquals( $expected_customer_data['email'], $request_body['email'] ?? '', 'Billing email should match order billing email.' );
			$this->assertEquals( $expected_customer_data['name'], $request_body['name'] ?? '', 'Billing name should match order billing name.' );

			// Verify address details are present and match the order.
			$this->assertArrayHasKey( 'address', $request_body, 'Request should include address data.' );
			$this->assertEquals( $expected_customer_data['address']['line1'], $request_body['address']['line1'] ?? '', 'Billing address line1 should match order.' );
			if ( ! empty( $expected_customer_data['address']['line2'] ) ) {
				$this->assertEquals( $expected_customer_data['address']['line2'], $request_body['address']['line2'] ?? '', 'Billing address line2 should match order.' );
			} else {
				// When line2 is empty, verify it's either not present or empty in the request body.
				$this->assertTrue(
					null === $request_body['address']['line2'] || '' === $request_body['address']['line2'],
					'Billing address line2 should be empty or not present when order has no line2.'
				);
			}

			$this->assertEquals( $expected_customer_data['address']['city'], $request_body['address']['city'] ?? '', 'Billing city should match order.' );
			$this->assertEquals( $expected_customer_data['address']['state'], $request_body['address']['state'] ?? '', 'Billing state should match order.' );
			$this->assertEquals( $expected_customer_data['address']['postal_code'], $request_body['address']['postal_code'] ?? '', 'Billing postal code should match order.' );
			$this->assertEquals( $expected_customer_data['address']['country'], $request_body['address']['country'] ?? '', 'Billing country should match order.' );
		}

		// Cleanup.
		if ( $user_id > 0 ) {
			wp_delete_user( $user_id );
		}
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Data provider for test_get_customer_id_for_order_retrieves_billing_details_from_order.
	 *
	 * @return array Test cases.
	 */
	public function provide_get_customer_id_for_order_billing_details_test_cases() {
		return [
			'creating customer for guest user'     => [
				'scenario_name'               => 'create customer',
				'is_guest'                    => true,
				'existing_stripe_customer_id' => '',
				'expected_customer_id'        => 'cus_test123',
				'api_url_pattern'             => '#/v1/customers$#',
				'billing_data'                => [
					'email'      => 'test-billing@example.com',
					'first_name' => 'TestFirstName',
					'last_name'  => 'TestLastName',
					'address_1'  => '123 Test Street',
					'address_2'  => 'Apt 4B',
					'city'       => 'TestCity',
					'state'      => 'CA',
					'postcode'   => '90210',
					'country'    => 'US',
				],
				'expected_customer_data'      => [
					'email'       => 'test-billing@example.com',
					'name'        => 'TestFirstName TestLastName',
					'description' => 'Name: TestFirstName TestLastName, Guest',
					'address'     => [
						'line1'       => '123 Test Street',
						'line2'       => 'Apt 4B',
						'city'        => 'TestCity',
						'state'       => 'CA',
						'postal_code' => '90210',
						'country'     => 'US',
					],
				],
			],
			'updating customer for logged-in user' => [
				'scenario_name'               => 'update customer',
				'is_guest'                    => false,
				'existing_stripe_customer_id' => 'cus_existing123',
				'expected_customer_id'        => 'cus_existing123',
				'api_url_pattern'             => '#/v1/customers/cus_existing123$#',
				'billing_data'                => [
					'email'      => 'updated-billing@example.com',
					'first_name' => 'UpdatedFirstName',
					'last_name'  => 'UpdatedLastName',
					'address_1'  => '456 Updated Street',
					'address_2'  => '',
					'city'       => 'UpdatedCity',
					'state'      => 'NY',
					'postcode'   => '10001',
					'country'    => 'US',
				],
				// For logged-in users, user email and user meta are used (not order data).
				// The expected data should match what will be in user meta (set in the test).
				'expected_customer_data'      => [
					'email'   => 'updated-billing@example.com', // User email matches order email (set in test)
					'name'    => 'UpdatedFirstName UpdatedLastName',
					'address' => [
						'line1'       => '456 Updated Street',
						'line2'       => '',
						'city'        => 'UpdatedCity',
						'state'       => 'NY',
						'postal_code' => '10001',
						'country'     => 'US',
					],
				],
			],
		];
	}

	/**
	 * Test `get_excluded_payment_method_types` in various scenarios.
	 *
	 * @param array  $unsupported_methods        Unsupported payment methods from PMC.
	 * @param callable|null $filter_callback     Filter callback function or null.
	 * @param array  $expected_excluded          Payment methods expected to be excluded.
	 * @param array  $expected_not_excluded      Payment methods expected NOT to be excluded.
	 * @return void
	 * @dataProvider provide_test_get_excluded_payment_method_types
	 */
	public function test_get_excluded_payment_method_types( array $unsupported_methods, $filter_callback, array $expected_excluded, array $expected_not_excluded ) {
		$initial_settings = WC_Stripe_Helper::get_stripe_settings();
		$settings_base    = WC_Stripe_Helper::get_stripe_settings();

		// Set up settings with PMC enabled and test mode
		$settings = array_merge(
			$settings_base,
			[
				'pmc_enabled'          => 'yes',
				'testmode'             => 'yes',
				'test_publishable_key' => 'pk_test_1234567890',
				'test_secret_key'      => 'sk_test_1234567890',
				'test_connection_type' => 'connect',
			]
		);
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// Build mock API response with unsupported enabled methods
		$pmc_data = (object) [
			'id'       => 'pmc_test',
			'parent'   => \WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
			'active'   => true,
			'livemode' => false,
		];

		foreach ( $unsupported_methods as $method_id ) {
			$pmc_data->$method_id = (object) [
				'display_preference' => (object) [ 'value' => 'on' ],
			];
		}

		$mock_api_response = (object) [
			'data' => [ $pmc_data ],
		];

		$mock_api = $this->getMockBuilder( WC_Stripe_API::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_api->method( 'get_payment_method_configurations' )
			->willReturn( $mock_api_response );

		$reflection = new \ReflectionClass( WC_Stripe_API::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $mock_api );

		// Clear cache
		delete_option( \WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		// Add filter if provided
		if ( null !== $filter_callback ) {
			add_filter( 'wc_stripe_ocs_non_excludable_payment_methods', $filter_callback );
		}

		// Create gateway instance and call method via reflection
		$gateway            = new WC_Stripe_UPE_Payment_Gateway();
		$reflection_gateway = new \ReflectionClass( WC_Stripe_UPE_Payment_Gateway::class );
		$method             = $reflection_gateway->getMethod( 'get_excluded_payment_method_types' );
		$method->setAccessible( true );

		$excluded_methods = $method->invoke( $gateway );

		// Cleanup
		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_ocs_non_excludable_payment_methods', $filter_callback );
		}
		WC_Stripe_Helper::update_main_stripe_settings( $initial_settings );
		$property->setValue( null, null );
		delete_option( \WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		\WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();

		// Assertions.
		foreach ( $expected_excluded as $method_id ) {
			$this->assertContains(
				$method_id,
				$excluded_methods,
				"Expected method '{$method_id}' to be excluded."
			);
		}

		foreach ( $expected_not_excluded as $method_id ) {
			$this->assertNotContains(
				$method_id,
				$excluded_methods,
				"Expected method '{$method_id}' NOT to be excluded."
			);
		}

		// Amazon Pay should always be excluded
		$this->assertContains(
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			$excluded_methods,
			'Amazon Pay should always be excluded.'
		);
	}

	/**
	 * Data provider for `test_get_excluded_payment_method_types`.
	 *
	 * @return array
	 */
	public function provide_test_get_excluded_payment_method_types(): array {
		return [
			'No filter, unsupported methods'                                     => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'paypal' ],
				'filter_callback'       => null,
				'expected_excluded'     => [ 'fpx', 'naver_pay', 'paypal', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [],
			],
			'Filter with unsupported methods'                                    => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'abc' ],
				'filter_callback'       => function () {
					return [ 'abc' ];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'abc' ],
			],
			'Filter with empty array'                                            => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay' ],
				'filter_callback'       => function () {
					return [];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [],
			],
			'Filter with non-string values'                                      => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'paypal', 'abc' ],
				'filter_callback'       => function () {
					return [ 123, null, 'abc', [], 'valid_method' ];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', 'paypal', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'abc', 'valid_method' ],
			],
			'Filter with methods already in NON_EXCLUDABLE_PAYMENT_METHOD_TYPES' => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay', 'paypal' ],
				'filter_callback'       => function () {
					return [ 'link', 'apple_pay', 'abc' ];
				},
				'expected_excluded'     => [ 'fpx', 'naver_pay', 'paypal', WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'link', 'apple_pay', 'abc' ],
			],
			'No unsupported methods'                                             => [
				'unsupported_methods'   => [],
				'filter_callback'       => null,
				'expected_excluded'     => [ WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [],
			],
			'Filter with duplicate values'                                       => [
				'unsupported_methods'   => [ 'fpx', 'naver_pay' ],
				'filter_callback'       => function () {
					return [ 'fpx', 'fpx', 'naver_pay' ];
				},
				'expected_excluded'     => [ WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'expected_not_excluded' => [ 'fpx', 'naver_pay' ],
			],
		];
	}

	/**
	 * Data provider for test_payment_scripts_enqueues_correct_assets.
	 *
	 * @return array[]
	 */
	public function provider_payment_scripts_enqueue_scenarios() {
		/*
		 * NOTE: The Amazon Pay payment method MUST be enabled for the express payment method to be detected as available.
		 */
		return [
			'Product page with ECE off, no Amazon Pay'            => [
				'page_type'                                 => 'product',
				'express_checkout'                          => 'no',
				'express_checkout_button_locations'         => [],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD ],
				'amazon_pay_button_locations'               => [],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => false,
			],
			'Cart page with ECE off, no Amazon Pay'               => [
				'page_type'                                 => 'cart',
				'express_checkout'                          => 'no',
				'express_checkout_button_locations'         => [],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD ],
				'amazon_pay_button_locations'               => [],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => false,
			],
			'Cart page with ECE on at cart'                       => [
				'page_type'                                 => 'cart',
				'express_checkout'                          => 'yes',
				'express_checkout_button_locations'         => [ 'cart' ],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD ],
				'amazon_pay_button_locations'               => [],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => true,
			],
			'Cart page with ECE off, Amazon Pay on at cart'       => [
				'page_type'                                 => 'cart',
				'express_checkout'                          => 'no',
				'express_checkout_button_locations'         => [],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'amazon_pay_button_locations'               => [ 'cart' ],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => true,
			],
			'Product page with ECE on at product'                 => [
				'page_type'                                 => 'product',
				'express_checkout'                          => 'yes',
				'express_checkout_button_locations'         => [ 'product' ],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD ],
				'amazon_pay_button_locations'               => [],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => true,
			],
			'Product page with ECE off, Amazon Pay on at product' => [
				'page_type'                                 => 'product',
				'express_checkout'                          => 'no',
				'express_checkout_button_locations'         => [],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::AMAZON_PAY ],
				'amazon_pay_button_locations'               => [ 'product' ],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => true,
			],
			'Checkout page with ECE off and Amazon Pay off'       => [
				'page_type'                                 => 'checkout',
				'express_checkout'                          => 'no',
				'express_checkout_button_locations'         => [],
				'upe_checkout_experience_accepted_payments' => [ WC_Stripe_Payment_Methods::CARD ],
				'amazon_pay_button_locations'               => [],
				'expected_stripe'                           => true,
				'expected_upe_classic'                      => true,
			],
		];
	}

	/**
	 * Test that payment_scripts() enqueues the correct assets based on page type and express checkout settings.
	 *
	 * @dataProvider provider_payment_scripts_enqueue_scenarios
	 *
	 * @param string $page_type                                 Page type: 'product', 'cart', or 'checkout'.
	 * @param string $express_checkout                          Express checkout enabled: 'yes' or 'no'.
	 * @param array  $express_checkout_button_locations         Express checkout button locations.
	 * @param array  $upe_checkout_experience_accepted_payments Enabled UPE payment methods.
	 * @param array  $amazon_pay_button_locations               Amazon Pay button locations.
	 * @param bool   $expected_stripe                           Whether 'stripe' script should be enqueued.
	 * @param bool   $expected_upe_classic                      Whether 'wc-stripe-upe-classic' script should be enqueued.
	 */
	public function test_payment_scripts_enqueues_correct_assets( $page_type, $express_checkout, $express_checkout_button_locations, $upe_checkout_experience_accepted_payments, $amazon_pay_button_locations, $expected_stripe, $expected_upe_classic ) {
		$product            = null;
		$is_cart_filter     = null;
		$is_checkout_filter = null;

		if ( 'product' === $page_type ) {
			$product = WC_Helper_Product::create_simple_product();
			$this->go_to( get_permalink( $product->get_id() ) );
		} elseif ( 'cart' === $page_type ) {
			$is_cart_filter = function () {
				return true;
			};
			add_filter( 'woocommerce_is_cart', $is_cart_filter );
		} elseif ( 'checkout' === $page_type ) {
			$is_checkout_filter = function () {
				return true;
			};
			add_filter( 'woocommerce_is_checkout', $is_checkout_filter );
		}

		$original_settings = WC_Stripe_Helper::get_stripe_settings();

		$stripe_settings                                      = $original_settings;
		$stripe_settings['enabled']                           = 'yes';
		$stripe_settings['express_checkout']                  = $express_checkout;
		$stripe_settings['express_checkout_button_locations'] = $express_checkout_button_locations;
		$stripe_settings['upe_checkout_experience_accepted_payments'] = $upe_checkout_experience_accepted_payments;
		$stripe_settings['amazon_pay_button_locations']               = $amazon_pay_button_locations;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		try {
			$gateway          = new WC_Stripe_UPE_Payment_Gateway();
			$gateway->enabled = 'yes';

			$this->clean_up_scripts(
				[ 'stripe', 'wc-stripe-upe-classic' ],
				[ 'stripelink_styles', 'wc-stripe-upe-classic' ]
			);

			$gateway->payment_scripts();

			$this->assertSame( $expected_stripe, wp_script_is( 'stripe', 'enqueued' ), 'Unexpected enqueue state for stripe JS.' );
			$this->assertSame( $expected_upe_classic, wp_script_is( 'wc-stripe-upe-classic', 'enqueued' ), 'Unexpected enqueue state for wc-stripe-upe-classic.' );
		} finally {
			WC_Stripe_Helper::update_main_stripe_settings( $original_settings );

			$this->clean_up_scripts(
				[ 'stripe', 'wc-stripe-upe-classic' ],
				[ 'stripelink_styles', 'wc-stripe-upe-classic' ]
			);

			if ( $product ) {
				$product->delete( true );
			}

			if ( $is_checkout_filter ) {
				remove_filter( 'woocommerce_is_checkout', $is_checkout_filter );
			}

			if ( $is_cart_filter ) {
				remove_filter( 'woocommerce_is_cart', $is_cart_filter );
			}
		}
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
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
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
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
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
	 * Build a minimal gateway mock suitable for exercising `javascript_params()`.
	 *
	 * All instance methods that reach out to Stripe or WooCommerce infrastructure are
	 * stubbed; everything else (including `apply_filters`) runs for real so that
	 * filter-based behaviour can be asserted.
	 *
	 * @return WC_Stripe_UPE_Payment_Gateway
	 */
	private function create_gateway_mock_for_javascript_params(): WC_Stripe_UPE_Payment_Gateway {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods(
				[
					'get_return_url',
					'get_stripe_return_url',
					'is_changing_payment_method_for_subscription',
					'is_subscription_item_in_cart',
					'is_valid_optimized_checkout_page',
				]
			)
			->getMock();

		$gateway->method( 'get_return_url' )->willReturn( '' );
		$gateway->method( 'get_stripe_return_url' )->willReturn( '' );
		$gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( false );
		$gateway->method( 'is_subscription_item_in_cart' )->willReturn( false );
		$gateway->method( 'is_valid_optimized_checkout_page' )->willReturn( false );

		$this->set_stripe_account_data( [ 'country' => 'US' ] );

		return $gateway;
	}

	/**
	 * Tests that `javascript_params()` handles the `wc_stripe_upe_permitted_font_domains` filter correctly.
	 *
	 * @dataProvider provide_test_javascript_params_permitted_font_domains
	 *
	 * @param mixed|null $filter_return     Value returned by the filter, or null to skip adding the filter entirely.
	 * @param bool       $expected_in_params Whether `permittedFontDomains` should be present in the params.
	 * @param mixed      $expected_value    Expected value of `permittedFontDomains` when present.
	 */
	public function test_javascript_params_permitted_font_domains( $filter_return, bool $expected_in_params, $expected_value ): void {
		$gateway = $this->create_gateway_mock_for_javascript_params();

		$filter_callback = null;
		if ( null !== $filter_return ) {
			$filter_callback = function () use ( $filter_return ) {
				return $filter_return;
			};
			add_filter( 'wc_stripe_upe_permitted_font_domains', $filter_callback );
		}

		$params = $gateway->javascript_params();

		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_upe_permitted_font_domains', $filter_callback );
		}

		if ( $expected_in_params ) {
			$this->assertArrayHasKey( 'permittedFontDomains', $params );
			$this->assertSame( $expected_value, $params['permittedFontDomains'] );
		} else {
			$this->assertArrayNotHasKey( 'permittedFontDomains', $params );
		}
	}

	/**
	 * Data provider for `test_javascript_params_permitted_font_domains`.
	 *
	 * @return array[]
	 */
	public function provide_test_javascript_params_permitted_font_domains(): array {
		return [
			'no filter hooked — key is omitted'                                        => [
				'filter_return'      => null,
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns empty array — key is omitted'                              => [
				'filter_return'      => [],
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns non-empty array — key is set'                              => [
				'filter_return'      => [ 'custom-fonts.example.com', 'fonts.mysite.com' ],
				'expected_in_params' => true,
				'expected_value'     => [ 'custom-fonts.example.com', 'fonts.mysite.com' ],
			],
			'filter returns non-array string — key is omitted'                         => [
				'filter_return'      => 'custom-fonts.example.com',
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns domain without dot (e.g. localhost) — key is omitted'      => [
				'filter_return'      => [ 'localhost' ],
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns domain starting with dot — key is omitted'                 => [
				'filter_return'      => [ '.example.com' ],
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns domain with single-char TLD — key is omitted'              => [
				'filter_return'      => [ 'example.c' ],
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns domain with trailing dot — key is omitted'                 => [
				'filter_return'      => [ 'example.' ],
				'expected_in_params' => false,
				'expected_value'     => null,
			],
			'filter returns array with non-string elements — non-strings are excluded' => [
				'filter_return'      => [ 'fonts.example.com', 42, null, true, 'type.mysite.org' ],
				'expected_in_params' => true,
				'expected_value'     => [ 'fonts.example.com', 'type.mysite.org' ],
			],
			'filter returns mixed valid and invalid domains — only valid are included' => [
				'filter_return'      => [ 'fonts.example.com', 'localhost', '.bad.com', 'good.fonts.io', 'also.bad.' ],
				'expected_in_params' => true,
				'expected_value'     => [ 'fonts.example.com', 'good.fonts.io' ],
			],
			'filter returns uppercase domain — stored as lowercase'                    => [
				'filter_return'      => [ 'Fonts.Example.COM' ],
				'expected_in_params' => true,
				'expected_value'     => [ 'fonts.example.com' ],
			],
			'filter returns domain with surrounding whitespace — stored trimmed'       => [
				'filter_return'      => [ '  fonts.example.com  ' ],
				'expected_in_params' => true,
				'expected_value'     => [ 'fonts.example.com' ],
			],
			'filter returns duplicate valid domains — deduplicated'                    => [
				'filter_return'      => [ 'fonts.example.com', 'fonts.example.com', 'FONTS.EXAMPLE.COM' ],
				'expected_in_params' => true,
				'expected_value'     => [ 'fonts.example.com' ],
			],
		];
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
	 * Test for `expand_copy_button_markup`.
	 *
	 * @dataProvider provider_expand_copy_button_markup
	 *
	 * @param string $input    Input string with <number> tags.
	 * @param string $expected Expected output with copy button markup.
	 *
	 * @return void
	 */
	public function test_expand_copy_button_markup( $input, $expected ) {
		$actual = WC_Stripe_UPE_Payment_Gateway::expand_copy_button_markup( $input );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider for `test_expand_copy_button_markup`.
	 *
	 * @return array[]
	 */
	public function provider_expand_copy_button_markup() {
		$copy_label = 'Copy to clipboard';
		return [
			'string with multiple number tags' => [
				'input'    => 'Use <number>4242 4242 4242 4242</number> and <number>AT611904300234573201</number>.',
				'expected' => 'Use <button type="button" class="wc-stripe-copy-test-number" aria-label="' . $copy_label . '" title="' . $copy_label . '"><i></i><span>4242 4242 4242 4242</span></button> and <button type="button" class="wc-stripe-copy-test-number" aria-label="' . $copy_label . '" title="' . $copy_label . '"><i></i><span>AT611904300234573201</span></button>.',
			],
			'string with number tag'           => [
				'input'    => '<strong>Test mode:</strong> use card <number>4242 4242 4242 4242</number> with any expiry.',
				'expected' => '<strong>Test mode:</strong> use card <button type="button" class="wc-stripe-copy-test-number" aria-label="' . $copy_label . '" title="' . $copy_label . '"><i></i><span>4242 4242 4242 4242</span></button> with any expiry.',
			],
			'string without number tag'        => [
				'input'    => '<strong>Test mode:</strong> use any 6-digit number.',
				'expected' => '<strong>Test mode:</strong> use any 6-digit number.',
			],
			'empty string'                     => [
				'input'    => '',
				'expected' => '',
			],
		];
	}

	/**
	 * Test that add_converted_currency_information returns unchanged total when no checkout session is associated with the order.
	 *
	 * @return void
	 */
	public function test_add_converted_currency_information_returns_unchanged_total_when_no_checkout_session(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$formatted_total = '$10.00';
		$result          = $this->mock_gateway->add_converted_currency_information( $formatted_total, $order );

		$this->assertEquals( $formatted_total, $result );
	}

	/**
	 * Test that add_converted_currency_information returns unchanged total when checkout session has no presentment details.
	 *
	 * @return void
	 */
	public function test_add_converted_currency_information_returns_unchanged_total_when_no_presentment_details(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_total( 20.00 );
		$order->save();

		$checkout_session_id = 'cs_test_no_presentment_1';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );

		$checkout_session = $this->array_to_object(
			[
				'id'           => $checkout_session_id,
				'amount_total' => 2000,
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		$formatted_total = '$10.00';
		$result          = $this->mock_gateway->add_converted_currency_information( $formatted_total, $order );

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$this->assertEquals( $formatted_total, $result );
	}

	/**
	 * Data provider for page-context filters used by add_converted_currency_information.
	 *
	 * @return array<string, array{string}>
	 */
	public function provide_add_converted_currency_information_page_contexts(): array {
		return [
			'order received page' => [ 'woocommerce_is_order_received_page' ],
			'account page'        => [ 'woocommerce_is_account_page' ],
		];
	}

	/**
	 * Test that add_converted_currency_information appends the converted currency info when presentment details are present.
	 *
	 * @dataProvider provide_add_converted_currency_information_page_contexts
	 *
	 * @param string $page_context_filter The page-context filter to simulate.
	 * @return void
	 */
	public function test_add_converted_currency_information_appends_converted_currency_info( string $page_context_filter ): void {
		add_filter( $page_context_filter, '__return_true' );

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_total( 20.00 );
		$order->save();

		$checkout_session_id = 'cs_test_with_presentment_1';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );

		$checkout_session = $this->array_to_object(
			[
				'id'                  => $checkout_session_id,
				'amount_total'        => 2000,
				'presentment_details' => [
					'presentment_amount'   => 1500,
					'presentment_currency' => 'eur',
				],
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		try {
			$formatted_total = '$10.00';
			$result          = $this->mock_gateway->add_converted_currency_information( $formatted_total, $order );
			$expected_amount = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( 1500, 'eur' );

			$this->assertEquals( '$10.00 (&euro; ' . $expected_amount . ' EUR)', $result );
		} finally {
			WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );
			remove_filter( $page_context_filter, '__return_true' );
		}
	}

	/**
	 * Test that add_currency_conversion_notice outputs nothing when no checkout session is associated with the order.
	 *
	 * @return void
	 */
	public function test_add_currency_conversion_notice_outputs_nothing_when_no_checkout_session(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		ob_start();
		$this->mock_gateway->add_currency_conversion_notice( $order );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that add_currency_conversion_notice outputs nothing when checkout session has no presentment details.
	 *
	 * @return void
	 */
	public function test_add_currency_conversion_notice_outputs_nothing_when_no_presentment_details(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_total( 20.00 );
		$order->save();

		$checkout_session_id = 'cs_test_no_presentment_3';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );

		$checkout_session = $this->array_to_object(
			[
				'id'           => $checkout_session_id,
				'amount_total' => 2000,
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		ob_start();
		$this->mock_gateway->add_currency_conversion_notice( $order );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$this->assertEmpty( $output );
	}

	/**
	 * Test that add_currency_conversion_notice outputs a notice with the correct converted amount and exchange rate.
	 *
	 * @return void
	 */
	public function test_add_currency_conversion_notice_outputs_notice_with_converted_amount_and_rate(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		// Set total to $20.00 USD (2000 cents) to match the mocked checkout session amount_total.
		$order->set_total( 20 );
		$order->save();

		$checkout_session_id = 'cs_test_with_presentment_3';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );

		$checkout_session = $this->array_to_object(
			[
				'id'                  => $checkout_session_id,
				'amount_total'        => 2000,
				'presentment_details' => [
					'presentment_amount'   => 1500,
					'presentment_currency' => 'eur',
				],
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		ob_start();
		$this->mock_gateway->add_currency_conversion_notice( $order );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		// 1500 EUR cents = 15.00 EUR; 15.00 / 20.00 (order total) = 0.750 exchange rate (always 3 decimal places).
		$expected_amount = '15.00';
		$expected_rate   = '0.750';

		$this->assertStringContainsString( '<p class="woocommerce-info" style="margin-top: 1em;">', $output );
		$this->assertStringContainsString( $expected_amount . ' EUR', $output );
		$this->assertStringContainsString( $expected_rate . ' EUR', $output );
		$this->assertStringContainsString( '</p>', $output );
	}

	/**
	 * Test that display_paid_by_customer_amount outputs nothing when no checkout session is associated with the order.
	 *
	 * @return void
	 */
	public function test_display_paid_by_customer_amount_outputs_nothing_when_no_checkout_session(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		ob_start();
		$this->mock_gateway->display_paid_by_customer_amount( $order->get_id() );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that display_paid_by_customer_amount outputs nothing when the checkout session has no presentment details.
	 *
	 * @return void
	 */
	public function test_display_paid_by_customer_amount_outputs_nothing_when_no_presentment_details(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$checkout_session_id = 'cs_test_paid_by_customer_no_presentment';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		// The render method reloads the order by ID, so the session meta must be persisted.
		$order->save();

		$checkout_session = $this->array_to_object(
			[
				'id'           => $checkout_session_id,
				'amount_total' => 2000,
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		try {
			ob_start();
			$this->mock_gateway->display_paid_by_customer_amount( $order->get_id() );
			$output = ob_get_clean();

			$this->assertEmpty( $output );
		} finally {
			WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );
		}
	}

	/**
	 * Data provider for display_paid_by_customer_amount currency-code disambiguation.
	 *
	 * The store currency is USD ("$"). The code is only spelled out when the presentment
	 * currency shares that symbol (AUD is also "$"); BDT ("৳") stands on its own.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public function provide_display_paid_by_customer_currency_code(): array {
		return [
			'shared symbol shows code'   => [ 'aud', 'AUD', true ],
			'distinct symbol hides code' => [ 'bdt', 'BDT', false ],
		];
	}

	/**
	 * Test that display_paid_by_customer_amount appends the currency code only when its symbol
	 * collides with the store currency's symbol.
	 *
	 * @dataProvider provide_display_paid_by_customer_currency_code
	 *
	 * @param string $presentment_currency The presentment currency code (lowercase).
	 * @param string $expected_code        The uppercase code that may be shown.
	 * @param bool   $expects_code         Whether the "(CODE)" suffix should be rendered.
	 * @return void
	 */
	public function test_display_paid_by_customer_amount_appends_code_only_on_symbol_collision( string $presentment_currency, string $expected_code, bool $expects_code ): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_currency( 'USD' );
		$order->set_total( 20.00 );
		$order->save();

		$checkout_session_id = 'cs_test_paid_by_customer_' . $presentment_currency;
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );
		// The render method reloads the order by ID, so the session meta must be persisted.
		$order->save();

		$checkout_session = $this->array_to_object(
			[
				'id'                  => $checkout_session_id,
				'amount_total'        => 2000,
				'presentment_details' => [
					'presentment_amount'   => 1500,
					'presentment_currency' => $presentment_currency,
				],
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		try {
			ob_start();
			$this->mock_gateway->display_paid_by_customer_amount( $order->get_id() );
			$output = ob_get_clean();

			$this->assertStringContainsString( 'stripe-paid-by-customer', $output );
			$this->assertStringContainsString( 'Paid by customer:', $output );
			$this->assertStringContainsString( '15.00', $output );

			if ( $expects_code ) {
				$this->assertStringContainsString( '(' . $expected_code . ')', $output );
			} else {
				$this->assertStringNotContainsString( $expected_code, $output );
			}
		} finally {
			WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );
		}
	}

	/**
	 * Test that add_converted_currency_information reads presentment data from order meta without making an API call.
	 *
	 * @return void
	 */
	public function test_add_converted_currency_information_reads_from_order_meta_without_api_call(): void {
		add_filter( 'woocommerce_is_order_received_page', '__return_true' );

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$checkout_session_id = 'cs_test_meta_presentment_1';
		$order_helper        = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order_helper->update_stripe_presentment_amount( $order, 1500 );
		$order_helper->update_stripe_presentment_currency( $order, 'eur' );

		// No checkout session is cached — if the code tries to fetch it, the test will fail.
		$formatted_total = '$10.00';
		$result          = $this->mock_gateway->add_converted_currency_information( $formatted_total, $order );

		$expected_amount = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( 1500, 'eur' );

		$this->assertEquals( '$10.00 (&euro; ' . $expected_amount . ' EUR)', $result );
	}

	/**
	 * Test that add_currency_conversion_notice reads presentment data from order meta without making an API call.
	 *
	 * @return void
	 */
	public function test_add_currency_conversion_notice_reads_from_order_meta_without_api_call(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$checkout_session_id = 'cs_test_meta_presentment_2';
		$order_helper        = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order_helper->update_stripe_presentment_amount( $order, 1500 );
		$order_helper->update_stripe_presentment_currency( $order, 'eur' );

		// No checkout session is cached — if the code tries to fetch it, the test will fail.
		ob_start();
		$this->mock_gateway->add_currency_conversion_notice( $order );
		$output = ob_get_clean();

		$expected_amount = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( 1500, 'eur' );

		// Rate is always formatted to exactly 3 decimal places; uses major-unit amounts.
		$expected_rate = wc_format_decimal( (float) $expected_amount / $order->get_total(), 3 );

		$this->assertStringContainsString( '<p class="woocommerce-info" style="margin-top: 1em;">', $output );
		$this->assertStringContainsString( $expected_amount . ' EUR', $output );
		$this->assertStringContainsString( $expected_rate . ' EUR', $output );
		$this->assertStringContainsString( '</p>', $output );
	}

	/**
	 * Tests for `should_upe_payment_method_show_save_option`.
	 *
	 * Verifies that the private method correctly hides the save option
	 * for card and link when Link is enabled, while leaving OC unaffected.
	 *
	 * @param string $payment_method_class The payment method class name.
	 * @param array  $enabled_methods      Enabled UPE payment method IDs.
	 * @param string $saved_cards          The 'saved_cards' setting value.
	 * @param bool   $expected             Expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_should_upe_payment_method_show_save_option
	 */
	public function test_should_upe_payment_method_show_save_option( $payment_method_class, $enabled_methods, $saved_cards, $expected ) {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_upe_enabled_payment_method_ids', 'is_saved_cards_enabled', 'is_subscription_item_in_cart', 'is_pre_order_charged_upon_release_in_cart' ] )
			->getMock();

		$gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( $enabled_methods );

		$gateway->method( 'is_saved_cards_enabled' )
			->willReturn( 'yes' === $saved_cards );

		$gateway->method( 'is_subscription_item_in_cart' )
			->willReturn( false );

		$gateway->method( 'is_pre_order_charged_upon_release_in_cart' )
			->willReturn( false );

		$payment_method = new $payment_method_class();

		$method = new ReflectionMethod( WC_Stripe_UPE_Payment_Gateway::class, 'should_upe_payment_method_show_save_option' );
		$method->setAccessible( true );

		$actual = $method->invoke( $gateway, $payment_method );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for `test_should_upe_payment_method_show_save_option`.
	 *
	 * @return array
	 */
	public function provide_test_should_upe_payment_method_show_save_option() {
		$card_and_link = [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ];
		$card_only     = [ WC_Stripe_Payment_Methods::CARD ];

		return [
			'card — Link enabled, saved cards on — false'   => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_CC::class,
				'enabled_methods'      => $card_and_link,
				'saved_cards'          => 'yes',
				'expected'             => false,
			],
			'card — Link disabled, saved cards on — true'   => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_CC::class,
				'enabled_methods'      => $card_only,
				'saved_cards'          => 'yes',
				'expected'             => true,
			],
			'card — Link disabled, saved cards off — false' => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_CC::class,
				'enabled_methods'      => $card_only,
				'saved_cards'          => 'no',
				'expected'             => false,
			],
			'link — Link enabled, saved cards on — false'   => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_Link::class,
				'enabled_methods'      => $card_and_link,
				'saved_cards'          => 'yes',
				'expected'             => false,
			],
			'link — Link disabled, saved cards on — true'   => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_Link::class,
				'enabled_methods'      => $card_only,
				'saved_cards'          => 'yes',
				'expected'             => true,
			],
			'OC — Link enabled, saved cards on — true'      => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_OC::class,
				'enabled_methods'      => $card_and_link,
				'saved_cards'          => 'yes',
				'expected'             => true,
			],
			'OC — Link disabled, saved cards on — true'     => [
				'payment_method_class' => WC_Stripe_UPE_Payment_Method_OC::class,
				'enabled_methods'      => $card_only,
				'saved_cards'          => 'yes',
				'expected'             => true,
			],
		];
	}

	/**
	 * Creates an order with presentment data cached for email notice tests.
	 *
	 * @param string $checkout_session_id The checkout session ID to use.
	 * @return WC_Order
	 */
	private function create_order_with_presentment_email_data( string $checkout_session_id ): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_total( 20.00 );
		$order->save();

		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );

		$checkout_session = $this->array_to_object(
			[
				'id'                  => $checkout_session_id,
				'amount_total'        => 2000,
				'presentment_details' => [
					'presentment_amount'   => 1500,
					'presentment_currency' => 'eur',
				],
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		return $order;
	}

	/**
	 * Test that add_email_currency_conversion_notice outputs nothing when no checkout session is associated with the order.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_outputs_nothing_when_no_checkout_session(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that add_email_currency_conversion_notice outputs nothing when checkout session has no presentment details.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_outputs_nothing_when_no_presentment_details(): void {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->save();

		$checkout_session_id = 'cs_test_no_presentment_email_1';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_checkout_session_id( $order, $checkout_session_id );

		$checkout_session = $this->array_to_object(
			[
				'id'           => $checkout_session_id,
				'amount_total' => 2000,
			]
		);
		WC_Stripe_Database_Cache::set( 'checkout_session_' . $checkout_session_id, $checkout_session );

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$this->assertEmpty( $output );
	}

	/**
	 * Test that add_email_currency_conversion_notice outputs a div with the correct converted amount and exchange rate.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_outputs_notice_with_converted_amount_and_rate(): void {
		$checkout_session_id = 'cs_test_with_presentment_email_1';
		$order               = $this->create_order_with_presentment_email_data( $checkout_session_id );

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$expected_amount = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( 1500, 'eur' );
		// Rate uses major-unit amounts and is always formatted to 3 decimal places.
		$expected_rate = wc_format_decimal( (float) $expected_amount / 20.00, 3 );

		$this->assertStringContainsString( '<div', $output );
		$this->assertStringContainsString( 'Currency Conversion', $output );
		$this->assertStringContainsString( $expected_amount . ' EUR', $output );
		$this->assertStringContainsString( $expected_rate . ' EUR', $output );
		$this->assertStringContainsString( '</div>', $output );
	}

	/**
	 * Test that add_email_currency_conversion_notice outputs a div with the correct converted amount and exchange rate for the merchant.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_outputs_notice_with_converted_amount_and_rate_for_merchant(): void {
		$checkout_session_id = 'cs_test_with_presentment_email_merchant';
		$order               = $this->create_order_with_presentment_email_data( $checkout_session_id );

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order, true );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$expected_amount = WC_Stripe_Helper::get_woocommerce_amount_from_stripe_amount( 1500, 'eur' );
		// Rate uses major-unit amounts and is always formatted to 3 decimal places.
		$expected_rate = wc_format_decimal( (float) $expected_amount / 20.00, 3 );

		$this->assertStringContainsString( '<div', $output );
		$this->assertStringContainsString( 'Adaptive Pricing Applied', $output );
		$this->assertStringContainsString( $expected_amount . ' EUR', $output );
		$this->assertStringContainsString( $expected_rate . ' EUR', $output );
		$this->assertStringContainsString( '</div>', $output );
	}

	/**
	 * Test that the wc_stripe_adaptive_pricing_email_notice_styles filter allows customising the notice colours.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_respects_styles_filter(): void {
		$checkout_session_id = 'cs_test_with_presentment_email_styles';
		$order               = $this->create_order_with_presentment_email_data( $checkout_session_id );

		add_filter(
			'wc_stripe_adaptive_pricing_email_notice_styles',
			function () {
				return [
					'border-color'     => '#FF0000',
					'border-radius'    => '8px',
					'background-color' => '#FFFFFF',
				];
			}
		);

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order );
		$output = ob_get_clean();

		remove_all_filters( 'wc_stripe_adaptive_pricing_email_notice_styles' );
		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$this->assertStringContainsString( '#FF0000', $output );
		$this->assertStringContainsString( '8px', $output );
		$this->assertStringContainsString( '#FFFFFF', $output );
	}

	/**
	 * Test that add_email_currency_conversion_notice outputs plain text (no HTML) for plain-text emails.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_outputs_plain_text_for_customer(): void {
		$checkout_session_id = 'cs_test_with_presentment_email_plain_customer';
		$order               = $this->create_order_with_presentment_email_data( $checkout_session_id );

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order, false, true );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$this->assertStringNotContainsString( '<div', $output );
		$this->assertStringNotContainsString( '<p', $output );
		$this->assertStringContainsString( 'Currency Conversion', $output );
	}

	/**
	 * Test that add_email_currency_conversion_notice outputs plain text (no HTML) for plain-text admin emails.
	 *
	 * @return void
	 */
	public function test_add_email_currency_conversion_notice_outputs_plain_text_for_admin(): void {
		$checkout_session_id = 'cs_test_with_presentment_email_plain_admin';
		$order               = $this->create_order_with_presentment_email_data( $checkout_session_id );

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order, true, true );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$this->assertStringNotContainsString( '<div', $output );
		$this->assertStringNotContainsString( '<p', $output );
		$this->assertStringContainsString( 'Adaptive Pricing Applied', $output );
	}

	// =========================================================================
	// Tests for get_tokens() — early return for non-logged-in users (10.6.0)
	// =========================================================================

	/**
	 * When no user is logged in, `get_tokens()` must return whatever `parent::get_tokens()`
	 * returns immediately, without trying to collect sub-gateway tokens (which would fail
	 * or produce incorrect results for guest sessions).
	 *
	 * @return void
	 */
	public function test_get_tokens_returns_parent_tokens_when_not_logged_in(): void {
		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		// Enable OCS so the inner sub-gateway logic *would* run if the early-return
		// guard were absent.
		$this->mock_gateway->oc_enabled = true;

		$tokens = $this->mock_gateway->get_tokens();

		// For a guest session, parent::get_tokens() returns an empty array because
		// WooCommerce's get_customer_tokens() only returns tokens for logged-in users.
		$this->assertIsArray( $tokens );
		$this->assertEmpty( $tokens, 'get_tokens() should return no tokens for a guest user even when OCS is enabled.' );
	}

	/**
	 * Data provider for order currency conversion notice ECB sentence by billing country.
	 *
	 * @return array<string, array{billing_country: string, expect_ecb_sentence: bool}>
	 */
	public function provide_add_currency_conversion_notice_order_eea_matrix(): array {
		return [
			'EEA customer (Germany)'           => [
				[
					'billing_country'     => 'DE',
					'expect_ecb_sentence' => true,
				],
			],
			'non-EEA customer (United States)' => [
				[
					'billing_country'     => 'US',
					'expect_ecb_sentence' => false,
				],
			],
		];
	}

	/**
	 * @dataProvider provide_add_currency_conversion_notice_order_eea_matrix
	 *
	 * @param array{billing_country: string, expect_ecb_sentence: bool} $test_case Row from the matrix.
	 */
	public function test_add_currency_conversion_notice_ecb_sentence_by_order_country( array $test_case ): void {
		$billing_country     = $test_case['billing_country'];
		$expect_ecb_sentence = $test_case['expect_ecb_sentence'];

		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$order->set_total( 20 );
		$order->set_billing_country( $billing_country );
		$order->save();

		$checkout_session_id = sprintf( 'cs_test_order_ecb_mtx_%s', strtolower( $billing_country ) );
		$order_helper        = WC_Stripe_Order_Helper::get_instance();
		$order_helper->update_stripe_checkout_session_id( $order, $checkout_session_id );
		$order_helper->update_stripe_presentment_amount( $order, 1500 );
		$order_helper->update_stripe_presentment_currency( $order, 'eur' );

		ob_start();
		$this->mock_gateway->add_currency_conversion_notice( $order );
		$output = ob_get_clean();

		$ecb_needle = 'European Central Bank (ECB) interbank rate';
		if ( $expect_ecb_sentence ) {
			$this->assertStringContainsString( $ecb_needle, $output );
		} else {
			$this->assertStringNotContainsString( $ecb_needle, $output );
		}
	}

	/**
	 * Data provider for add_email_currency_conversion_notice ECB sentence: EEA vs non-EEA × customer vs admin × HTML vs plain text.
	 *
	 * @return array<string, array{billing_country: string, sent_to_admin: bool, plain_text: bool, expect_ecb_sentence: bool}>
	 */
	public function provide_add_email_currency_conversion_notice_ecb_matrix(): array {
		return [
			'customer HTML, EEA (France)'              => [
				[
					'billing_country'     => 'FR',
					'sent_to_admin'       => false,
					'plain_text'          => false,
					'expect_ecb_sentence' => true,
				],
			],
			'customer plain text, EEA (Netherlands)'   => [
				[
					'billing_country'     => 'NL',
					'sent_to_admin'       => false,
					'plain_text'          => true,
					'expect_ecb_sentence' => true,
				],
			],
			'customer HTML, non-EEA (Canada)'          => [
				[
					'billing_country'     => 'CA',
					'sent_to_admin'       => false,
					'plain_text'          => false,
					'expect_ecb_sentence' => false,
				],
			],
			'customer plain text, non-EEA (Australia)' => [
				[
					'billing_country'     => 'AU',
					'sent_to_admin'       => false,
					'plain_text'          => true,
					'expect_ecb_sentence' => false,
				],
			],
			'admin HTML, EEA (Germany)'                => [
				[
					'billing_country'     => 'DE',
					'sent_to_admin'       => true,
					'plain_text'          => false,
					'expect_ecb_sentence' => false,
				],
			],
			'admin plain text, EEA (Germany)'          => [
				[
					'billing_country'     => 'DE',
					'sent_to_admin'       => true,
					'plain_text'          => true,
					'expect_ecb_sentence' => false,
				],
			],
		];
	}

	/**
	 * Tests that `javascript_params()` includes `showStripeDeveloperWidget` only in test mode
	 * when the `wc_stripe_show_stripe_developer_widget` filter returns true.
	 *
	 * @dataProvider provide_test_javascript_params_stripe_developer_widget
	 *
	 * @param bool       $testmode           Whether the gateway is in test mode.
	 * @param mixed|null $filter_return       Value returned by the filter, or null to skip adding the filter.
	 * @param bool       $expected_in_params  Whether `showStripeDeveloperWidget` should be present in the params.
	 */
	public function test_javascript_params_stripe_developer_widget( bool $testmode, $filter_return, bool $expected_in_params ): void {
		$gateway           = $this->create_gateway_mock_for_javascript_params();
		$gateway->testmode = $testmode;

		$filter_callback = null;
		if ( null !== $filter_return ) {
			$filter_callback = function () use ( $filter_return ) {
				return $filter_return;
			};
			add_filter( 'wc_stripe_show_stripe_developer_widget', $filter_callback );
		}

		$params = $gateway->javascript_params();

		if ( null !== $filter_callback ) {
			remove_filter( 'wc_stripe_show_stripe_developer_widget', $filter_callback );
		}

		if ( $expected_in_params ) {
			$this->assertArrayHasKey( 'showStripeDeveloperWidget', $params );
			$this->assertTrue( $params['showStripeDeveloperWidget'] );
		} else {
			$this->assertArrayNotHasKey( 'showStripeDeveloperWidget', $params );
		}
	}

	/**
	 * Data provider for `test_javascript_params_stripe_developer_widget`.
	 *
	 * @return array[]
	 */
	public function provide_test_javascript_params_stripe_developer_widget(): array {
		return [
			'test mode, no filter hooked — key is omitted'     => [
				'testmode'           => true,
				'filter_return'      => null,
				'expected_in_params' => false,
			],
			'test mode, filter returns false — key is omitted' => [
				'testmode'           => true,
				'filter_return'      => false,
				'expected_in_params' => false,
			],
			'test mode, filter returns true — key is present'  => [
				'testmode'           => true,
				'filter_return'      => true,
				'expected_in_params' => true,
			],
			'live mode, filter returns true — key is omitted'  => [
				'testmode'           => false,
				'filter_return'      => true,
				'expected_in_params' => false,
			],
		];
	}

	/**
	 * @dataProvider provide_add_email_currency_conversion_notice_ecb_matrix
	 *
	 * @param array{billing_country: string, sent_to_admin: bool, plain_text: bool, expect_ecb_sentence: bool} $case Row from the matrix.
	 */
	public function test_add_email_currency_conversion_notice_ecb_sentence_by_context( array $case ): void {
		$billing_country     = $case['billing_country'];
		$sent_to_admin       = $case['sent_to_admin'];
		$plain_text          = $case['plain_text'];
		$expect_ecb_sentence = $case['expect_ecb_sentence'];

		$checkout_session_id = sprintf(
			'cs_test_ecb_mtx_%s_%d_%d',
			$billing_country,
			(int) $sent_to_admin,
			(int) $plain_text
		);
		$order               = $this->create_order_with_presentment_email_data( $checkout_session_id );
		$order->set_billing_country( $billing_country );
		$order->save();

		ob_start();
		$this->mock_gateway->add_email_currency_conversion_notice( $order, $sent_to_admin, $plain_text );
		$output = ob_get_clean();

		WC_Stripe_Database_Cache::delete( 'checkout_session_' . $checkout_session_id );

		$ecb_needle = 'European Central Bank (ECB) interbank rate';
		if ( $expect_ecb_sentence ) {
			$this->assertStringContainsString( $ecb_needle, $output );
		} else {
			$this->assertStringNotContainsString( $ecb_needle, $output );
		}
	}

	/**
	 * Test that APM payments include the full statement descriptor from local settings.
	 *
	 * @dataProvider provide_statement_descriptor_scenarios
	 *
	 * @param string $payment_method_post   The POST payment_method value (e.g. 'stripe_sepa_debit').
	 * @param string $local_descriptor      The locally configured statement descriptor.
	 * @param array  $account_data          The Stripe account data to mock.
	 * @param bool   $short_descriptor_on   Whether is_short_statement_descriptor_enabled is 'yes'.
	 * @param bool   $expect_full           Whether statement_descriptor should be set.
	 * @param bool   $expect_suffix         Whether statement_descriptor_suffix should be set.
	 * @param string $expected_value        The expected statement_descriptor value (if applicable).
	 */
	public function test_statement_descriptor_for_payment_types(
		string $payment_method_post,
		string $local_descriptor,
		array $account_data,
		bool $short_descriptor_on,
		bool $expect_full,
		bool $expect_suffix,
		string $expected_value
	) {
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();
		$customer_id = 'cus_mock';

		// SEPA billing-country validation requires a SEPA-zone country; DE works for all payment types.
		$order->set_billing_country( 'DE' );
		$order->save();

		// Configure the statement descriptor settings.
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['statement_descriptor'] = $local_descriptor;
		$stripe_settings['is_short_statement_descriptor_enabled'] = $short_descriptor_on ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Re-create mock gateway so it picks up updated settings.
		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods(
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
					'update_saved_payment_method',
				]
			)
			->getMock();

		$this->mock_gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );

		$this->mock_gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->onlyMethods( [ 'create_and_confirm_payment_intent', 'update_and_confirm_payment_intent', 'create_and_confirm_setup_intent' ] )
			->getMock();

		// Mock account data for fallback scenarios.
		$this->set_stripe_account_data( $account_data );

		$_POST = [
			'payment_method'           => $payment_method_post,
			'wc-stripe-payment-method' => 'pm_mock',
		];

		$captured_payment_info = null;

		$mock_intent = (object) array_merge(
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE,
			[
				'payment_method' => 'pm_mock',
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
			->with(
				$this->callback(
					function ( $payment_info ) use ( &$captured_payment_info ) {
						$captured_payment_info = $payment_info;
						return true;
					}
				)
			)
			->willReturn( $mock_intent );

		$this->mock_gateway
			->method( 'get_stripe_customer_id' )
			->willReturn( $customer_id );

		$this->mock_gateway
			->method( 'get_latest_charge_from_intent' )
			->willReturn(
				(object) [
					'id'                     => 'ch_mock',
					'captured'               => true,
					'status'                 => 'succeeded',
					'payment_method_details' => (object) [],
				]
			);

		$this->mock_gateway->process_payment( $order_id );

		$this->assertNotNull( $captured_payment_info, 'Payment info should have been captured.' );

		if ( $expect_full ) {
			$this->assertArrayHasKey( 'statement_descriptor', $captured_payment_info );
			$this->assertEquals( $expected_value, $captured_payment_info['statement_descriptor'] );
		} else {
			$this->assertArrayNotHasKey( 'statement_descriptor', $captured_payment_info );
		}

		if ( $expect_suffix ) {
			$this->assertArrayHasKey( 'statement_descriptor_suffix', $captured_payment_info );
		} else {
			$this->assertArrayNotHasKey( 'statement_descriptor_suffix', $captured_payment_info );
		}
	}

	/**
	 * Tests that get_address_data_for_payment_request includes the shipping phone, so it reaches
	 * the payment intent shipping object for risk decisioning (STRIPE-973).
	 *
	 * @dataProvider provide_test_get_address_data_for_payment_request_phone_cases
	 *
	 * @param string $phone        The order shipping phone.
	 * @param bool   $expect_phone Whether the shipping phone is expected in the address data.
	 */
	public function test_get_address_data_for_payment_request_includes_shipping_phone( string $phone, bool $expect_phone ) {
		$order = WC_Helper_Order::create_order();
		$order->set_shipping_first_name( 'Jane' );
		$order->set_shipping_last_name( 'Doe' );
		$order->set_shipping_address_1( '123 Ship St' );
		$order->set_shipping_city( 'Shipville' );
		$order->set_shipping_state( 'CA' );
		$order->set_shipping_postcode( '90210' );
		$order->set_shipping_country( 'US' );
		$order->set_shipping_phone( $phone );
		$order->save();

		$reflection = new \ReflectionClass( WC_Stripe_UPE_Payment_Gateway::class );
		$method     = $reflection->getMethod( 'get_address_data_for_payment_request' );
		$method->setAccessible( true );

		$address_data = $method->invoke( $this->mock_gateway, $order );

		if ( $expect_phone ) {
			$this->assertArrayHasKey( 'phone', $address_data, 'Shipping address data should include the shipping phone' );
			$this->assertEquals( $phone, $address_data['phone'], 'Shipping phone should match the order shipping phone' );
		} else {
			$this->assertArrayNotHasKey( 'phone', $address_data, 'Shipping address data should omit an empty phone' );
		}
	}

	/**
	 * Test that statement_descriptor is set in payment_information when using the intent update path.
	 *
	 * Note: The Stripe /confirm endpoint does not accept statement_descriptor, so the intent
	 * controller intentionally does not forward it. This test verifies the gateway still sets
	 * the value in payment_information for the update path.
	 */
	public function test_statement_descriptor_set_in_payment_info_on_intent_update() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// SEPA billing-country validation requires a SEPA-zone country.
		$order->set_billing_country( 'DE' );
		$order->save();

		// Configure a local statement descriptor.
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['statement_descriptor'] = 'MY STORE NAME';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Re-create mock gateway to pick up settings.
		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods(
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
					'update_saved_payment_method',
				]
			)
			->getMock();

		$this->mock_gateway->method( 'get_return_url' )->willReturn( self::MOCK_RETURN_URL );

		$this->mock_gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->onlyMethods( [ 'create_and_confirm_payment_intent', 'update_and_confirm_payment_intent', 'create_and_confirm_setup_intent' ] )
			->getMock();

		list( $amount ) = $this->get_order_details( $order );

		// Create an existing intent with requires_payment_method status and matching SEPA type/amount
		// so that get_existing_compatible_payment_intent returns it.
		$mock_existing_intent = (object) wp_parse_args(
			[
				'id'                   => 'pi_existing',
				'payment_method'       => 'pm_mock',
				'payment_method_types' => [ WC_Stripe_Payment_Methods::SEPA_DEBIT ],
				'status'               => WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD,
				'amount'               => $amount,
				'charges'              => (object) [
					'data' => [
						(object) [
							'id'       => 'ch_mock',
							'captured' => true,
							'status'   => 'succeeded',
						],
					],
				],
			],
			self::MOCK_CARD_PAYMENT_INTENT_TEMPLATE
		);

		$mock_payment_method = (object) self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE;

		$_POST = [
			'payment_method'           => 'stripe_sepa_debit',
			'wc-stripe-payment-method' => 'pm_mock',
		];

		// Return the existing intent on both calls to get_intent_from_order.
		$this->mock_gateway
			->method( 'get_intent_from_order' )
			->willReturn( $mock_existing_intent );

		// Mock stripe_request for payment method retrieval and intent status check.
		$this->mock_gateway
			->method( 'stripe_request' )
			->willReturnCallback(
				function ( $path ) use ( $mock_payment_method, $mock_existing_intent ) {
					if ( strpos( $path, 'payment_methods/' ) === 0 ) {
						return $mock_payment_method;
					}
					if ( strpos( $path, 'payment_intents/' ) === 0 ) {
						return $mock_existing_intent;
					}
					return (object) [];
				}
			);

		$this->mock_gateway
			->method( 'get_stripe_customer_id' )
			->willReturn( 'cus_mock' );

		// The update path should be called (not create), and it should include statement_descriptor.
		$captured_payment_info = null;

		$mock_result_intent = (object) wp_parse_args(
			[
				'status' => WC_Stripe_Intent_Status::SUCCEEDED,
			],
			(array) $mock_existing_intent
		);

		$this->mock_gateway->intent_controller
			->expects( $this->once() )
			->method( 'update_and_confirm_payment_intent' )
			->with(
				$this->anything(),
				$this->callback(
					function ( $payment_info ) use ( &$captured_payment_info ) {
						$captured_payment_info = $payment_info;
						return true;
					}
				)
			)
			->willReturn( $mock_result_intent );

		$this->mock_gateway->intent_controller
			->expects( $this->never() )
			->method( 'create_and_confirm_payment_intent' );

		$this->mock_gateway
			->method( 'get_latest_charge_from_intent' )
			->willReturn(
				(object) [
					'id'                     => 'ch_mock',
					'captured'               => true,
					'status'                 => 'succeeded',
					'payment_method_details' => (object) [],
				]
			);

		$this->mock_gateway->process_payment( $order_id );

		$this->assertNotNull( $captured_payment_info, 'Payment info should have been captured via update path.' );
		$this->assertArrayHasKey( 'statement_descriptor', $captured_payment_info );
		$this->assertEquals( 'MY STORE NAME', $captured_payment_info['statement_descriptor'] );
		$this->assertArrayNotHasKey( 'statement_descriptor_suffix', $captured_payment_info );
	}

	/**
	 * Data provider for test_statement_descriptor_for_payment_types.
	 */
	public function provide_statement_descriptor_scenarios() {
		$account_with_descriptor    = [
			'country'  => 'US',
			'settings' => [
				'payments' => [
					'statement_descriptor' => 'ACME FROM STRIPE',
				],
			],
		];
		$account_without_descriptor = [
			'country' => 'US',
		];

		return [
			'APM with local descriptor'                    => [
				'payment_method_post' => 'stripe_sepa_debit',
				'local_descriptor'    => 'MY STORE NAME',
				'account_data'        => $account_with_descriptor,
				'short_descriptor_on' => false,
				'expect_full'         => true,
				'expect_suffix'       => false,
				'expected_value'      => 'MY STORE NAME',
			],
			'APM falls back to account descriptor'         => [
				'payment_method_post' => 'stripe_sepa_debit',
				'local_descriptor'    => '',
				'account_data'        => $account_with_descriptor,
				'short_descriptor_on' => false,
				'expect_full'         => true,
				'expect_suffix'       => false,
				'expected_value'      => 'ACME FROM STRIPE',
			],
			'APM with no descriptor anywhere'              => [
				'payment_method_post' => 'stripe_sepa_debit',
				'local_descriptor'    => '',
				'account_data'        => $account_without_descriptor,
				'short_descriptor_on' => false,
				'expect_full'         => false,
				'expect_suffix'       => false,
				'expected_value'      => '',
			],
			'Card with short descriptor enabled'           => [
				'payment_method_post' => 'stripe',
				'local_descriptor'    => 'MY STORE NAME',
				'account_data'        => $account_with_descriptor,
				'short_descriptor_on' => true,
				'expect_full'         => false,
				'expect_suffix'       => true,
				'expected_value'      => '',
			],
			'Card without short descriptor enabled'        => [
				'payment_method_post' => 'stripe',
				'local_descriptor'    => 'MY STORE NAME',
				'account_data'        => $account_with_descriptor,
				'short_descriptor_on' => false,
				'expect_full'         => false,
				'expect_suffix'       => false,
				'expected_value'      => '',
			],
			'APM descriptor is sanitized'                  => [
				'payment_method_post' => 'stripe_sepa_debit',
				'local_descriptor'    => '<b>MY "STORE"</b>',
				'account_data'        => $account_without_descriptor,
				'short_descriptor_on' => false,
				'expect_full'         => true,
				'expect_suffix'       => false,
				'expected_value'      => 'MY STORE',
			],
			'APM account fallback descriptor is sanitized' => [
				'payment_method_post' => 'stripe_sepa_debit',
				'local_descriptor'    => '',
				'account_data'        => [
					'country'  => 'US',
					'settings' => [
						'payments' => [
							'statement_descriptor' => '<script>ACME</script>',
						],
					],
				],
				'short_descriptor_on' => false,
				'expect_full'         => true,
				'expect_suffix'       => false,
				'expected_value'      => 'ACME',
			],
		];
	}

	/**
	 * Data provider for test_get_address_data_for_payment_request_includes_shipping_phone.
	 *
	 * @return array
	 */
	public function provide_test_get_address_data_for_payment_request_phone_cases(): array {
		return [
			'phone present' => [ '+1 555-333-4444', true ],
			'phone empty'   => [ '', false ],
		];
	}
}
