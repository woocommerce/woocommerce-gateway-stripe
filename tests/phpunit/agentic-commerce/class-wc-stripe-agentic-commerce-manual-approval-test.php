<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Manual_Approval
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

require_once __DIR__ . '/trait-agentic-commerce-test-helpers.php';

use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Stripe_Agentic_Commerce_Manual_Approval;
use WC_Stripe_Agentic_Customize_Checkout_Event;

/**
 * Class WC_Stripe_Agentic_Commerce_Manual_Approval_Test
 *
 * Tests the manual approval validation for the finalize_checkout hook.
 */
class WC_Stripe_Agentic_Commerce_Manual_Approval_Test extends WP_UnitTestCase {

	use Trait_Agentic_Commerce_Test_Helpers;

	/**
	 * @var WC_Stripe_Agentic_Commerce_Manual_Approval
	 */
	private $approval;

	/**
	 * @var \WC_Product[]
	 */
	private array $products = [];

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Manual_Approval' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Manual_Approval class not loaded' );
		}

		$this->approval = new WC_Stripe_Agentic_Commerce_Manual_Approval();
	}

	public function tearDown(): void {
		foreach ( $this->products as $product ) {
			$product->delete( true );
		}
		$this->products = [];

		remove_all_filters( 'wc_stripe_agentic_approve_order' );

		parent::tearDown();
	}

	/**
	 * Test that a valid, in-stock product is approved.
	 */
	public function test_approves_valid_order(): void {
		$product  = $this->create_product();
		$event    = $this->build_finalize_event( [ $product ] );
		$response = $this->approval->validate( $event );

		$this->assertSame( 'approved', $response['manual_approval_details']['type'] );
		$this->assertArrayNotHasKey( 'declined', $response['manual_approval_details'] );
	}

	/**
	 * Test that multiple valid products are all approved.
	 */
	public function test_approves_multiple_valid_products(): void {
		$product1 = $this->create_product();
		$product2 = $this->create_product( [ 'regular_price' => '15.00' ] );
		$event    = $this->build_finalize_event( [ $product1, $product2 ] );
		$response = $this->approval->validate( $event );

		$this->assertSame( 'approved', $response['manual_approval_details']['type'] );
	}

	/**
	 * Test that a nonexistent product throws an exception.
	 */
	public function test_throws_on_nonexistent_product(): void {
		$event = $this->build_finalize_event_from_sku_ids( [ '999999999' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Product not found' );

		$this->approval->validate( $event );
	}

	/**
	 * Data provider for decline scenarios.
	 *
	 * Each case returns: product overrides, line item quantity, expected reason substring.
	 *
	 * @return array
	 */
	public function decline_scenarios_provider(): array {
		return [
			'out of stock'        => [
				'product_args'    => [ 'stock_status' => 'outofstock' ],
				'quantity'        => 1,
				'expected_reason' => 'is out of stock',
			],
			'insufficient stock'  => [
				'product_args'    => [
					'manage_stock'   => true,
					'stock_quantity' => 2,
				],
				'quantity'        => 5,
				'expected_reason' => 'Insufficient stock',
			],
			'zero stock quantity' => [
				'product_args'    => [
					'manage_stock'   => true,
					'stock_quantity' => 0,
				],
				'quantity'        => 1,
				'expected_reason' => 'is out of stock',
			],
		];
	}

	/**
	 * Test that various stock/availability issues result in a decline.
	 *
	 * @dataProvider decline_scenarios_provider
	 */
	public function test_declines_for_stock_issues( array $product_args, int $quantity, string $expected_reason ): void {
		$product  = $this->create_product( $product_args );
		$event    = $this->build_finalize_event( [ $product ], [ $quantity ] );
		$response = $this->approval->validate( $event );

		$this->assertSame( 'declined', $response['manual_approval_details']['type'] );
		$this->assertStringContainsString( $expected_reason, $response['manual_approval_details']['declined']['reason'] );
	}

	/**
	 * Test that an unpurchasable product is declined.
	 */
	public function test_declines_unpurchasable_product(): void {
		$product = $this->create_product( [ 'regular_price' => '' ] );
		$event   = $this->build_finalize_event( [ $product ] );

		$response = $this->approval->validate( $event );

		$this->assertSame( 'declined', $response['manual_approval_details']['type'] );
		$this->assertStringContainsString( 'is not available for purchase', $response['manual_approval_details']['declined']['reason'] );
	}

	/**
	 * Test that the first failing product in a multi-item order triggers the decline.
	 */
	public function test_declines_on_first_failing_item(): void {
		$valid_product   = $this->create_product();
		$invalid_product = $this->create_product( [ 'stock_status' => 'outofstock' ] );

		$event    = $this->build_finalize_event( [ $valid_product, $invalid_product ] );
		$response = $this->approval->validate( $event );

		$this->assertSame( 'declined', $response['manual_approval_details']['type'] );
		$this->assertStringContainsString( $invalid_product->get_name(), $response['manual_approval_details']['declined']['reason'] );
	}

	/**
	 * Data provider for filter override scenarios.
	 *
	 * @return array
	 */
	public function filter_override_provider(): array {
		return [
			'filter declines an approved order' => [
				'product_args'    => [],
				'filter_return'   => [
					'code'   => 'custom_rule',
					'reason' => 'Blocked by custom rule.',
				],
				'expected_type'   => 'declined',
				'expected_reason' => 'Blocked by custom rule.',
			],
			'filter approves a declined order'  => [
				'product_args'    => [ 'stock_status' => 'outofstock' ],
				'filter_return'   => null,
				'expected_type'   => 'approved',
				'expected_reason' => null,
			],
		];
	}

	/**
	 * Test that the wc_stripe_agentic_approve_order filter can override the decision.
	 *
	 * @dataProvider filter_override_provider
	 */
	public function test_filter_overrides_decision( array $product_args, ?array $filter_return, string $expected_type, ?string $expected_reason ): void {
		$product = $this->create_product( $product_args );
		$event   = $this->build_finalize_event( [ $product ] );

		add_filter(
			'wc_stripe_agentic_approve_order',
			function () use ( $filter_return ) {
				return $filter_return;
			}
		);

		$response = $this->approval->validate( $event );

		$this->assertSame( $expected_type, $response['manual_approval_details']['type'] );

		if ( null !== $expected_reason ) {
			$this->assertSame( $expected_reason, $response['manual_approval_details']['declined']['reason'] );
		} else {
			$this->assertArrayNotHasKey( 'declined', $response['manual_approval_details'] );
		}
	}

	/**
	 * Test that the filter receives the event object and null line item for approved orders.
	 */
	public function test_filter_receives_event_and_null_line_item(): void {
		$product            = $this->create_product();
		$event              = $this->build_finalize_event( [ $product ] );
		$captured_event     = null;
		$captured_line_item = 'not_set';

		add_filter(
			'wc_stripe_agentic_approve_order',
			function ( $decline, $evt, $line_item ) use ( &$captured_event, &$captured_line_item ) {
				$captured_event     = $evt;
				$captured_line_item = $line_item;
				return $decline;
			},
			10,
			3
		);

		$this->approval->validate( $event );

		$this->assertInstanceOf( WC_Stripe_Agentic_Customize_Checkout_Event::class, $captured_event );
		$this->assertNull( $captured_line_item );
	}

	/**
	 * Test that the filter receives the invalid line item for declined orders.
	 */
	public function test_filter_receives_invalid_line_item(): void {
		$product            = $this->create_product( [ 'stock_status' => 'outofstock' ] );
		$event              = $this->build_finalize_event( [ $product ] );
		$captured_line_item = null;

		add_filter(
			'wc_stripe_agentic_approve_order',
			function ( $decline, $evt, $line_item ) use ( &$captured_line_item ) {
				$captured_line_item = $line_item;
				return $decline;
			},
			10,
			3
		);

		$this->approval->validate( $event );

		$this->assertInstanceOf( \WC_Stripe_Agentic_Customize_Checkout_Line_Item::class, $captured_line_item );
	}

	/**
	 * Test that managed stock with sufficient quantity is approved.
	 */
	public function test_approves_managed_stock_with_sufficient_quantity(): void {
		$product = $this->create_product(
			[
				'manage_stock'   => true,
				'stock_quantity' => 10,
			]
		);

		$event    = $this->build_finalize_event( [ $product ], [ 5 ] );
		$response = $this->approval->validate( $event );

		$this->assertSame( 'approved', $response['manual_approval_details']['type'] );
	}

	/**
	 * Test that managed stock with exact quantity is approved.
	 */
	public function test_approves_exact_stock_quantity(): void {
		$product = $this->create_product(
			[
				'manage_stock'   => true,
				'stock_quantity' => 3,
			]
		);

		$event    = $this->build_finalize_event( [ $product ], [ 3 ] );
		$response = $this->approval->validate( $event );

		$this->assertSame( 'approved', $response['manual_approval_details']['type'] );
	}

	/**
	 * Creates a simple product and tracks it for cleanup.
	 *
	 * @param array $args Product property overrides.
	 * @return \WC_Product
	 */
	private function create_product( array $args = [] ): \WC_Product {
		$defaults = [
			'regular_price' => '20.00',
			'price'         => '20.00',
		];

		$product          = WC_Helper_Product::create_simple_product( true, array_merge( $defaults, $args ) );
		$this->products[] = $product;

		return $product;
	}

	/**
	 * Builds a finalize_checkout event from products.
	 *
	 * @param \WC_Product[] $products   Products to include as line items.
	 * @param int[]         $quantities Per-item quantities (defaults to 1).
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	private function build_finalize_event( array $products, array $quantities = [] ): WC_Stripe_Agentic_Customize_Checkout_Event {
		$items = [];
		foreach ( $products as $index => $product ) {
			$items[] = (object) [
				'id'       => 'li_test_' . $index,
				'sku_id'   => (string) $product->get_id(),
				'quantity' => $quantities[ $index ] ?? 1,
				'name'     => $product->get_name(),
			];
		}

		return new WC_Stripe_Agentic_Customize_Checkout_Event( $this->build_finalize_raw_event( $items ) );
	}

	/**
	 * Builds a finalize_checkout event from raw SKU IDs.
	 *
	 * @param string[] $sku_ids SKU IDs to include.
	 * @return WC_Stripe_Agentic_Customize_Checkout_Event
	 */
	private function build_finalize_event_from_sku_ids( array $sku_ids ): WC_Stripe_Agentic_Customize_Checkout_Event {
		$items = [];
		foreach ( $sku_ids as $index => $sku_id ) {
			$items[] = (object) [
				'id'       => 'li_test_' . $index,
				'sku_id'   => $sku_id,
				'quantity' => 1,
				'name'     => 'Unknown Product',
			];
		}

		return new WC_Stripe_Agentic_Customize_Checkout_Event( $this->build_finalize_raw_event( $items ) );
	}

	/**
	 * Builds a raw finalize_checkout event stdClass.
	 *
	 * @param array $line_items Raw line item objects.
	 * @return \stdClass
	 */
	private function build_finalize_raw_event( array $line_items ): \stdClass {
		return (object) [
			'id'       => 'evt_test_finalize',
			'type'     => 'v1.delegated_checkout.finalize_checkout',
			'livemode' => false,
			'data'     => (object) [
				'line_item_details' => $line_items,
				'currency'          => 'usd',
				'amount_total'      => 0,
				'shipping_details'  => (object) [
					'address' => (object) $this->default_address(),
				],
			],
		];
	}
}
