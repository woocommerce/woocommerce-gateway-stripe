<?php

/**
 * Tests for WC_Stripe_Diagnostics_Redactor.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Redactor_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Diagnostics_Redactor
	 */
	private $redactor;

	public function set_up() {
		parent::set_up();
		$this->redactor = new WC_Stripe_Diagnostics_Redactor();
	}

	public function test_unknown_kind_returns_empty_event() {
		$this->assertSame(
			[],
			$this->redactor->redact(
				[
					'kind' => 'nonsense',
					'foo'  => 'bar',
				]
			)
		);
		$this->assertSame( [], $this->redactor->redact( [] ) );
	}

	public function test_fields_outside_allow_list_are_dropped() {
		$event  = [
			'kind'           => 'stripe.api.response',
			'api'            => 'payment_intents',
			'method'         => 'POST',
			'status'         => 200,
			'request_id'     => 'req_abc',
			'raw_body'       => 'should not be kept',
			'customer_email' => 'shopper@example.com',
			'latency_ms'     => 134,
		];
		$result = $this->redactor->redact( $event );
		$this->assertArrayNotHasKey( 'raw_body', $result );
		$this->assertArrayNotHasKey( 'customer_email', $result );
		$this->assertSame( 200, $result['status'] );
		$this->assertSame( 134, $result['latency_ms'] );
	}

	public function test_nested_allow_list_paths_are_kept() {
		$event  = [
			'kind'     => 'stripe.api.request',
			'api'      => 'payment_intents',
			'method'   => 'POST',
			'metadata' => [
				'order_id'           => '42',
				'wc_diag_session_id' => 'smoke-abc',
				'customer_email'     => 'leak@example.com',
			],
		];
		$result = $this->redactor->redact( $event );
		$this->assertSame( '42', $result['metadata']['order_id'] );
		$this->assertSame( 'smoke-abc', $result['metadata']['wc_diag_session_id'] );
		$this->assertArrayNotHasKey( 'customer_email', $result['metadata'] );
	}

	/**
	 * @dataProvider provide_pii_payloads
	 */
	public function test_pii_patterns_are_scrubbed( string $allowed_field, string $raw_value, array $forbidden_substrings ) {
		// Inject the PII into an allow-listed console.warn field.
		$event                   = [
			'kind'   => 'console.warn',
			'source' => 'parent_frame',
		];
		$event[ $allowed_field ] = $raw_value;

		$result = $this->redactor->redact( $event );
		$json   = wp_json_encode( $result );

		foreach ( $forbidden_substrings as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, "PII substring '{$needle}' survived redaction." );
		}
	}

	public function provide_pii_payloads(): array {
		$fake_stripe_secret = 'sk_' . 'live_abcdef1234567890'; // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found

		return [
			'email in message'     => [ 'message', 'Failed for user shopper@example.com trying again', [ 'shopper@example.com' ] ],
			'ipv4 in message'      => [ 'message', 'Blocked from 192.168.1.42 after 3 retries', [ '192.168.1.42' ] ],
			'ipv6 in message'      => [ 'message', 'Blocked from 2001:0db8:85a3:0000:0000:8a2e:0370:7334', [ '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ] ],
			'stripe secret in msg' => [ 'message', 'Authorization: Bearer ' . $fake_stripe_secret, [ $fake_stripe_secret ] ],
			'url with query'       => [ 'message', 'Redirected to https://example.com/pay?email=shopper@example.com&token=xyz', [ 'shopper@example.com', 'token=xyz' ] ],
		];
	}

	public function test_redaction_audit_no_pii_survives_for_any_allow_listed_field() {
		// For every kind / field in the allow list, inject a bundle of PII
		// patterns and assert none survive into the serialized trace JSON.
		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
		$pii_payloads = [
			'foo@bar.com',
			'user@some-domain.co.uk',
			'192.168.1.1',
			'10.0.0.1',
			'2001:db8::1',
			'sk_' . 'live_supersecretkey12345',
			'whsec_' . 'abcdef1234567890',
			'https://example.com/checkout?email=leak@example.com',
		];
		// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found

		foreach ( WC_Stripe_Diagnostics_Redactor::default_allow_list() as $kind => $fields ) {
			foreach ( $fields as $path ) {
				foreach ( $pii_payloads as $payload ) {
					$event = [ 'kind' => $kind ];
					$this->assign( $event, $path, $payload );
					$redacted = $this->redactor->redact( $event );
					$json     = wp_json_encode( $redacted );
					$this->assertStringNotContainsString( $payload, $json, "PII '{$payload}' survived in {$kind}.{$path}" );
				}
			}
		}
	}

	public function test_long_string_is_truncated() {
		$raw    = str_repeat( 'a', 600 );
		$event  = [
			'kind'    => 'console.warn',
			'message' => $raw,
		];
		$result = $this->redactor->redact( $event );
		$this->assertLessThan( 600, strlen( $result['message'] ) );
	}

	/**
	 * Helper that mirrors the redactor's internal assign().
	 */
	private function assign( array &$target, string $path, $value ): void {
		$segments = explode( '.', $path );
		$cursor   = &$target;
		foreach ( $segments as $i => $segment ) {
			if ( count( $segments ) - 1 === $i ) {
				$cursor[ $segment ] = $value;
				return;
			}
			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
				$cursor[ $segment ] = [];
			}
			$cursor = &$cursor[ $segment ];
		}
	}
}
