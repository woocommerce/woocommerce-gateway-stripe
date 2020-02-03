<?php
/**
 * These tests assert that get_level3_data_from_order() returns the correct
 * data.
 */

class WC_Stripe_level3_Data_Test extends WP_UnitTestCase {
	public function test_data_for_mutli_item_order() {
		// Arrange: Create a couple of products to use.
		$variation_product = WC_Helper_Product::create_variation_product();
		$variation_ids     = $variation_product->get_children();

		$product_1 = wc_get_product ( $variation_ids[0] );
		$product_1->set_regular_price( 19.19 );
		$product_1->set_sale_price( 11.83 );
		$product_1->save();

		$product_2 = wc_get_product( $variation_ids[1] );
		$product_2->set_regular_price( 20.05 );
		$product_2->save();

		// Arrange: Set up an order with:
		// 1) A variation product.
		// 2) The same product added several times.
		$order = new WC_Order();
		$order->add_product( $product_1, 1 ); // Add one item of the first product variation
		$order->add_product( $product_2, 2 ); // Add two items of the second product variation

		$order->save();
		$order->calculate_totals();

		// Act: Call get_level3_data_from_order().
		$store_postcode = '90210';
		$gateway = new WC_Gateway_Stripe();
		$result = $gateway->get_level3_data_from_order( $order, $store_postcode );

		// Assert.
		$this->assertIsArray( $result );
		$this->assertEquals(
			array(
				'merchant_reference' => $order->get_id(),
				'shipping_address_zip' => $order->get_shipping_postcode(),
				'shipping_from_zip' => $store_postcode,
				'shipping_amount' => 0,
				'line_items' => array(
					(object) array(
						'product_code'        => (string) $product_1->get_id(),
						'product_description' => 'Dummy Variable Product',
						'unit_cost'           => 1183,
						'quantity'            => 1,
						'tax_amount'          => 0,
						'discount_amount'     => 0,
					),
					(object) array(
						'product_code'        => (string) $product_2->get_id(),
						'product_description' => 'Dummy Variable Product',
						'unit_cost'           => 2005,
						'quantity'            => 2,
						'tax_amount'          => 0,
						'discount_amount'     => 0,
					),
				),
			),
			$result
		);

		// Assert: Check that Stripe's total charge check passes.
		$total_charged = WC_Stripe_Helper::get_stripe_amount( $order->get_total() );
		$sum_of_unit_costs = array_reduce( $result['line_items'], function( $sum, $item ) {
			return $sum + $item->quantity * $item->unit_cost;
		}  );
		$sum_of_taxes = array_reduce( $result['line_items'], function( $sum, $item ) {
			return $sum + $item->tax_amount;
		}  );
		$sum_of_discounts = array_reduce( $result['line_items'], function( $sum, $item ) {
			return $sum + $item->discount_amount;
		}  );
		$shipping_amount = $result['shipping_amount'];
		$this->assertEquals(
			$total_charged,
			$sum_of_unit_costs + $sum_of_taxes - $sum_of_discounts + $shipping_amount
		);
	}
}
