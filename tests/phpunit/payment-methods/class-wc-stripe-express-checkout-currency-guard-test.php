<?php

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Currency_Guard.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Currency_Guard
 *
 * Class WC_Stripe_Express_Checkout_Currency_Guard_Test
 */
class WC_Stripe_Express_Checkout_Currency_Guard_Test extends WP_UnitTestCase {

	private const HEADER_KEY = 'HTTP_X_WCSTRIPE_PAYMENT_CURRENCY';

	public function tearDown(): void {
		unset( $_SERVER[ self::HEADER_KEY ] );
		parent::tearDown();
	}

	private function build_guard( bool $is_ece_context ): WC_Stripe_Express_Checkout_Currency_Guard {
		$helper = $this->createMock( WC_Stripe_Express_Checkout_Helper::class );
		$helper->method( 'is_express_checkout_context' )->willReturn( $is_ece_context );

		return new WC_Stripe_Express_Checkout_Currency_Guard( $helper );
	}

	private function build_order( string $currency ): WC_Order {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( $currency );

		return $order;
	}

	/**
	 * @dataProvider provider_no_throw_scenarios
	 */
	public function test_no_throw_when( bool $is_ece_context, ?string $header_value, string $order_currency ) {
		if ( null !== $header_value ) {
			$_SERVER[ self::HEADER_KEY ] = $header_value;
		}

		$guard = $this->build_guard( $is_ece_context );
		$order = $this->build_order( $order_currency );
		$req   = $this->createMock( WP_REST_Request::class );

		$guard->maybe_assert_order_currency_matches_express_checkout_currency( $order, $req );

		$this->assertTrue( true );
	}

	public function provider_no_throw_scenarios(): array {
		return [
			'header absent (fail-open)'         => [
				'is_ece_context' => true,
				'header_value'   => null,
				'order_currency' => 'EUR',
			],
			'not ECE context (defensive scope)' => [
				'is_ece_context' => false,
				'header_value'   => 'USD',
				'order_currency' => 'EUR',
			],
			'matching currencies, same case'    => [
				'is_ece_context' => true,
				'header_value'   => 'usd',
				'order_currency' => 'usd',
			],
			'matching currencies, mixed case'   => [
				'is_ece_context' => true,
				'header_value'   => 'USD',
				'order_currency' => 'usd',
			],
			'empty header value (fail-open)'    => [
				'is_ece_context' => true,
				'header_value'   => '',
				'order_currency' => 'EUR',
			],
		];
	}

	public function test_throws_route_exception_on_mismatch() {
		$_SERVER[ self::HEADER_KEY ] = 'USD';

		$guard = $this->build_guard( true );
		$order = $this->build_order( 'EUR' );
		$req   = $this->createMock( WP_REST_Request::class );

		try {
			$guard->maybe_assert_order_currency_matches_express_checkout_currency( $order, $req );
			$this->fail( 'Expected RouteException, none thrown.' );
		} catch ( RouteException $e ) {
			$this->assertSame( 'wc_stripe_express_checkout_currency_mismatch', $e->getErrorCode() );
			$this->assertSame( 400, $e->getCode() );
			$this->assertStringContainsString( 'USD', $e->getMessage() );
			$this->assertStringContainsString( 'EUR', $e->getMessage() );
		}
	}
}
