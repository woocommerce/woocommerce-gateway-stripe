<?php
/**
 * These tests assert that get_level3_data_from_order() returns the correct
 * data.
 */

/**
 * Level3 data test suite
 */
class WC_Stripe_Level3_Data_Test extends WP_UnitTestCase {

	protected function mock_level_3_order( $shipping_postcode, $with_fee = false ) {
		// Setup the item.
		$mock_item = $this->getMockBuilder( WC_Order_Item_Product::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_name', 'get_quantity', 'get_subtotal', 'get_total_tax', 'get_total', 'get_variation_id', 'get_product_id' ] )
			->getMock();

		$mock_item
			->method( 'get_name' )
			->will( $this->returnValue( 'Beanie with Logo' ) );

		$mock_item
			->method( 'get_quantity' )
			->will( $this->returnValue( 1 ) );

		$mock_item
			->method( 'get_total' )
			->will( $this->returnValue( 18 ) );

		$mock_item
			->method( 'get_subtotal' )
			->will( $this->returnValue( 18 ) );

		$mock_item
			->method( 'get_total_tax' )
			->will( $this->returnValue( 2.7 ) );

		$mock_item
			->method( 'get_variation_id' )
			->will( $this->returnValue( false ) );

		$mock_item
			->method( 'get_product_id' )
			->will( $this->returnValue( 30 ) );

		$mock_items[] = $mock_item;

		if ( $with_fee ) {
			// Setup the fee.
			$mock_fee = $this->getMockBuilder( WC_Order_Item_Fee::class )
				->disableOriginalConstructor()
				->setMethods( [ 'get_name', 'get_quantity', 'get_total_tax', 'get_total' ] )
				->getMock();

			$mock_fee
				->method( 'get_name' )
				->will( $this->returnValue( 'fee' ) );

			$mock_fee
				->method( 'get_quantity' )
				->will( $this->returnValue( 1 ) );

			$mock_fee
				->method( 'get_total' )
				->will( $this->returnValue( 10 ) );

			$mock_fee
				->method( 'get_total_tax' )
				->will( $this->returnValue( 1.5 ) );

			$mock_items[] = $mock_fee;
		}

		// Setup the order.
		$mock_order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_id', 'get_items', 'get_currency', 'get_shipping_total', 'get_shipping_tax', 'get_shipping_postcode' ] )
			->getMock();

		$mock_order
			->method( 'get_id' )
			->will( $this->returnValue( 210 ) );

		$mock_order
			->method( 'get_items' )
			->will( $this->returnValue( $mock_items ) );

		$mock_order
			->method( 'get_currency' )
			->will( $this->returnValue( WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );

		$mock_order
			->method( 'get_shipping_total' )
			->will( $this->returnValue( 30 ) );

		$mock_order
			->method( 'get_shipping_tax' )
			->will( $this->returnValue( 8 ) );

		$mock_order
			->method( 'get_shipping_postcode' )
			->will( $this->returnValue( $shipping_postcode ) );

		return $mock_order;
	}

	public function test_data_for_mutli_item_order() {
		$store_postcode = '90210';
		update_option( 'woocommerce_store_postcode', $store_postcode );

		// Arrange: Create a couple of products to use.
		$variation_product = WC_Helper_Product::create_variation_product();
		$variation_ids     = $variation_product->get_children();

		$product_1 = wc_get_product( $variation_ids[0] );
		$product_1->set_regular_price( 19.19 );
		$product_1->set_sale_price( 11.83 );
		$product_1->save();

		$product_2 = wc_get_product( $variation_ids[1] );
		$product_2->set_regular_price( 20.05 );
		$product_2->save();

		// Arrange: Set up an order with:
		// 1) A variation product.
		// 2) The same product added several times.
		// 3) A valid US ZIP code
		$order = new WC_Order();
		$order->set_shipping_postcode( '90210' );
		$order->add_product( $product_1, 1 ); // Add one item of the first product variation
		$order->add_product( $product_2, 2 ); // Add two items of the second product variation

		$order->save();
		$order->calculate_totals();

		// Act: Call get_level3_data_from_order().
		$gateway = new WC_Gateway_Stripe();
		$result  = $gateway->get_level3_data_from_order( $order );

		// Assert.
		$this->assertEquals(
			[
				'merchant_reference'   => $order->get_id(),
				'shipping_address_zip' => $order->get_shipping_postcode(),
				'shipping_from_zip'    => $store_postcode,
				'shipping_amount'      => 0,
				'line_items'           => [
					(object) [
						'product_code'        => (string) $product_1->get_id(),
						'product_description' => substr( $product_1->get_name(), 0, 26 ),
						'unit_cost'           => 1183,
						'quantity'            => 1,
						'tax_amount'          => 0,
						'discount_amount'     => 0,
					],
					(object) [
						'product_code'        => (string) $product_2->get_id(),
						'product_description' => substr( $product_2->get_name(), 0, 26 ),
						'unit_cost'           => 2005,
						'quantity'            => 2,
						'tax_amount'          => 0,
						'discount_amount'     => 0,
					],
				],
			],
			$result
		);

		// Assert: Check that Stripe's total charge check passes.
		$total_charged     = WC_Stripe_Helper::get_stripe_amount( $order->get_total() );
		$sum_of_unit_costs = array_reduce(
			$result['line_items'],
			function( $sum, $item ) {
				return $sum + $item->quantity * $item->unit_cost;
			}
		);
		$sum_of_taxes      = array_reduce(
			$result['line_items'],
			function( $sum, $item ) {
				return $sum + $item->tax_amount;
			}
		);
		$sum_of_discounts  = array_reduce(
			$result['line_items'],
			function( $sum, $item ) {
				return $sum + $item->discount_amount;
			}
		);
		$shipping_amount   = $result['shipping_amount'];
		$this->assertEquals(
			$total_charged,
			$sum_of_unit_costs + $sum_of_taxes - $sum_of_discounts + $shipping_amount
		);
	}

	public function test_non_us_shipping_zip_codes() {
		// Update the store with the right post code.
		update_option( 'woocommerce_store_postcode', 1040 );

		// Arrange: Create a couple of products to use.
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( 19.19 );
		$product->save();

		// Arrange: Set up an order with a non-US postcode.
		$order = new WC_Order();
		$order->set_shipping_postcode( '1050' );
		$order->add_product( $product, 1 );
		$order->save();
		$order->calculate_totals();

		// Act: Call get_level3_data_from_order().
		$store_postcode = '1100';
		$gateway        = new WC_Gateway_Stripe();
		$result         = $gateway->get_level3_data_from_order( $order );

		// Assert.
		$this->assertEquals(
			[
				'merchant_reference' => $order->get_id(),
				'shipping_amount'    => 0,
				'line_items'         => [
					(object) [
						'product_code'        => (string) $product->get_id(),
						'product_description' => substr( $product->get_name(), 0, 26 ),
						'unit_cost'           => 1919,
						'quantity'            => 1,
						'tax_amount'          => 0,
						'discount_amount'     => 0,
					],
				],
			],
			$result
		);
	}

	public function test_full_level3_data_with_fee() {
		$expected_data = [
			'merchant_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
				(object) [
					'product_code'        => 'fee',
					'product_description' => 'fee',
					'unit_cost'           => 1000,
					'quantity'            => 1,
					'tax_amount'          => 150,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		update_option( 'woocommerce_store_postcode', '94110' );

		$mock_order   = $this->mock_level_3_order( '98012', true );
		$gateway      = new WC_Gateway_Stripe();
		$level_3_data = $gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}
}
