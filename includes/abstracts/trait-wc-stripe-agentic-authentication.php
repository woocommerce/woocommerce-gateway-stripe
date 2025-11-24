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
			WC_Stripe_Logger::log(
				'Agentic: Authentication failed - webhook secret not configured',
				[
					'context' => 'agentic_authentication',
					'testmode' => $testmode,
				]
			);
			return new WP_Error( 'no_secret', 'Webhook secret not configured' );
		}

		// Get request headers and body.
		$headers = $this->get_request_headers();
		$body    = file_get_contents( 'php://input' );

		// Check for file_get_contents failure.
		if ( false === $body ) {
			WC_Stripe_Logger::log(
				'Agentic: Authentication failed - could not read request body',
				[ 'context' => 'agentic_authentication' ]
			);
			return new WP_Error( 'read_failure', 'Failed to read request body' );
		}

		if ( empty( $headers ) || ! is_array( $headers ) ) {
			WC_Stripe_Logger::log(
				'Agentic: Authentication failed - no request headers found',
				[
					'context' => 'agentic_authentication',
					'headers_type' => gettype( $headers ),
				]
			);
			return new WP_Error( 'empty_headers', 'No request headers found' );
		}

		if ( '' === $body || is_null( $body ) ) {
			WC_Stripe_Logger::log(
				'Agentic: Authentication failed - empty request body',
				[ 'context' => 'agentic_authentication' ]
			);
			return new WP_Error( 'empty_body', 'No request body found' );
		}

		// Reuse existing validation logic.
		$webhook_handler = new WC_Stripe_Webhook_Handler();
		$result          = $webhook_handler->validate_request( $headers, $body );

		if ( WC_Stripe_Webhook_State::VALIDATION_SUCCEEDED !== $result ) {
			WC_Stripe_Logger::log(
				'Agentic: Authentication failed - invalid signature',
				[
					'context' => 'agentic_authentication',
					'validation_result' => $result,
				]
			);
			return new WP_Error( 'invalid_signature', $result );
		}

		WC_Stripe_Logger::log(
			'Agentic: Authentication succeeded',
			[ 'context' => 'agentic_authentication' ]
		);

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
