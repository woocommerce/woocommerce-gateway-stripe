<?php
/**
 * Order helpers.
 *
 * @package WooCommerce\Tests
 */

/**
 * Class WC_Helper_Order.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Helper_Order {

	/**
	 * Delete an order.
	 *
	 * @param int $order_id ID of the order to delete.
	 */
	public static function delete_order( $order_id ) {

		$order = wc_get_order( $order_id );

		// Delete all products in the order.
		foreach ( $order->get_items() as $item ) {
			WC_Helper_Product::delete_product( $item['product_id'] );
		}

		WC_Helper_Shipping::delete_simple_flat_rate();

		// Delete the order post.
		$order->delete( true );
	}

	/**
	 * Create a order.
	 *
	 * @since   2.4
	 * @version 3.0 New parameter $product.
	 *
	 * @param int        $customer_id The ID of the customer the order is for.
	 * @param WC_Product $product The product to add to the order.
	 * @param array      $order_props Order properties.
	 *
	 * @return WC_Order
	 */
	public static function create_order( $customer_id = 1, $product = null, $order_props = [] ) {

		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = WC_Helper_Product::create_simple_product();
		}

		WC_Helper_Shipping::create_simple_flat_rate();

		$order_data = [
			'status'        => WC_Stripe_Order_Status::PENDING,
			'customer_id'   => $customer_id,
			'customer_note' => '',
			'total'         => '',
		];

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception.
		$order                  = wc_create_order( $order_data );

		// Add order products.
		$item = new WC_Order_Item_Product();
		$item->set_props(
			[
				'product'  => $product,
				'quantity' => 4,
				'subtotal' => wc_get_price_excluding_tax( $product, [ 'qty' => 4 ] ),
				'total'    => wc_get_price_excluding_tax( $product, [ 'qty' => 4 ] ),
			]
		);
		$item->save();
		$order->add_item( $item );

		// Set billing address.
		$order->set_billing_first_name( 'Jeroen' );
		$order->set_billing_last_name( 'Sormani' );
		$order->set_billing_company( 'WooCompany' );
		$order->set_billing_address_1( 'WooAddress' );
		$order->set_billing_address_2( '' );
		$order->set_billing_city( 'WooCity' );
		$order->set_billing_state( 'NY' );
		$order->set_billing_postcode( '12345' );
		$order->set_billing_country( 'US' );
		$order->set_billing_email( 'admin@example.org' );
		$order->set_billing_phone( '555-32123' );

		// Add shipping costs.
		$shipping_taxes = WC_Tax::calc_shipping_tax( '10', WC_Tax::get_shipping_tax_rates() );
		$rate           = new WC_Shipping_Rate( 'flat_rate_shipping', 'Flat rate shipping', '10', $shipping_taxes, 'flat_rate' );
		$item           = new WC_Order_Item_Shipping();
		$item->set_props(
			[
				'method_title' => $rate->label,
				'method_id'    => $rate->id,
				'total'        => wc_format_decimal( $rate->cost ),
				'taxes'        => $rate->taxes,
			]
		);
		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
		$order->add_item( $item );

		// Set payment gateway.
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( $payment_gateways['bacs'] );

		// Set totals.
		$order->set_shipping_total( 10 );
		$order->set_discount_total( 0 );
		$order->set_discount_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_total( 50 ); // 4 x $10 simple helper product

		// Additional order properties.
		foreach ( $order_props as $key => $value ) {
			$order->{"set_$key"}( $value );
		}

		$order->save();

		return $order;
	}

	/**
	 * Helper function to create order with fees and shipping objects.
	 *
	 * @param int        $customer_id The ID of the customer the order is for.
	 * @param WC_Product $product The product to add to the order.
	 *
	 * @return WC_Order
	 */
	public static function create_order_with_fees_and_shipping( $customer_id = 1, $product = null ) {
		$order = self::create_order( $customer_id, $product );

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_order_id( $order->get_id() );
		$fee_item->set_name( 'Testing fees' );
		$fee_item->set_total( 100 );

		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_order_id( $order->get_id() );
		$shipping_item->set_name( 'Flat shipping' );
		$shipping_item->set_total( 25 );

		$order->add_item( $fee_item );
		$order->add_item( $shipping_item );
		$order->save();
		$order->calculate_totals( true );

		return $order;
	}
}
