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
}
