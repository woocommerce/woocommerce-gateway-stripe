<?php

/**
 * Unit tests for WC_Stripe_Blocks_Support.
 */
class WC_Stripe_Blocks_Support_Test extends WP_UnitTestCase {

	/**
	 * The gateway instance cached on the WC_Stripe singleton before a test swaps it out.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway|null
	 */
	private $original_main_gateway = null;

	/**
	 * Script handles registered by a test, deregistered in tearDown.
	 *
	 * @var string[]
	 */
	private $registered_test_scripts = [];

	/**
	 * Blocks-support instances that registered hooks via initialize(), unhooked in tearDown.
	 *
	 * @var WC_Stripe_Blocks_Support[]
	 */
	private $initialized_blocks_support = [];

	/**
	 * Replaces the gateway returned by WC_Stripe::get_main_stripe_gateway() with the given instance.
	 *
	 * The property is protected and memoized, so we inject through reflection and restore it in
	 * tearDown to keep the singleton clean for sibling tests.
	 *
	 * @param object $gateway The gateway (or mock) to inject.
	 *
	 * @return void
	 */
	private function set_main_stripe_gateway( $gateway ): void {
		$property = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$property->setAccessible( true );

		if ( ! property_exists( $this, 'original_main_gateway' ) || null === $this->original_main_gateway ) {
			$this->original_main_gateway = $property->getValue( WC_Stripe::get_instance() );
		}

		$property->setValue( WC_Stripe::get_instance(), $gateway );
	}

	/**
	 * Restores the original main gateway and removes the empty-gateways filter.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$property = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$property->setAccessible( true );
		$property->setValue( WC_Stripe::get_instance(), $this->original_main_gateway );
		$this->original_main_gateway = null;

		remove_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', 100 );

		// WP_Scripts and WP_Styles are globals shared across the suite.
		// Ensure that we clean up after each test.
		wp_dequeue_style( $this->get_block_style_handle() );
		wp_deregister_style( $this->get_block_style_handle() );

		foreach ( array_merge( [ $this->get_block_script_handle(), 'stripe' ], $this->registered_test_scripts ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
		$this->registered_test_scripts = [];

		// The constructor registers this at a priority unique to it, and it throws when it
		// fires, so a leaked instance would abort an unrelated test's checkout.
		remove_all_actions( 'woocommerce_rest_checkout_process_payment_with_context', 10000 );

		foreach ( $this->initialized_blocks_support as $blocks_support ) {
			remove_filter( 'render_block_woocommerce/checkout', [ $blocks_support, 'maybe_enqueue_blocks_style' ] );
			remove_filter( 'render_block_woocommerce/cart', [ $blocks_support, 'maybe_enqueue_blocks_style' ] );
			remove_action( 'enqueue_block_editor_assets', [ $blocks_support, 'maybe_enqueue_blocks_style_for_editor' ], 20 );
		}
		$this->initialized_blocks_support = [];

		parent::tearDown();
	}

	/**
	 * Registers a stub script and records it for teardown.
	 *
	 * @param string   $handle Script handle.
	 * @param string[] $deps   Script dependencies.
	 *
	 * @return void
	 */
	private function register_test_script( string $handle, array $deps = [] ): void {
		$this->registered_test_scripts[] = $handle;
		wp_register_script( $handle, 'https://example.org/' . $handle . '.js', $deps, '1.0.0', true );
	}

	/**
	 * Returns a blocks-support instance with its hooks registered, tracked for teardown.
	 *
	 * @return WC_Stripe_Blocks_Support
	 */
	private function get_initialized_blocks_support(): WC_Stripe_Blocks_Support {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$blocks_support->initialize();

		$this->initialized_blocks_support[] = $blocks_support;

		return $blocks_support;
	}

	/**
	 * Invokes the private get_gateway_javascript_params() on a fresh blocks-support instance.
	 *
	 * @return array
	 */
	private function invoke_get_gateway_javascript_params(): array {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$method         = new ReflectionMethod( WC_Stripe_Blocks_Support::class, 'get_gateway_javascript_params' );
		$method->setAccessible( true );

		return $method->invoke( $blocks_support );
	}

	/**
	 * In the Cart/Checkout block editor with OC enabled and card disabled, the consolidated 'stripe'
	 * gateway is unavailable and the per-method gateways are filtered out, so neither availability
	 * branch fires. The OC fallback must still build the config so the OC element registers as 'stripe'.
	 *
	 * @return void
	 */
	public function test_get_gateway_javascript_params_falls_back_to_oc_config_when_no_gateway_available(): void {
		$expected_config = [
			'shouldShowOptimizedCheckout' => true,
			'paymentMethodsConfig'        => [ WC_Stripe_Payment_Methods::OC => [ 'title' => 'Stripe' ] ],
		];

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'should_render_optimized_checkout', 'javascript_params' ] )
			->getMock();
		$gateway->method( 'should_render_optimized_checkout' )->willReturn( true );
		$gateway->method( 'javascript_params' )->willReturn( $expected_config );

		$this->set_main_stripe_gateway( $gateway );
		// Force get_available_payment_gateways() empty so both availability branches miss.
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', 100 );

		$this->assertSame( $expected_config, $this->invoke_get_gateway_javascript_params() );
	}

	/**
	 * Without the OC editor context, an unavailable gateway must yield an empty config so the
	 * fallback does not leak the gateway params onto pages where Stripe genuinely is not available.
	 *
	 * @return void
	 */
	public function test_get_gateway_javascript_params_returns_empty_when_not_optimized_checkout(): void {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'should_render_optimized_checkout', 'javascript_params' ] )
			->getMock();
		$gateway->method( 'should_render_optimized_checkout' )->willReturn( false );
		$gateway->expects( $this->never() )->method( 'javascript_params' );

		$this->set_main_stripe_gateway( $gateway );
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', 100 );

		$this->assertSame( [], $this->invoke_get_gateway_javascript_params() );
	}

	/**
	 * WooCommerce resolves payment method script dependencies on every request, so enqueuing the
	 * stylesheet from the script registration callback loaded it store-wide. It must only register.
	 *
	 * @return void
	 */
	public function test_get_payment_method_script_handles_registers_style_without_enqueuing_it(): void {
		$blocks_support = new WC_Stripe_Blocks_Support();

		$handles = $blocks_support->get_payment_method_script_handles();

		$this->assertSame( [ $this->get_block_script_handle() ], $handles );
		$this->assertTrue( wp_style_is( $this->get_block_style_handle(), 'registered' ) );
		$this->assertFalse( wp_style_is( $this->get_block_style_handle(), 'enqueued' ) );
	}

	/**
	 * Ensure RTL support is registered.
	 *
	 * @return void
	 */
	public function test_registered_style_is_flagged_for_rtl_replacement(): void {
		( new WC_Stripe_Blocks_Support() )->get_payment_method_script_handles();

		$this->assertSame( 'replace', wp_styles()->get_data( $this->get_block_style_handle(), 'rtl' ) );
	}

	/**
	 * The enqueue hangs off a render_block filter, so it must pass the rendered markup straight back.
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_blocks_style_returns_content_unchanged(): void {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$content        = '<div class="wp-block-woocommerce-checkout"></div>';

		$this->assertSame( $content, $blocks_support->maybe_enqueue_blocks_style( $content ) );
	}

	/**
	 * Our script handle is never enqueued directly - WooCommerce adds it as a dependency of
	 * the Cart and Checkout block scripts. These cases test various dependency shapes
	 * to make sure our logic handles all cases correctly.
	 *
	 * @dataProvider provider_blocks_script_dependency_shapes
	 *
	 * @param array  $scripts_to_register Handle => dependencies map of stub scripts to register.
	 * @param string $handle_to_enqueue   The handle the page enqueues.
	 * @param bool   $expected_enqueued   Whether the block stylesheet should end up enqueued.
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_blocks_style_follows_script_dependency_graph( array $scripts_to_register, string $handle_to_enqueue, bool $expected_enqueued ): void {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$blocks_support->get_payment_method_script_handles();

		foreach ( $scripts_to_register as $handle => $deps ) {
			$this->register_test_script( $handle, $deps );
		}
		wp_enqueue_script( $handle_to_enqueue );

		$blocks_support->maybe_enqueue_blocks_style( '' );

		$this->assertSame( $expected_enqueued, wp_style_is( $this->get_block_style_handle(), 'enqueued' ) );
	}

	/**
	 * @return array[]
	 */
	public function provider_blocks_script_dependency_shapes(): array {
		return [
			'directly enqueued'                => [
				[],
				$this->get_block_script_handle(),
				true,
			],
			'dependency of the checkout block' => [
				[ 'wc-checkout-block-frontend' => [ $this->get_block_script_handle() ] ],
				'wc-checkout-block-frontend',
				true,
			],
			'dependency of the cart block'     => [
				[ 'wc-cart-block-frontend' => [ $this->get_block_script_handle() ] ],
				'wc-cart-block-frontend',
				true,
			],
			'transitive dependency'            => [
				[
					'wc-checkout-block-frontend' => [ $this->get_block_script_handle() ],
					'theme-checkout-extras'      => [ 'wc-checkout-block-frontend' ],
				],
				'theme-checkout-extras',
				true,
			],
			'unrelated script only'            => [
				[ 'theme-scripts' => [] ],
				'theme-scripts',
				false,
			],
		];
	}

	public function test_maybe_enqueue_blocks_style_does_nothing_without_an_enqueued_script(): void {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$blocks_support->get_payment_method_script_handles();

		$blocks_support->maybe_enqueue_blocks_style( '' );

		$this->assertFalse( wp_style_is( $this->get_block_style_handle(), 'enqueued' ) );
	}

	public function test_maybe_enqueue_blocks_style_for_editor_enqueues_when_editor_script_is_enqueued(): void {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$blocks_support->get_payment_method_script_handles();

		$this->register_test_script( 'wc-checkout-block', [ $this->get_block_script_handle() ] );
		wp_enqueue_script( 'wc-checkout-block' );

		$blocks_support->maybe_enqueue_blocks_style_for_editor();

		$this->assertTrue( wp_style_is( $this->get_block_style_handle(), 'enqueued' ) );
	}

	/**
	 * @dataProvider provider_render_time_hooks
	 *
	 * @param string $hook              Hook name.
	 * @param string $method            Callback method on the blocks-support instance.
	 * @param int    $expected_priority Expected registration priority.
	 *
	 * @return void
	 */
	public function test_initialize_registers_render_time_hooks( string $hook, string $method, int $expected_priority ): void {
		$blocks_support = $this->get_initialized_blocks_support();

		$this->assertSame( $expected_priority, has_filter( $hook, [ $blocks_support, $method ] ) );
	}

	/**
	 * @return array[]
	 */
	public function provider_render_time_hooks(): array {
		return [
			'checkout block render' => [ 'render_block_woocommerce/checkout', 'maybe_enqueue_blocks_style', 10 ],
			'cart block render'     => [ 'render_block_woocommerce/cart', 'maybe_enqueue_blocks_style', 10 ],
			'block editor assets'   => [ 'enqueue_block_editor_assets', 'maybe_enqueue_blocks_style_for_editor', 20 ],
		];
	}

	/**
	 * WooCommerce answers 200 with an empty payment status when it never handed the payment
	 * to the gateway, which would leave the order unpaid with nothing to show the shopper.
	 *
	 * @dataProvider provider_unprocessed_stripe_payment_methods
	 *
	 * @param string $payment_method The payment method id on the payment context.
	 *
	 * @return void
	 */
	public function test_fail_unprocessed_payment_throws_for_stripe_payment_methods( string $payment_method ): void {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$order          = WC_Helper_Order::create_order();

		$context = new \Automattic\WooCommerce\StoreApi\Payments\PaymentContext();
		$context->set_payment_method( $payment_method );
		$context->set_order( $order );

		$result = new \Automattic\WooCommerce\StoreApi\Payments\PaymentResult();

		$this->expectException( Exception::class );
		$blocks_support->fail_unprocessed_payment( $context, $result );
	}

	/**
	 * @return array[]
	 */
	public function provider_unprocessed_stripe_payment_methods(): array {
		return [
			'main gateway'      => [ 'stripe' ],
			'split UPE gateway' => [ 'stripe_us_bank_account' ],
		];
	}

	/**
	 * @dataProvider provider_processed_or_foreign_payments
	 *
	 * @param string $payment_method The payment method id on the payment context.
	 * @param string $status         The status already set on the payment result.
	 *
	 * @return void
	 */
	public function test_fail_unprocessed_payment_leaves_other_payments_alone( string $payment_method, string $status ): void {
		$blocks_support = new WC_Stripe_Blocks_Support();
		$order          = WC_Helper_Order::create_order();

		$context = new \Automattic\WooCommerce\StoreApi\Payments\PaymentContext();
		$context->set_payment_method( $payment_method );
		$context->set_order( $order );

		$result = new \Automattic\WooCommerce\StoreApi\Payments\PaymentResult( $status );

		$blocks_support->fail_unprocessed_payment( $context, $result );

		$this->assertSame( $status, $result->status );
	}

	/**
	 * @return array[]
	 */
	public function provider_processed_or_foreign_payments(): array {
		return [
			'payment succeeded'   => [ 'stripe', 'success' ],
			'payment failed'      => [ 'stripe', 'failure' ],
			'another gateway'     => [ 'cheque', '' ],
			'lookalike method id' => [ 'stripey', '' ],
		];
	}

	private function get_block_script_handle(): string {
		return WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Blocks_Support::class, 'BLOCKS_SCRIPT_HANDLE', 'string' );
	}

	private function get_block_style_handle(): string {
		return WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Blocks_Support::class, 'BLOCKS_STYLE_HANDLE', 'string' );
	}
}
