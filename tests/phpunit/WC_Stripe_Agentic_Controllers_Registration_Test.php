<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe;

/**
 * Tests for Agentic Checkout REST controller registration.
 *
 * @package WooCommerce/Stripe/WC_Stripe
 */
class WC_Stripe_Agentic_Controllers_Registration_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * Test that agentic controllers are not registered when feature is disabled.
	 */
	public function test_agentic_controllers_not_registered_when_disabled() {
		// Remove the filter to disable agentic checkout.
		remove_all_filters( 'wc_stripe_agentic_checkout_enabled' );
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_false' );

		$wc_stripe = WC_Stripe::get_instance();

		// Trigger REST API init.
		do_action( 'rest_api_init' );

		// Check that agentic routes are not registered.
		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assertArrayNotHasKey( '/wc/v3/stripe/agentic/approve', $routes );
		$this->assertArrayNotHasKey( '/wc/v3/stripe/agentic/compute_tax', $routes );
		$this->assertArrayNotHasKey( '/wc/v3/stripe/agentic/compute_shipping_options', $routes );

		// Clean up.
		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_false' );
	}

	/**
	 * Test that agentic controllers are registered when feature is enabled.
	 */
	public function test_agentic_controllers_registered_when_enabled() {
		// Enable agentic checkout.
		remove_all_filters( 'wc_stripe_agentic_checkout_enabled' );
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );

		$wc_stripe = WC_Stripe::get_instance();

		// Trigger REST API init.
		do_action( 'rest_api_init' );

		// Check that agentic routes are registered.
		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assertArrayHasKey( '/wc/v3/stripe/agentic/approve', $routes );
		$this->assertArrayHasKey( '/wc/v3/stripe/agentic/compute_tax', $routes );
		$this->assertArrayHasKey( '/wc/v3/stripe/agentic/compute_shipping_options', $routes );

		// Clean up.
		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	/**
	 * Test that register_agentic_rest_controllers method exists.
	 */
	public function test_register_agentic_rest_controllers_method_exists() {
		$wc_stripe = WC_Stripe::get_instance();
		$this->assertTrue( method_exists( $wc_stripe, 'register_agentic_rest_controllers' ) );
	}

	/**
	 * Test that register_agentic_rest_controllers is hooked to rest_api_init.
	 */
	public function test_register_agentic_rest_controllers_hooked_to_rest_api_init() {
		$wc_stripe = WC_Stripe::get_instance();
		$this->assertNotFalse( has_action( 'rest_api_init', [ $wc_stripe, 'register_agentic_rest_controllers' ] ) );
	}
}
