<?php

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_Error;
use WC_Stripe_REST_Agentic_Approval_Controller;

/**
 * Test Manual Approval Hook REST controller for Agentic Checkout.
 */
class WC_Stripe_REST_Agentic_Approval_Controller_Test extends WP_UnitTestCase {
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new WC_Stripe_REST_Agentic_Approval_Controller();
	}

	public function test_registers_route() {
		$routes = $this->controller->get_routes();
		$this->assertArrayHasKey( '/wc/v3/stripe/agentic/approve', $routes );
	}

	public function test_approve_returns_error_when_disabled() {
		$request  = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$response = $this->controller->approve( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'feature_disabled', $response->get_error_code() );
	}

	public function test_approve_approves_when_products_in_stock() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'          => 'cs_test_123',
			'line_items'  => [],
			'amount_total' => 1000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'cs_test_123', $data['id'] );
		$this->assertEquals( 'approved', $data['result']['type'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	public function test_approve_declines_when_product_not_found() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'         => 'cs_test_123',
			'line_items' => [
				'data' => [
					[
						'price'    => [ 'lookup_key' => 'invalid_sku' ],
						'quantity' => 1,
					],
				],
			],
			'amount_total' => 1000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'declined', $data['result']['type'] );
		$this->assertEquals( 'product_not_found', $data['result']['declined']['reason'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	public function test_approve_declines_when_product_not_purchasable() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		// Mock product that's not purchasable
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'is_purchasable' )->willReturn( false );
		$product->method( 'is_in_stock' )->willReturn( true );
		$product->method( 'get_sku' )->willReturn( 'test_sku' );

		add_filter( 'wc_stripe_agentic_product_by_sku', function() use ( $product ) {
			return $product;
		} );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'         => 'cs_test_123',
			'line_items' => [
				'data' => [
					[
						'price'    => [ 'lookup_key' => 'test_sku' ],
						'quantity' => 1,
					],
				],
			],
			'amount_total' => 1000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'declined', $data['result']['type'] );
		$this->assertEquals( 'product_not_purchasable', $data['result']['declined']['reason'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
		remove_all_filters( 'wc_stripe_agentic_product_by_sku' );
	}

	public function test_approve_declines_when_out_of_stock() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		// Mock product that's out of stock
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'is_purchasable' )->willReturn( true );
		$product->method( 'is_in_stock' )->willReturn( false );
		$product->method( 'get_sku' )->willReturn( 'test_sku' );

		add_filter( 'wc_stripe_agentic_product_by_sku', function() use ( $product ) {
			return $product;
		} );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'         => 'cs_test_123',
			'line_items' => [
				'data' => [
					[
						'price'    => [ 'lookup_key' => 'test_sku' ],
						'quantity' => 1,
					],
				],
			],
			'amount_total' => 1000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'declined', $data['result']['type'] );
		$this->assertEquals( 'low_inventory', $data['result']['declined']['reason'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
		remove_all_filters( 'wc_stripe_agentic_product_by_sku' );
	}

	public function test_approve_declines_when_insufficient_stock() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		// Mock product with limited stock
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'is_purchasable' )->willReturn( true );
		$product->method( 'is_in_stock' )->willReturn( true );
		$product->method( 'managing_stock' )->willReturn( true );
		$product->method( 'get_stock_quantity' )->willReturn( 2 );
		$product->method( 'get_sku' )->willReturn( 'test_sku' );

		add_filter( 'wc_stripe_agentic_product_by_sku', function() use ( $product ) {
			return $product;
		} );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'         => 'cs_test_123',
			'line_items' => [
				'data' => [
					[
						'price'    => [ 'lookup_key' => 'test_sku' ],
						'quantity' => 5, // Request 5, but only 2 available
					],
				],
			],
			'amount_total' => 5000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'declined', $data['result']['type'] );
		$this->assertEquals( 'low_inventory', $data['result']['declined']['reason'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
		remove_all_filters( 'wc_stripe_agentic_product_by_sku' );
	}

	public function test_approve_approves_when_sufficient_stock() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		// Mock product with sufficient stock
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'is_purchasable' )->willReturn( true );
		$product->method( 'is_in_stock' )->willReturn( true );
		$product->method( 'managing_stock' )->willReturn( true );
		$product->method( 'get_stock_quantity' )->willReturn( 10 );
		$product->method( 'get_sku' )->willReturn( 'test_sku' );

		add_filter( 'wc_stripe_agentic_product_by_sku', function() use ( $product ) {
			return $product;
		} );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'         => 'cs_test_123',
			'line_items' => [
				'data' => [
					[
						'price'    => [ 'lookup_key' => 'test_sku' ],
						'quantity' => 5,
					],
				],
			],
			'amount_total' => 5000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'approved', $data['result']['type'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
		remove_all_filters( 'wc_stripe_agentic_product_by_sku' );
	}

	public function test_approve_declines_when_multiple_products_one_invalid() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		// Mock first product as valid
		$product1 = $this->createMock( \WC_Product::class );
		$product1->method( 'is_purchasable' )->willReturn( true );
		$product1->method( 'is_in_stock' )->willReturn( true );
		$product1->method( 'managing_stock' )->willReturn( false );
		$product1->method( 'get_sku' )->willReturn( 'valid_sku' );

		add_filter( 'wc_stripe_agentic_product_by_sku', function( $product, $sku ) use ( $product1 ) {
			if ( $sku === 'valid_sku' ) {
				return $product1;
			}
			// Second product not found
			return null;
		}, 10, 2 );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/approve' );
		$request->set_body( json_encode( [
			'id'         => 'cs_test_123',
			'line_items' => [
				'data' => [
					[
						'price'    => [ 'lookup_key' => 'valid_sku' ],
						'quantity' => 1,
					],
					[
						'price'    => [ 'lookup_key' => 'invalid_sku' ],
						'quantity' => 1,
					],
				],
			],
			'amount_total' => 2000,
		] ) );

		$response = $this->controller->approve( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'declined', $data['result']['type'] );
		$this->assertEquals( 'product_not_found', $data['result']['declined']['reason'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
		remove_all_filters( 'wc_stripe_agentic_product_by_sku' );
	}
}
