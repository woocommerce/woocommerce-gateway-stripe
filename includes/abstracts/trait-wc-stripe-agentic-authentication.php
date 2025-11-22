<?php
/**
 * Trait WC_Stripe_Agentic_Authentication
 *
 * Provides authentication and gating logic for Agentic Checkout REST endpoints.
 *
 * @package WooCommerce_Stripe/Abstracts
 * @since   8.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for authenticating Stripe Agentic Checkout webhook requests.
 */
trait WC_Stripe_Agentic_Authentication {
	/**
	 * Check if agentic checkout is enabled.
	 *
	 * @return bool
	 */
	protected function is_agentic_checkout_enabled() {
		/**
		 * Filter to enable/disable Agentic Checkout functionality.
		 *
		 * @since 8.9.0
		 * @param bool $enabled Whether agentic checkout is enabled. Default false.
		 */
		return apply_filters( 'wc_stripe_agentic_checkout_enabled', false );
	}

	/**
	 * Verify Stripe signature for agentic hooks.
	 *
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	protected function verify_stripe_signature() {
		// Get webhook secret from settings.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$testmode        = WC_Stripe_Mode::is_test();
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

		// Reuse existing validation logic.
		$webhook_handler = new WC_Stripe_Webhook_Handler();
		$result          = $webhook_handler->validate_request( $headers, $body );

		if ( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED !== $result ) {
			return new WP_Error( 'invalid_signature', $result );
		}

		return true;
	}

	/**
	 * Get request headers for validation.
	 *
	 * Must be implemented by class using trait.
	 *
	 * @return array
	 */
	abstract protected function get_request_headers();
}
