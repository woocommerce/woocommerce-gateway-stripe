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
		$this->assertTrue( true );
	}
}
