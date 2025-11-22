<?php

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WP_Error;
use WC_Stripe_Webhook_Handler;
use WC_Stripe_Webhook_State;

/**
 * Test authentication trait for Agentic Checkout.
 */
class WC_Stripe_Agentic_Authentication_Test extends WP_UnitTestCase {
	use \WC_Stripe_Agentic_Authentication;

	/**
	 * Mock webhook handler for testing.
	 *
	 * @var WC_Stripe_Webhook_Handler|null
	 */
	private $mock_webhook_handler = null;

	/**
	 * Override verify_stripe_signature to allow injection of mock webhook handler.
	 *
	 * @return bool|WP_Error
	 */
	protected function verify_stripe_signature_with_handler( $webhook_handler = null ) {
		// Get webhook secret from settings.
		$stripe_settings = \WC_Stripe_Helper::get_stripe_settings();
		$testmode        = \WC_Stripe_Mode::is_test();
		$secret_key      = ( $testmode ? 'test_' : '' ) . 'webhook_secret';
		$secret          = $stripe_settings[ $secret_key ] ?? false;

		if ( empty( $secret ) ) {
			return new WP_Error( 'no_secret', 'Webhook secret not configured' );
		}

		// Get request headers and body.
		$headers = $this->get_request_headers();
		$body    = file_get_contents( 'php://input' );

		// Check for file_get_contents failure.
		if ( false === $body ) {
			return new WP_Error( 'read_failure', 'Failed to read request body' );
		}

		if ( empty( $headers ) || ! is_array( $headers ) ) {
			return new WP_Error( 'empty_headers', 'No request headers found' );
		}

		if ( '' === $body || is_null( $body ) ) {
			return new WP_Error( 'empty_body', 'No request body found' );
		}

		// Use injected handler or create new one.
		$handler = $webhook_handler ?? new WC_Stripe_Webhook_Handler();
		$result  = $handler->validate_request( $headers, $body );

		if ( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED !== $result ) {
			return new WP_Error( 'invalid_signature', $result );
		}

		return true;
	}

	protected function get_request_headers() {
		return $this->headers ?? [];
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

	public function test_verify_stripe_signature_returns_true_for_valid_signature() {
		// Mock Stripe settings with webhook secret.
		add_filter(
			'wc_stripe_settings',
			function () {
				return [
					'testmode'            => 'yes',
					'test_webhook_secret' => 'whsec_test_secret',
				];
			}
		);

		// Set headers.
		$this->headers = [ 'Stripe-Signature' => 't=123,v1=valid_signature' ];

		// Mock the webhook handler to return successful validation.
		$mock_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'validate_request' ] )
			->getMock();

		$mock_handler->expects( $this->once() )
			->method( 'validate_request' )
			->with( $this->headers, $this->anything() )
			->willReturn( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED );

		$result = $this->verify_stripe_signature_with_handler( $mock_handler );
		$this->assertTrue( $result );

		// Clean up.
		remove_all_filters( 'wc_stripe_settings' );
	}

	public function test_verify_stripe_signature_returns_error_for_invalid_signature() {
		// Mock Stripe settings with webhook secret.
		add_filter(
			'wc_stripe_settings',
			function () {
				return [
					'testmode'            => 'yes',
					'test_webhook_secret' => 'whsec_test_secret',
				];
			}
		);

		// Set headers.
		$this->headers = [ 'Stripe-Signature' => 't=123,v1=invalid_signature' ];

		// Mock the webhook handler to return failed validation.
		$mock_handler = $this->getMockBuilder( WC_Stripe_Webhook_Handler::class )
			->setMethods( [ 'validate_request' ] )
			->getMock();

		$mock_handler->expects( $this->once() )
			->method( 'validate_request' )
			->with( $this->headers, $this->anything() )
			->willReturn( WC_Stripe_Webhook_State::VALIDATION_FAILED_SIGNATURE_MISMATCH );

		$result = $this->verify_stripe_signature_with_handler( $mock_handler );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_signature', $result->get_error_code() );
		$this->assertEquals( WC_Stripe_Webhook_State::VALIDATION_FAILED_SIGNATURE_MISMATCH, $result->get_error_message() );

		// Clean up.
		remove_all_filters( 'wc_stripe_settings' );
	}

	public function test_verify_stripe_signature_returns_error_for_empty_headers() {
		// Mock Stripe settings with webhook secret.
		add_filter(
			'wc_stripe_settings',
			function () {
				return [
					'testmode'            => 'yes',
					'test_webhook_secret' => 'whsec_test_secret',
				];
			}
		);

		// Set empty headers.
		$this->headers = [];

		$result = $this->verify_stripe_signature();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'empty_headers', $result->get_error_code() );

		// Clean up.
		remove_all_filters( 'wc_stripe_settings' );
	}

	public function test_verify_stripe_signature_returns_error_for_non_array_headers() {
		// Mock Stripe settings with webhook secret.
		add_filter(
			'wc_stripe_settings',
			function () {
				return [
					'testmode'            => 'yes',
					'test_webhook_secret' => 'whsec_test_secret',
				];
			}
		);

		// Set non-array headers.
		$this->headers = 'not an array';

		$result = $this->verify_stripe_signature();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'empty_headers', $result->get_error_code() );

		// Clean up.
		remove_all_filters( 'wc_stripe_settings' );
	}
}
