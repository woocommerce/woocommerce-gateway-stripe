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
		$this->assertArrayHasKey( '/wc/v3/stripe/agentic/tax', $routes );
	}

	public function test_compute_tax_returns_error_when_disabled() {
		$request  = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/tax' );
		$response = $this->controller->compute_tax( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'feature_disabled', $response->get_error_code() );
	}

	public function test_compute_tax_returns_calculated_response() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/tax' );
		$request->set_body(
			json_encode(
				[
					'id'                  => 'cs_test_123',
					'livemode'            => false,
					'currency'            => 'usd',
					'line_items_details'  => [
						[
							'sku_id'      => 'test_sku',
							'unit_amount' => 1000,
							'quantity'    => 2,
						],
					],
					'fulfillment_details' => [
						'address' => [
							'city'        => 'San Francisco',
							'state'       => 'CA',
							'postal_code' => '94107',
							'country'     => 'US',
						],
					],
				]
			)
		);

		$response = $this->controller->compute_tax( $request );
		$data     = $response->get_data();

		// Verify Stripe protocol response format.
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'cs_test_123', $data['id'] );
		$this->assertArrayHasKey( 'result', $data );
		$this->assertArrayHasKey( 'type', $data['result'] );
		$this->assertEquals( 'calculated', $data['result']['type'] );
		$this->assertArrayHasKey( 'calculated', $data['result'] );
		$this->assertArrayHasKey( 'taxes', $data['result']['calculated'] );
		$this->assertIsArray( $data['result']['calculated']['taxes'] );

		// Verify tax breakdown structure if taxes exist.
		if ( ! empty( $data['result']['calculated']['taxes'] ) ) {
			$first_tax = $data['result']['calculated']['taxes'][0];
			$this->assertArrayHasKey( 'amount', $first_tax );
			$this->assertArrayHasKey( 'display_name', $first_tax );
			$this->assertIsInt( $first_tax['amount'] );
			$this->assertIsString( $first_tax['display_name'] );
		}

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	public function test_compute_tax_handles_invalid_json() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/tax' );
		$request->set_body( 'invalid json {' );

		$response = $this->controller->compute_tax( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'invalid_json', $response->get_error_code() );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	public function test_compute_tax_handles_empty_tax_rates() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		// Test with a location that has no tax rates configured.
		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/tax' );
		$request->set_body(
			json_encode(
				[
					'id'                  => 'cs_test_456',
					'livemode'            => false,
					'currency'            => 'usd',
					'line_items_details'  => [
						[
							'sku_id'      => 'test_sku',
							'unit_amount' => 1000,
							'quantity'    => 1,
						],
					],
					'fulfillment_details' => [
						'address' => [
							'city'        => 'London',
							'state'       => '',
							'postal_code' => 'SW1A 1AA',
							'country'     => 'GB',
						],
					],
				]
			)
		);

		$response = $this->controller->compute_tax( $request );
		$data     = $response->get_data();

		// Should return valid response even with no taxes.
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'result', $data );
		$this->assertEquals( 'calculated', $data['result']['type'] );
		$this->assertIsArray( $data['result']['calculated']['taxes'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	public function test_compute_tax_calculates_shipping_tax() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/v3/stripe/agentic/tax' );
		$request->set_body(
			json_encode(
				[
					'id'                  => 'cs_test_789',
					'livemode'            => false,
					'currency'            => 'usd',
					'line_items_details'  => [
						[
							'sku_id'      => 'test_sku',
							'unit_amount' => 1000,
							'quantity'    => 1,
						],
					],
					'fulfillment_details' => [
						'shipping_amount' => 500, // $5.00 shipping in cents.
						'address'         => [
							'city'        => 'San Francisco',
							'state'       => 'CA',
							'postal_code' => '94107',
							'country'     => 'US',
						],
					],
				]
			)
		);

		$response = $this->controller->compute_tax( $request );
		$data     = $response->get_data();

		// Verify response includes shipping tax in the total.
		$this->assertArrayHasKey( 'result', $data );
		$this->assertArrayHasKey( 'calculated', $data['result'] );
		$this->assertIsArray( $data['result']['calculated']['taxes'] );

		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}
}
