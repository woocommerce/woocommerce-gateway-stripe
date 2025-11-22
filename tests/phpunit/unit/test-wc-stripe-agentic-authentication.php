<?php

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WP_Error;

/**
 * Test authentication trait for Agentic Checkout.
 */
class WC_Stripe_Agentic_Authentication_Test extends WP_UnitTestCase {
	use \WC_Stripe_Agentic_Authentication;

	protected function get_request_headers() {
		return [];
	}

	public function test_is_agentic_checkout_enabled_returns_false_by_default() {
		$this->assertFalse( $this->is_agentic_checkout_enabled() );
	}

	public function test_is_agentic_checkout_enabled_respects_filter() {
		add_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
		$this->assertTrue( $this->is_agentic_checkout_enabled() );
		remove_filter( 'wc_stripe_agentic_checkout_enabled', '__return_true' );
	}

	public function test_verify_stripe_signature_returns_error_when_no_secret() {
		$result = $this->verify_stripe_signature();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'no_secret', $result->get_error_code() );
	}
}
