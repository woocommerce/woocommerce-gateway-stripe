<?php
/**
 * These tests assert that get_level3_data_from_order() returns the correct
 * data.
 */

class WC_Stripe_level3_Data_Test extends WP_UnitTestCase {
	public function test_data_for_mutli_item_order() {
		// Level 3 data is only for WC 3.0+.
		if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
			// Dummy assertion.
			$this->assertEquals( WC_Stripe_Helper::is_wc_lt( '3.0' ), true );
		}

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

	public function test_level3_param_not_added_in_wc26() {
		// This is a test only for WC version < 3.0.
		if ( ! WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
			// Dummy assertion.
			$this->assertEquals( WC_Stripe_Helper::is_wc_lt( '3.0' ), false );
		}

		// Arrange: A request array and URL path to use in request_with_level3_data().
		$request = array(
			'source' => '123',
		);
		$api = 'payment_intent';

		// Arrange: Intercept HTTP call so we can test that WC_Stripe_API::request was called.
		// Two assertions happen here - to make sure we didn't send the level3 parameter to
		// the Stripe API.
		// Note: It's unfortunate that we have to test implementation details here, but there
		// is no way to mock static functions using phpunit - i.e. we could have mocked
		// WC_Stripe_API::request to make sure that it was called with the correct parameters.
		$pre_http_request_response_callback = function( $preempt, $request_args, $url ) use (
			$request,
			$api
		) {
			// Assert: There is no level3 param in API call.
			$this->assertSame($request_args['body'], $request );
			// Assert: We're using the same API endpoint as originally
			// supplied to request_with_level3_data.
			$this->assertSame( $url, WC_Stripe_API::ENDPOINT . $api); // this assertion should be fixed to use correct url ($x is incorrect)

			// Return dummy content as the response so that an error is not thrown during test.
			return array(
				'headers'  => array(),
				'body'     => 'test',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Act: call request_with_level3_data() in WC 2.6 (it's one of the matrix envs in Travis).
		// Note: all the assertions happen in pre_http_request_response_callback.
		WC_Stripe_API::request_with_level3_data(
			$request,
			$api,
			array(
				'test' => 123,
			),
			new WC_Order()
		);
	}
}
