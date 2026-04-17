<?php
/**
 * Test harness exposing protected dedup methods on WC_Stripe_Agentic_Commerce_Integration
 * so unit tests can exercise them directly without driving a full sync.
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Subclass that relays calls to the protected dedup methods under test.
 */
class Stripe_Agentic_Commerce_Integration_Dedup_Harness extends \WC_Stripe_Agentic_Commerce_Integration {

	public function public_get_feed_hash( string $file_path ): string {
		return $this->get_feed_hash( $file_path );
	}

	public function public_is_feed_unchanged( string $current_hash ): bool {
		return $this->is_feed_unchanged( $current_hash );
	}

	public function public_remember_feed_upload( string $hash, array $result ): void {
		$this->remember_feed_upload( $hash, $result );
	}
}
