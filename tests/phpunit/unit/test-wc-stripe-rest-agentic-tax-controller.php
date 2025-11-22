<?php

use PHPUnit\Framework\TestCase;

class WC_Stripe_REST_Agentic_Tax_Controller_Test extends TestCase {
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new WC_Stripe_REST_Agentic_Tax_Controller();
	}

	public function test_registers_route() {
		$routes = $this->controller->get_routes();
		$this->assertArrayHasKey( '/wc/v3/stripe/agentic/compute_tax', $routes );
	}

	public function test_compute_tax_returns_error_when_disabled() {
		$request  = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/compute_tax' );
		$response = $this->controller->compute_tax( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'feature_disabled', $response->get_error_code() );
	}

	public function test_compute_tax_returns_tax_amounts() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/compute_tax' );
		$request->set_body( json_encode( [
			'livemode'            => false,
			'currency'            => 'usd',
			'line_items_details'  => [
				[ 'sku_id' => 'test_sku', 'unit_amount' => 1000, 'quantity' => 2 ],
			],
			'fulfillment_details' => [
				'address' => [
					'city'        => 'San Francisco',
					'state'       => 'CA',
					'postal_code' => '94107',
					'country'     => 'US',
				],
			],
		] ) );

		$response = $this->controller->compute_tax( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'line_item_details', $data );
		$this->assertArrayHasKey( 'total_details', $data );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}
}
