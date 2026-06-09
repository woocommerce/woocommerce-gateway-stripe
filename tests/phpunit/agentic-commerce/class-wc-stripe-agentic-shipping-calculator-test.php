<?php
/**
 * Tests for WC_Stripe_Agentic_Shipping_Calculator
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

require_once __DIR__ . '/trait-agentic-commerce-test-helpers.php';

use WP_UnitTestCase;
use WC_Shipping_Zone;
use WC_Stripe_Agentic_Shipping_Calculator;

/**
 * Class WC_Stripe_Agentic_Shipping_Calculator_Test
 */
class WC_Stripe_Agentic_Shipping_Calculator_Test extends WP_UnitTestCase {

	use Trait_Agentic_Commerce_Test_Helpers;

	/**
	 * @var WC_Stripe_Agentic_Shipping_Calculator
	 */
	private $calculator;

	/**
	 * @var WC_Shipping_Zone|null
	 */
	private $shipping_zone;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Shipping_Calculator' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Shipping_Calculator class not loaded' );
		}

		$this->calculator = new WC_Stripe_Agentic_Shipping_Calculator();

		$this->save_wc_options( 'woocommerce_ship_to_countries', 'woocommerce_calc_taxes' );

		$this->reset_shipping_cache();
	}

	public function tearDown(): void {
		if ( $this->shipping_zone ) {
			$this->shipping_zone->delete();
			$this->shipping_zone = null;
		}

		$this->restore_wc_options();

		$this->reset_shipping_cache();

		parent::tearDown();
	}

	/**
	 * Test that shipping disabled returns empty array.
	 */
	public function test_returns_empty_when_shipping_disabled() {
		update_option( 'woocommerce_ship_to_countries', 'disabled' );

		$event  = $this->build_event_from_products( [] );
		$result = $this->calculator->calculate( $event, 'usd' );

		$this->assertEmpty( $result );
	}

	/**
	 * Data provider for tests that assert on a matching US shipping zone.
	 *
	 * @return array<string, array{cost: float, currency: string}>
	 */
	public function matching_zone_provider(): array {
		return [
			'standard cost, lowercase currency' => [
				'cost'     => 5.00,
				'currency' => 'usd',
			],
			'decimal cost, uppercase currency'  => [
				'cost'     => 7.50,
				'currency' => 'USD',
			],
		];
	}

	/**
	 * Test that a matching zone returns correctly structured shipping options,
	 * amounts in Stripe cents format, lowercase currency, and a non-empty display_name.
	 *
	 * @dataProvider matching_zone_provider
	 */
	public function test_matching_zone_returns_valid_shipping_options( float $cost, string $currency ) {
		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'US', $cost );

		$event  = $this->build_event_from_products( [] );
		$result = $this->calculator->calculate( $event, $currency );

		$this->assertArrayHasKey( 'shipping_options', $result );
		$this->assertNotEmpty( $result['shipping_options'] );

		$option       = $result['shipping_options'][0];
		$rate_data    = $option['shipping_rate_data'];
		$fixed_amount = $rate_data['fixed_amount'];

		// Structure assertions.
		$this->assertArrayHasKey( 'shipping_rate_data', $option );
		$this->assertArrayHasKey( 'wc_rate_id', $rate_data['metadata'] );
		$this->assertNotEmpty( $rate_data['display_name'] );

		// Stripe format: tax_behavior, integer amount, lowercase currency.
		$this->assertEquals( 'inclusive', $rate_data['tax_behavior'] );
		$this->assertIsInt( $fixed_amount['amount'] );
		$this->assertGreaterThan( 0, $fixed_amount['amount'] );
		$this->assertEquals( 'usd', $fixed_amount['currency'] );
	}

	/**
	 * Test that a non-matching country returns empty or does not include the wrong zone.
	 */
	public function test_returns_empty_for_non_matching_zone() {
		$this->shipping_zone = $this->create_shipping_zone_with_flat_rate( 'DE', 5.00 );

		$event  = $this->build_event_from_products( [] ); // US address.
		$result = $this->calculator->calculate( $event, 'usd' );

		// The rest-of-world zone (zone 0) may or may not have methods,
		// but it must not include the DE-specific rate.
		if ( ! empty( $result ) && isset( $result['shipping_options'] ) ) {
			foreach ( $result['shipping_options'] as $option ) {
				$this->assertNotEquals( 'DE Flat Rate', $option['shipping_rate_data']['display_name'] );
			}
		} else {
			$this->assertEmpty( $result );
		}
	}
}
