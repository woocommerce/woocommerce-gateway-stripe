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
	private $original_main_gateway;

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

		parent::tearDown();
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
}
