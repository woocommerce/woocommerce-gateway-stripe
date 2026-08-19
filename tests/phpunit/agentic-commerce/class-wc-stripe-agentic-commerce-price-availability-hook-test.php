<?php
/**
 * Tests for the product price and availability agentic hook.
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use Automattic\WooCommerce\Enums\ProductStockStatus;
use ReflectionMethod;
use WC_Helper_Product;
use WC_Stripe_Agentic_Commerce_Price_Availability_Responder;
use WC_Stripe_Agentic_Price_Availability_Event;
use WC_Stripe_Webhook_Handler;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_Agentic_Commerce_Price_Availability_Hook_Test
 *
 * Covers WC_Stripe_Webhook_Handler::is_agentic_hook_type, the
 * process_agentic_price_availability_hook dispatch (via reflection), and
 * WC_Stripe_Agentic_Commerce_Price_Availability_Responder.
 */
class WC_Stripe_Agentic_Commerce_Price_Availability_Hook_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $handler;

	/**
	 * @var WC_Stripe_Agentic_Commerce_Price_Availability_Responder
	 */
	private $responder;

	/**
	 * @var \WC_Product[] Products to clean up in tear_down.
	 */
	private $products = [];

	/**
	 * Set up the handler and responder under test.
	 */
	public function set_up() {
		parent::set_up();

		$this->handler   = new WC_Stripe_Webhook_Handler();
		$this->responder = new WC_Stripe_Agentic_Commerce_Price_Availability_Responder();
	}

	/**
	 * Tear down created products.
	 */
	public function tear_down() {
		foreach ( $this->products as $product ) {
			$product->delete( true );
		}
		$this->products = [];

		parent::tear_down();
	}

	/**
	 * Agentic hook detection must match both delegated checkout hooks and the
	 * price/availability hook's `delegated_commerce.` prefix, and nothing else —
	 * the result decides which signing secret validates the request.
	 *
	 * @dataProvider provide_event_types
	 * @param string $event_type The event type to classify.
	 * @param bool   $expected   Whether it is an agentic hook.
	 */
	public function test_is_agentic_hook_type( string $event_type, bool $expected ) {
		$method = new ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'is_agentic_hook_type' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( $this->handler, $event_type ) );
	}

	/**
	 * Event types and their expected agentic-hook classification.
	 *
	 * @return array[]
	 */
	public function provide_event_types(): array {
		return [
			'customize checkout'       => [ 'v1.delegated_checkout.customize_checkout', true ],
			'finalize checkout'        => [ 'v1.delegated_checkout.finalize_checkout', true ],
			'price availability'       => [ 'delegated_commerce.product_price_availability', true ],
			'regular payment webhook'  => [ 'payment_intent.succeeded', false ],
			'checkout session webhook' => [ 'checkout.session.completed', false ],
			'prefix not at position 0' => [ 'x.delegated_commerce.product_price_availability', false ],
		];
	}

	/**
	 * An in-stock product with managed stock must report its status, quantity,
	 * and regular price in minor units, echoing the SKU and merchant account.
	 */
	public function test_respond_returns_price_and_availability_for_in_stock_product() {
		$product = $this->create_product( [ 'sku' => 'PA-IN-STOCK-' . uniqid() ] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 5 );
		$product->save();

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertSame( $product->get_sku(), $response['sku_id'] );
		$this->assertSame( 'acct_test_12345', $response['merchant_id'] );
		$this->assertArrayNotHasKey( 'deleted', $response );
		$this->assertSame(
			[
				'status'   => 'in_stock',
				'quantity' => 5,
			],
			$response['availability']
		);
		$this->assertSame(
			[
				'unit_amount' => 2000,
				'currency'    => 'usd',
			],
			$response['price']
		);
		$this->assertArrayNotHasKey( 'sale_price', $response );
		$this->assertIsInt( $response['as_of'] );
	}

	/**
	 * A SKU-less product synced under its numeric ID must resolve through the
	 * product-ID fallback, matching the resolver's catalog contract.
	 */
	public function test_respond_resolves_numeric_product_id_reference() {
		$product = $this->create_product( [ 'sku' => '' ] );

		$response = $this->responder->respond( $this->build_event( (string) $product->get_id() ) );

		$this->assertArrayNotHasKey( 'deleted', $response );
		$this->assertSame( 'in_stock', $response['availability']['status'] );
		$this->assertSame( 2000, $response['price']['unit_amount'] );
	}

	/**
	 * WooCommerce stock statuses must map to the hook's availability enum the
	 * same way the catalog feed maps them.
	 *
	 * @dataProvider provide_stock_statuses
	 * @param string $wc_status       The WooCommerce stock status.
	 * @param string $expected_status The expected hook availability status.
	 */
	public function test_respond_maps_stock_status( string $wc_status, string $expected_status ) {
		$product = $this->create_product( [ 'sku' => 'PA-STATUS-' . uniqid() ] );
		$product->set_stock_status( $wc_status );
		$product->save();

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertSame( $expected_status, $response['availability']['status'] );
	}

	/**
	 * WooCommerce stock statuses and their expected hook availability values.
	 *
	 * @return array[]
	 */
	public function provide_stock_statuses(): array {
		return [
			'in stock'     => [ ProductStockStatus::IN_STOCK, 'in_stock' ],
			'out of stock' => [ ProductStockStatus::OUT_OF_STOCK, 'out_of_stock' ],
			'on backorder' => [ ProductStockStatus::ON_BACKORDER, 'backorder' ],
		];
	}

	/**
	 * When stock is not managed, no quantity may be reported — mirroring the
	 * feed's blank inventory_quantity for untracked products.
	 */
	public function test_respond_omits_quantity_when_stock_not_managed() {
		$product = $this->create_product( [ 'sku' => 'PA-UNMANAGED-' . uniqid() ] );

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertArrayNotHasKey( 'quantity', $response['availability'] );
	}

	/**
	 * Price getters are filterable, so a filter returning a non-numeric or
	 * negative value must omit the price rather than quote 0.00.
	 *
	 * @dataProvider provide_invalid_filtered_values
	 * @param mixed $invalid_value The value a third-party filter returns.
	 */
	public function test_respond_omits_price_when_filtered_value_is_invalid( $invalid_value ) {
		$product = $this->create_product( [ 'sku' => 'PA-BAD-PRICE-' . uniqid() ] );

		add_filter( 'woocommerce_product_get_regular_price', fn() => $invalid_value );

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertArrayNotHasKey( 'price', $response );
	}

	/**
	 * Same guard for the sale price: an invalid filtered value must read as
	 * "no sale", not as a free product.
	 *
	 * @dataProvider provide_invalid_filtered_values
	 * @param mixed $invalid_value The value a third-party filter returns.
	 */
	public function test_respond_omits_sale_price_when_filtered_value_is_invalid( $invalid_value ) {
		$product = $this->create_product( [ 'sku' => 'PA-BAD-SALE-' . uniqid() ] );

		add_filter( 'woocommerce_product_get_sale_price', fn() => $invalid_value );

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertArrayNotHasKey( 'sale_price', $response );
	}

	/**
	 * A filtered non-numeric stock quantity must be omitted instead of being
	 * cast to 0, which would read as sold out.
	 */
	public function test_respond_omits_quantity_when_filtered_value_is_not_numeric() {
		$product = $this->create_product( [ 'sku' => 'PA-BAD-QTY-' . uniqid() ] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 5 );
		$product->save();

		add_filter( 'woocommerce_product_get_stock_quantity', fn() => 'soon' );

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertArrayNotHasKey( 'quantity', $response['availability'] );
	}

	/**
	 * Invalid values a price filter can return.
	 *
	 * @return array[]
	 */
	public function provide_invalid_filtered_values(): array {
		return [
			'false'              => [ false ],
			'null'               => [ null ],
			'non-numeric string' => [ 'contact us' ],
			'negative price'     => [ '-5.00' ],
		];
	}

	/**
	 * A product on sale must report the sale price separately from the regular
	 * price, with the configured sale date range.
	 */
	public function test_respond_includes_sale_price_with_configured_dates() {
		$product = $this->create_product( [ 'sku' => 'PA-SALE-' . uniqid() ] );
		$product->set_sale_price( '15.00' );
		$product->set_date_on_sale_from( '2026-08-01' );
		$product->set_date_on_sale_to( '2026-08-31' );
		$product->save();

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$this->assertSame( 2000, $response['price']['unit_amount'] );
		$this->assertSame(
			[
				'unit_amount' => 1500,
				'currency'    => 'usd',
				'start_date'  => '2026-08-01',
				'end_date'    => '2026-08-31',
			],
			$response['sale_price']
		);
	}

	/**
	 * A sale price without configured dates must still carry the start/end
	 * dates Stripe requires, defaulting to today and +30 days like the feed.
	 */
	public function test_respond_defaults_sale_dates_when_unset() {
		$product = $this->create_product( [ 'sku' => 'PA-SALE-OPEN-' . uniqid() ] );
		$product->set_sale_price( '15.00' );
		$product->save();

		$response = $this->responder->respond( $this->build_event( $product->get_sku() ) );

		$today = new \WC_DateTime();
		$end   = new \WC_DateTime();
		$end->modify( '+30 days' );

		$this->assertSame( $today->date_i18n( 'Y-m-d' ), $response['sale_price']['start_date'] );
		$this->assertSame( $end->date_i18n( 'Y-m-d' ), $response['sale_price']['end_date'] );
	}

	/**
	 * SKUs the catalog would not contain must be reported as deleted, with no
	 * availability or price, so agents stop offering them.
	 *
	 * @dataProvider provide_deleted_scenarios
	 * @param string $scenario Which unavailable-product scenario to build.
	 */
	public function test_respond_reports_deleted( string $scenario ) {
		switch ( $scenario ) {
			case 'unknown sku':
				$sku_id = 'PA-UNKNOWN-' . uniqid();
				break;
			case 'unpublished product':
				$product = $this->create_product( [ 'sku' => 'PA-DRAFT-' . uniqid() ] );
				$product->set_status( 'draft' );
				$product->save();
				$sku_id = $product->get_sku();
				break;
			case 'excluded from sync':
			default:
				$product = $this->create_product( [ 'sku' => 'PA-EXCLUDED-' . uniqid() ] );
				add_filter( 'woocommerce_agentic_commerce_should_sync_product', '__return_false' );
				$sku_id = $product->get_sku();
				break;
		}

		try {
			$response = $this->responder->respond( $this->build_event( $sku_id ) );
		} finally {
			remove_filter( 'woocommerce_agentic_commerce_should_sync_product', '__return_false' );
		}

		$this->assertSame(
			[
				'sku_id'      => $sku_id,
				'merchant_id' => 'acct_test_12345',
				'deleted'     => true,
			],
			$response
		);
	}

	/**
	 * Unavailable-product scenarios that must all read as deleted.
	 *
	 * @return array[]
	 */
	public function provide_deleted_scenarios(): array {
		return [
			'unknown sku'         => [ 'unknown sku' ],
			'unpublished product' => [ 'unpublished product' ],
			'excluded from sync'  => [ 'excluded from sync' ],
		];
	}

	/**
	 * The webhook handler must route the raw hook payload through the responder
	 * and produce a complete response for a real product.
	 */
	public function test_webhook_handler_processes_price_availability_hook() {
		$product = $this->create_product( [ 'sku' => 'PA-DISPATCH-' . uniqid() ] );

		$event               = $this->load_dummy_event();
		$event->data->sku_id = $product->get_sku();

		$method = new ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'process_agentic_price_availability_hook' );
		$method->setAccessible( true );

		$response = $method->invoke( $this->handler, $event );

		$this->assertSame( $product->get_sku(), $response['sku_id'] );
		$this->assertSame( 'acct_test_12345', $response['merchant_id'] );
		$this->assertSame( 'in_stock', $response['availability']['status'] );
		$this->assertSame( 2000, $response['price']['unit_amount'] );
	}

	/**
	 * The event wrapper must expose the payload fields the responder depends
	 * on, including the context that becomes the response's merchant_id.
	 */
	public function test_event_wrapper_exposes_payload_fields() {
		$event = new WC_Stripe_Agentic_Price_Availability_Event( $this->load_dummy_event() );

		$this->assertSame( 'dcpe_test_12345', $event->get_id() );
		$this->assertSame( 'delegated_commerce.product_price_availability', $event->get_type() );
		$this->assertFalse( $event->is_livemode() );
		$this->assertSame( 'PRICE-AVAIL-TEST-SKU', $event->get_sku_id() );
		$this->assertSame( 'acct_test_12345', $event->get_merchant_id() );
	}

	// ---- Helpers ----

	/**
	 * Creates a simple published product priced at 20.00 and registers it for cleanup.
	 *
	 * @param array $props Extra product props (e.g. sku).
	 * @return \WC_Product
	 */
	private function create_product( array $props = [] ): \WC_Product {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array_merge(
				[
					'regular_price' => '20.00',
					'price'         => '20.00',
				],
				$props
			)
		);

		$this->products[] = $product;

		return $product;
	}

	/**
	 * Builds a typed price/availability event for the given SKU reference.
	 *
	 * @param string $sku_id The SKU reference to request.
	 * @return WC_Stripe_Agentic_Price_Availability_Event
	 */
	private function build_event( string $sku_id ): WC_Stripe_Agentic_Price_Availability_Event {
		$event               = $this->load_dummy_event();
		$event->data->sku_id = $sku_id;

		return new WC_Stripe_Agentic_Price_Availability_Event( $event );
	}

	/**
	 * Loads the raw dummy hook payload.
	 *
	 * @return \stdClass
	 */
	private function load_dummy_event(): \stdClass {
		return json_decode(
			file_get_contents( __DIR__ . '/../dummy-data/agentic_price_availability_event.json' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		);
	}
}
