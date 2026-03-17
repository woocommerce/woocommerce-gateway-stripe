<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Order_Mapper
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Order_Mapper_Test
 *
 * Tests the order mapper for Agentic Commerce.
 */
class WC_Stripe_Agentic_Commerce_Order_Mapper_Test extends WP_UnitTestCase {

	/**
	 * The mapper instance under test.
	 *
	 * @var WC_Stripe_Agentic_Commerce_Order_Mapper
	 */
	private $mapper;

	/**
	 * A default product used by build_checkout_session when no line_items override is provided.
	 *
	 * @var \WC_Product
	 */
	private $default_product;

	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Order_Mapper' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Order_Mapper class not loaded' );
		}

		$this->mapper          = new WC_Stripe_Agentic_Commerce_Order_Mapper();
		$this->default_product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
			]
		);
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( $this->default_product ) {
			$this->default_product->delete( true );
		}
		parent::tearDown();
	}

	/**
	 * Test creating an order from a complete checkout session.
	 *
	 * @return void
	 */
	public function test_create_order_from_complete_checkout_session() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '25.00',
				'price'         => '25.00',
			]
		);
		$session = $this->build_checkout_session(
			[
				'amount_total'    => 2500,
				'amount_subtotal' => 2500,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => (string) $product->get_id(),
							'description'     => 'Test Product',
							'quantity'        => 1,
							'unit_amount'     => 2500,
							'amount_total'    => 2500,
							'amount_subtotal' => 2500,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertInstanceOf( 'WC_Order', $order );
		$this->assertGreaterThan( 0, $order->get_id() );
		$this->assertEquals( '25.00', $order->get_total() );
		$this->assertEquals( 'processing', $order->get_status() );

		$order->delete( true );
		$product->delete( true );
	}

	/**
	 * Test that the order currency is set from the checkout session.
	 *
	 * @return void
	 */
	public function test_order_currency_is_set_from_session() {
		$session = $this->build_checkout_session( [ 'currency' => 'eur' ] );
		$order   = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'EUR', $order->get_currency() );

		$order->delete( true );
	}

	/**
	 * Test that an unsupported currency throws an exception.
	 *
	 * @return void
	 */
	public function test_exception_thrown_for_unsupported_currency() {
		$session = $this->build_checkout_session( [ 'currency' => 'zzz' ] );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'unsupported currency' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that an invalid email throws an exception.
	 *
	 * @return void
	 */
	public function test_exception_thrown_for_invalid_email() {
		$session = $this->build_checkout_session(
			[
				'customer_email'   => 'not-an-email',
				'customer_details' => (object) [
					'email' => 'not-an-email',
					'name'  => 'Test User',
					'phone' => null,
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'has no customer email' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that a missing email throws an exception.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_email_is_absent() {
		$session = $this->build_checkout_session(
			[
				'customer_email'   => null,
				'customer_details' => (object) [
					'email' => null,
					'name'  => 'No Email User',
					'phone' => null,
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'has no customer email' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that the payment method is set to Stripe.
	 *
	 * @return void
	 */
	public function test_payment_method_is_set_to_stripe() {
		$session = $this->build_checkout_session();
		$order   = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'stripe', $order->get_payment_method() );

		$order->delete( true );
	}

	/**
	 * Test that Stripe metadata is stored on the order.
	 *
	 * @return void
	 */
	public function test_stripe_metadata_is_stored() {
		$session = $this->build_checkout_session(
			[
				'payment_intent' => (object) [ 'id' => 'pi_test_metadata' ],
				'customer'       => 'cus_test_metadata',
			]
		);
		$order   = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'pi_test_metadata', $order->get_meta( '_stripe_intent_id', true ) );
		$this->assertEquals( 'cus_test_metadata', $order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( 'usd', $order->get_meta( '_stripe_currency', true ) );
		$this->assertEquals( 'cs_test_123', $order->get_meta( '_stripe_checkout_session_id', true ) );

		$order->delete( true );
	}

	/**
	 * Test that the order status is set to processing.
	 *
	 * @return void
	 */
	public function test_order_status_is_processing() {
		$session = $this->build_checkout_session();
		$order   = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'processing', $order->get_status() );

		$order->delete( true );
	}

	/**
	 * Test that an existing customer is linked by email.
	 *
	 * @return void
	 */
	public function test_existing_customer_linked_by_email() {
		$user_id = $this->factory->user->create( [ 'user_email' => 'existing@example.com' ] );
		$session = $this->build_checkout_session(
			[
				'customer_email'   => 'existing@example.com',
				'customer_details' => (object) [
					'email'   => 'existing@example.com',
					'name'    => 'Existing User',
					'phone'   => null,
					'address' => (object) [
						'line1'       => '123 Main St',
						'city'        => 'Anytown',
						'state'       => 'CA',
						'postal_code' => '90210',
						'country'     => 'US',
					],
				],
			]
		);

		$order       = $this->mapper->create_order_from_checkout_session( $session );
		$customer_id = $order->get_customer_id();

		$order->delete( true );
		wp_delete_user( $user_id );

		$this->assertEquals( $user_id, $customer_id );
	}

	/**
	 * Test that a guest order is created when no matching user exists.
	 *
	 * @return void
	 */
	public function test_guest_order_created_when_no_matching_user() {
		$session = $this->build_checkout_session(
			[
				'customer_email'   => 'nonexistent@example.com',
				'customer_details' => (object) [
					'email'   => 'nonexistent@example.com',
					'name'    => 'Guest User',
					'phone'   => null,
					'address' => (object) [
						'line1'       => '123 Main St',
						'city'        => 'Anytown',
						'state'       => 'CA',
						'postal_code' => '90210',
						'country'     => 'US',
					],
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 0, $order->get_customer_id() );
		$this->assertEquals( 'nonexistent@example.com', $order->get_billing_email() );

		$order->delete( true );
	}

	/**
	 * Test that line items are mapped with a known product.
	 *
	 * @return void
	 */
	public function test_line_items_mapped_with_known_product() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '15.00',
				'price'         => '15.00',
			]
		);
		$session = $this->build_checkout_session(
			[
				'amount_total'    => 1500,
				'amount_subtotal' => 1500,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => (string) $product->get_id(),
							'description'     => 'Test Product',
							'quantity'        => 1,
							'unit_amount'     => 1500,
							'amount_total'    => 1500,
							'amount_subtotal' => 1500,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$expected_product_id = $product->get_id();
		$order               = $this->mapper->create_order_from_checkout_session( $session );
		$items               = $order->get_items();
		$item                = reset( $items );
		$product_id          = $item instanceof WC_Order_Item_Product ? $item->get_product_id() : null;
		$quantity            = $item instanceof WC_Order_Item_Product ? $item->get_quantity() : null;
		$total               = $item instanceof WC_Order_Item_Product ? wc_format_decimal( $item->get_total(), 2 ) : null;

		$order->delete( true );
		$product->delete( true );

		$this->assertCount( 1, $items );
		$this->assertInstanceOf( WC_Order_Item_Product::class, $item );
		$this->assertEquals( $expected_product_id, $product_id );
		$this->assertEquals( 1, $quantity );
		$this->assertEquals( '15.00', $total );
	}

	/**
	 * Test that an exception is thrown when a lookup_key does not resolve to a product.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_product_not_found_for_lookup_key() {
		$session = $this->build_checkout_session(
			[
				'amount_total'    => 999,
				'amount_subtotal' => 999,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => '99999999',
							'description'     => 'Unknown Widget',
							'quantity'        => 1,
							'unit_amount'     => 999,
							'amount_total'    => 999,
							'amount_subtotal' => 999,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Product not found for lookup_key "99999999"' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that an exception is thrown when a line item has no product ID.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_line_item_has_no_product_id() {
		$session = $this->build_checkout_session(
			[
				'amount_total'    => 999,
				'amount_subtotal' => 999,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => null,
							'description'     => 'Ad-hoc Item',
							'quantity'        => 1,
							'unit_amount'     => 999,
							'amount_total'    => 999,
							'amount_subtotal' => 999,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'has no integer (product ID) lookup_key' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that line item quantity is preserved.
	 *
	 * @return void
	 */
	public function test_line_item_quantity_is_preserved() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
			]
		);
		$session = $this->build_checkout_session(
			[
				'amount_total'    => 3000,
				'amount_subtotal' => 3000,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => (string) $product->get_id(),
							'description'     => 'Test Product',
							'quantity'        => 3,
							'unit_amount'     => 1000,
							'amount_total'    => 3000,
							'amount_subtotal' => 3000,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );
		$items = $order->get_items();
		$item  = reset( $items );

		$this->assertEquals( 3, $item->get_quantity() );
		$this->assertEquals( '30.00', wc_format_decimal( $item->get_total(), 2 ) );

		$order->delete( true );
		$product->delete( true );
	}

	/**
	 * Test currency conversion from Stripe amounts.
	 *
	 * Each case creates a product whose WC price matches the expected converted
	 * Stripe amount, then verifies the order total after the mapper runs.
	 *
	 * @dataProvider data_provider_currency_conversion
	 *
	 * @param float  $product_price   The WC product price.
	 * @param int    $stripe_amount   The Stripe amount in smallest currency unit.
	 * @param string $currency        The currency code.
	 * @param float  $expected_amount The expected WC order total.
	 * @return void
	 */
	public function test_currency_conversion( float $product_price, int $stripe_amount, string $currency, float $expected_amount ) {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => (string) $product_price,
				'price'         => (string) $product_price,
			]
		);

		$session = $this->build_checkout_session(
			[
				'currency'        => $currency,
				'amount_total'    => $stripe_amount,
				'amount_subtotal' => $stripe_amount,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => (string) $product->get_id(),
							'description'     => 'Test',
							'quantity'        => 1,
							'unit_amount'     => $stripe_amount,
							'amount_total'    => $stripe_amount,
							'amount_subtotal' => $stripe_amount,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals(
			$expected_amount,
			(float) $order->get_total(),
			"Failed converting $stripe_amount $currency to $expected_amount"
		);

		$order->delete( true );
		$product->delete( true );
	}

	/**
	 * Data provider for currency conversion tests.
	 *
	 * @return array<string, array{float, int, string, float}>
	 */
	public function data_provider_currency_conversion(): array {
		return [
			'USD standard'      => [ 10.00, 1000, 'usd', 10.00 ],
			'EUR cents'         => [ 0.99, 99, 'eur', 0.99 ],
			'JPY no-decimal'    => [ 1000.00, 1000, 'jpy', 1000.00 ],
			'BHD three-decimal' => [ 1.00, 1000, 'bhd', 1.00 ],
		];
	}

	/**
	 * Test splitting full names into first and last.
	 *
	 * We test name splitting through the address mapping by checking the
	 * billing first and last name on the created order.
	 *
	 * @dataProvider data_provider_name_splitting
	 *
	 * @param string $full_name      The full name from Stripe.
	 * @param string $expected_first Expected first name.
	 * @param string $expected_last  Expected last name.
	 * @return void
	 */
	public function test_name_splitting( string $full_name, string $expected_first, string $expected_last ) {
		$session = $this->build_checkout_session(
			[
				'customer_details' => (object) [
					'email'   => 'test@example.com',
					'name'    => $full_name,
					'phone'   => null,
					'address' => (object) [
						'line1'       => '123 Main St',
						'city'        => 'Anytown',
						'state'       => 'CA',
						'postal_code' => '90210',
						'country'     => 'US',
					],
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( $expected_first, $order->get_billing_first_name() );
		$this->assertEquals( $expected_last, $order->get_billing_last_name() );

		$order->delete( true );
	}

	/**
	 * Data provider for name splitting tests.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public function data_provider_name_splitting(): array {
		return [
			'two parts'       => [ 'John Smith', 'John', 'Smith' ],
			'first name only' => [ 'John', 'John', '' ],
			'three parts'     => [ 'John Paul Smith', 'John', 'Paul Smith' ],
			'empty string'    => [ '', '', '' ],
		];
	}

	/**
	 * Test that billing name falls back to shipping_details.name when
	 * customer_details.name is null (as happens in real webhook payloads).
	 *
	 * @return void
	 */
	public function test_billing_name_falls_back_to_shipping_details_name() {
		$session = $this->build_checkout_session(
			[
				'customer_details' => (object) [
					'email'   => 'test@example.com',
					'name'    => null,
					'phone'   => '+12015550134',
					'address' => (object) [
						'line1'       => '1017 Wealthy Street Southeast',
						'line2'       => null,
						'city'        => 'Grand Rapids',
						'state'       => 'MI',
						'postal_code' => '49506',
						'country'     => 'US',
					],
				],
				'shipping_details' => (object) [
					'name'    => 'Radoslav Georgiev',
					'address' => (object) [
						'line1'       => '1017 Wealthy Street Southeast',
						'line2'       => null,
						'city'        => 'Grand Rapids',
						'state'       => 'MI',
						'postal_code' => '49506',
						'country'     => 'US',
					],
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'Radoslav', $order->get_billing_first_name() );
		$this->assertEquals( 'Georgiev', $order->get_billing_last_name() );

		$order->delete( true );
	}

	/**
	 * Test that billing name and shipping address fall back to
	 * collected_information.shipping_details when top-level
	 * shipping_details is absent.
	 *
	 * @return void
	 */
	public function test_addresses_fall_back_to_collected_information() {
		$session = $this->build_checkout_session(
			[
				'customer_details'      => (object) [
					'email'   => 'test@example.com',
					'name'    => null,
					'phone'   => '+12015550134',
					'address' => (object) [
						'line1'       => '1017 Wealthy Street Southeast',
						'line2'       => null,
						'city'        => 'Grand Rapids',
						'state'       => 'MI',
						'postal_code' => '49506',
						'country'     => 'US',
					],
				],
				'shipping_details'      => null,
				'collected_information' => (object) [
					'shipping_details' => (object) [
						'name'    => 'Radoslav Georgiev',
						'address' => (object) [
							'line1'       => '500 Market St',
							'line2'       => null,
							'city'        => 'San Francisco',
							'state'       => 'CA',
							'postal_code' => '94105',
							'country'     => 'US',
						],
					],
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		// Billing name falls back to collected_information.shipping_details.name.
		$this->assertEquals( 'Radoslav', $order->get_billing_first_name() );
		$this->assertEquals( 'Georgiev', $order->get_billing_last_name() );

		// Shipping address comes from collected_information.shipping_details.
		$this->assertEquals( 'Radoslav', $order->get_shipping_first_name() );
		$this->assertEquals( 'Georgiev', $order->get_shipping_last_name() );
		$this->assertEquals( '500 Market St', $order->get_shipping_address_1() );
		$this->assertEquals( 'San Francisco', $order->get_shipping_city() );
		$this->assertEquals( 'CA', $order->get_shipping_state() );
		$this->assertEquals( '94105', $order->get_shipping_postcode() );
		$this->assertEquals( 'US', $order->get_shipping_country() );

		$order->delete( true );
	}

	/**
	 * Test that the billing address is mapped from customer_details.
	 *
	 * @return void
	 */
	public function test_billing_address_mapped_from_customer_details() {
		$session = $this->build_checkout_session(
			[
				'customer_details' => (object) [
					'email'   => 'billing@example.com',
					'name'    => 'Jane Doe',
					'phone'   => '+15551234567',
					'address' => (object) [
						'line1'       => '123 Main St',
						'line2'       => 'Apt 4B',
						'city'        => 'New York',
						'state'       => 'NY',
						'postal_code' => '10001',
						'country'     => 'US',
					],
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'Jane', $order->get_billing_first_name() );
		$this->assertEquals( 'Doe', $order->get_billing_last_name() );
		$this->assertEquals( 'billing@example.com', $order->get_billing_email() );
		$this->assertEquals( '+15551234567', $order->get_billing_phone() );
		$this->assertEquals( '123 Main St', $order->get_billing_address_1() );
		$this->assertEquals( 'Apt 4B', $order->get_billing_address_2() );
		$this->assertEquals( 'New York', $order->get_billing_city() );
		$this->assertEquals( 'NY', $order->get_billing_state() );
		$this->assertEquals( '10001', $order->get_billing_postcode() );
		$this->assertEquals( 'US', $order->get_billing_country() );

		$order->delete( true );
	}

	/**
	 * Test that the shipping address is mapped from shipping_details.
	 *
	 * @return void
	 */
	public function test_shipping_address_mapped_from_shipping_details() {
		$session = $this->build_checkout_session(
			[
				'shipping_details' => (object) [
					'name'    => 'Bob Jones',
					'address' => (object) [
						'line1'       => '456 Oak Ave',
						'line2'       => '',
						'city'        => 'Chicago',
						'state'       => 'IL',
						'postal_code' => '60601',
						'country'     => 'US',
					],
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertEquals( 'Bob', $order->get_shipping_first_name() );
		$this->assertEquals( 'Jones', $order->get_shipping_last_name() );
		$this->assertEquals( '456 Oak Ave', $order->get_shipping_address_1() );
		$this->assertEquals( 'Chicago', $order->get_shipping_city() );
		$this->assertEquals( 'IL', $order->get_shipping_state() );
		$this->assertEquals( '60601', $order->get_shipping_postcode() );
		$this->assertEquals( 'US', $order->get_shipping_country() );

		$order->delete( true );
	}

	/**
	 * Test that missing billing address throws an exception.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_billing_address_missing() {
		$session = $this->build_checkout_session(
			[
				'customer_details' => (object) [
					'email' => 'test@example.com',
					'name'  => 'Test',
					'phone' => null,
				],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'no billing address' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that missing shipping details creates an order without shipping address (digital goods).
	 *
	 * @return void
	 */
	public function test_order_created_without_shipping_details() {
		$session = $this->build_checkout_session(
			[
				'shipping_details' => null,
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$this->assertInstanceOf( \WC_Order::class, $order );
		$this->assertEmpty( $order->get_shipping_first_name() );
		$this->assertEmpty( $order->get_shipping_address_1() );
		$this->assertNotEmpty( $order->get_billing_first_name() );

		$order->delete( true );
	}

	/**
	 * Test that an order note is added when a shippable product has no shipping address.
	 *
	 * @return void
	 */
	public function test_order_note_added_when_shippable_product_has_no_shipping_address() {
		$session = $this->build_checkout_session(
			[ 'shipping_details' => null ],
			$this->default_product
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$notes    = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$contents = array_map( fn( $note ) => $note->content, $notes );

		$order->delete( true );

		$this->assertNotEmpty(
			array_filter( $contents, fn( $c ) => str_contains( $c, 'no shipping address was provided' ) )
		);
	}

	/**
	 * Test that no order note is added when a virtual product has no shipping address.
	 *
	 * @return void
	 */
	public function test_no_order_note_when_virtual_product_has_no_shipping_address() {
		$virtual_product = WC_Helper_Product::create_simple_product(
			true,
			[
				'virtual'       => true,
				'regular_price' => '10.00',
				'price'         => '10.00',
			]
		);

		$session = $this->build_checkout_session(
			[ 'shipping_details' => null ],
			$virtual_product
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );

		$notes    = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		$contents = array_map( fn( $note ) => $note->content, $notes );

		$order->delete( true );
		$virtual_product->delete( true );

		$this->assertEmpty(
			array_filter( $contents, fn( $c ) => str_contains( $c, 'no shipping address was provided' ) )
		);
	}

	/**
	 * Test that an exception is thrown when session ID is missing.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_session_id_missing() {
		$session = $this->build_checkout_session( [ 'id' => null ] );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'missing the id field' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that an exception is thrown when payment intent ID is missing.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_payment_intent_id_missing() {
		$session = $this->build_checkout_session(
			[
				'payment_intent' => (object) [ 'id' => null ],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'missing the payment_intent id' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that an exception is thrown when payment intent is null.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_payment_intent_null() {
		$session = $this->build_checkout_session(
			[
				'payment_intent' => null,
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'missing the payment_intent id' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that an exception is thrown when currency is missing.
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_currency_missing() {
		$session = $this->build_checkout_session( [ 'currency' => null ] );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'missing the currency field' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test that an exception is thrown when line items are absent and API
	 * fetch is not available (simulated by providing empty line_items).
	 *
	 * @return void
	 */
	public function test_exception_thrown_when_line_items_empty() {
		$session = $this->build_checkout_session(
			[
				'line_items' => (object) [ 'data' => [] ],
			]
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'has no line items' );

		$this->mapper->create_order_from_checkout_session( $session );
	}

	/**
	 * Test creating an order with multiple line items.
	 *
	 * @return void
	 */
	public function test_multiple_line_items() {
		$product1 = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '10.00',
				'price'         => '10.00',
			]
		);
		$product2 = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '20.00',
				'price'         => '20.00',
			]
		);

		$session = $this->build_checkout_session(
			[
				'amount_total'    => 3000,
				'amount_subtotal' => 3000,
				'line_items'      => $this->build_line_items(
					[
						[
							'lookup_key'      => (string) $product1->get_id(),
							'description'     => 'Product 1',
							'quantity'        => 1,
							'unit_amount'     => 1000,
							'amount_total'    => 1000,
							'amount_subtotal' => 1000,
							'amount_tax'      => 0,
						],
						[
							'lookup_key'      => (string) $product2->get_id(),
							'description'     => 'Product 2',
							'quantity'        => 1,
							'unit_amount'     => 2000,
							'amount_total'    => 2000,
							'amount_subtotal' => 2000,
							'amount_tax'      => 0,
						],
					]
				),
				'total_details'   => (object) [
					'amount_shipping' => 0,
					'amount_tax'      => 0,
					'amount_discount' => 0,
				],
			]
		);

		$order = $this->mapper->create_order_from_checkout_session( $session );
		$items = $order->get_items();

		$this->assertCount( 2, $items );
		$this->assertEquals( '30.00', $order->get_total() );

		$order->delete( true );
		$product1->delete( true );
		$product2->delete( true );
	}

	/**
	 * Builds a Stripe checkout session wrapper for testing.
	 *
	 * @param array<string, mixed>  $overrides Fields to override on the default session.
	 * @param \WC_Product|null      $product   Product to use for the default line item. Defaults to $this->default_product.
	 * @return WC_Stripe_Agentic_Checkout_Session The checkout session wrapper.
	 */
	private function build_checkout_session( array $overrides = [], ?\WC_Product $product = null ): WC_Stripe_Agentic_Checkout_Session {
		$product  = $product ?? $this->default_product;
		$defaults = [
			'id'               => 'cs_test_123',
			'payment_intent'   => (object) [ 'id' => 'pi_test_456' ],
			'customer'         => 'cus_test_789',
			'customer_email'   => 'test@example.com',
			'currency'         => 'usd',
			'amount_total'     => 1000,
			'amount_subtotal'  => 1000,
			'customer_details' => (object) [
				'email'   => 'test@example.com',
				'name'    => 'John Smith',
				'phone'   => '+1234567890',
				'address' => (object) [
					'line1'       => '123 Main St',
					'line2'       => '',
					'city'        => 'Anytown',
					'state'       => 'CA',
					'postal_code' => '90210',
					'country'     => 'US',
				],
			],
			'shipping_details' => (object) [
				'name'    => 'John Smith',
				'address' => (object) [
					'line1'       => '123 Main St',
					'line2'       => '',
					'city'        => 'Anytown',
					'state'       => 'CA',
					'postal_code' => '90210',
					'country'     => 'US',
				],
			],
			'total_details'    => (object) [
				'amount_shipping' => 0,
				'amount_tax'      => 0,
				'amount_discount' => 0,
			],
			'line_items'       => $this->build_line_items(
				[
					[
						'lookup_key'      => (string) $product->get_id(),
						'description'     => 'Default Product',
						'quantity'        => 1,
						'unit_amount'     => 1000,
						'amount_total'    => 1000,
						'amount_subtotal' => 1000,
						'amount_tax'      => 0,
					],
				]
			),
		];

		$merged = array_merge( $defaults, $overrides );

		return new WC_Stripe_Agentic_Checkout_Session( (object) $merged );
	}

	/**
	 * Builds a line items object from an array of item data.
	 *
	 * @param array<int, array<string, mixed>> $items Array of item configurations.
	 * @return object The line items object with data array.
	 */
	private function build_line_items( array $items ): object {
		$data = [];

		foreach ( $items as $index => $item ) {
			$data[] = (object) [
				'id'              => $item['id'] ?? 'li_test_' . $index,
				'description'     => $item['description'] ?? 'Test Product',
				'quantity'        => $item['quantity'] ?? 1,
				'amount_total'    => $item['amount_total'] ?? 0,
				'amount_subtotal' => $item['amount_subtotal'] ?? $item['amount_total'] ?? 0,
				'amount_tax'      => $item['amount_tax'] ?? 0,
				'price'           => (object) [
					'unit_amount'        => $item['unit_amount'] ?? 0,
					'external_reference' => $item['lookup_key'] ?? null,
					'currency'           => $item['currency'] ?? 'usd',
				],
			];
		}

		return (object) [ 'data' => $data ];
	}
}
