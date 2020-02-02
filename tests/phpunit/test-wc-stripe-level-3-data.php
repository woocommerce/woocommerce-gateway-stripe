<?php
/**
 * These tests assert that get_level_3_data_from_order() returns the correct
 * data for different types of orders.
 */

class WC_Stripe_Level_3_Data_Test extends WP_UnitTestCase {
	public function test_data_for_mutli_item_order() {
		$order = WC_Helper_Order::create_order();
		echo '$order:';
		var_dump( $order );
		$gateway = new WC_Gateway_Stripe();
		$result = $gateway->get_level_3_data_from_order( $order );
		echo '$result:';
		var_dump( $result );
		$variation_product = WC_Helper_Product::create_variation_product();
		echo '$variation_product:';
		echo $variation_product->get_id();
		var_dump( $variation_product );
		$children_ids = $variation_product->get_children();
		echo "children_ids:";
		var_dump($children_ids);

		$product_1 = wc_get_product($children_ids[0]);
		$product_2 = wc_get_product($children_ids[1]);

		$order->add_product($product_1);
		$order->add_product($product_2);
		$order->save();

		echo "order total: " . $order->get_total();

		$this->assertIsArray( $result );
	}
}
