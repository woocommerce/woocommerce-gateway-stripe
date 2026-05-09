<?php
/**
 * @package WooCommerce/Stripe
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config-flags.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/remote-config/class-wc-stripe-remote-config.php';

class WC_Stripe_Remote_Config_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
	}

	public function tear_down(): void {
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		parent::tear_down();
	}

	private function valid_payload( bool $oc_value = false ): array {
		return [
			'flags'        => [ 'optimized_checkout' => [ 'value' => $oc_value ] ],
			'generated_at' => '2026-05-09T12:00:00Z',
			'ttl'          => 86400,
		];
	}

	public function test_apply_writes_to_per_mode_option(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload( false ) );

		$stored = get_option( '_wcstripe_remote_config_live' );
		$this->assertIsArray( $stored );
		$this->assertSame( 1, $stored['schema_version'] );
		$this->assertSame( false, $stored['flags']['optimized_checkout']['value'] );

		$this->assertFalse( get_option( '_wcstripe_remote_config_test' ) );
	}

	public function test_apply_returns_true_on_success(): void {
		$rc = new WC_Stripe_Remote_Config();
		$this->assertTrue( $rc->apply( 'live', $this->valid_payload() ) );
	}

	public function test_apply_rejects_payload_missing_generated_at(): void {
		$rc      = new WC_Stripe_Remote_Config();
		$payload = $this->valid_payload();
		unset( $payload['generated_at'] );

		$this->assertFalse( $rc->apply( 'live', $payload ) );
		$this->assertFalse( get_option( '_wcstripe_remote_config_live' ) );
	}

	public function test_apply_rejects_payload_with_non_iso_generated_at(): void {
		$rc                      = new WC_Stripe_Remote_Config();
		$payload                 = $this->valid_payload();
		$payload['generated_at'] = 'not-a-date';

		$this->assertFalse( $rc->apply( 'live', $payload ) );
	}

	public function test_apply_rejects_payload_with_wrong_flag_type(): void {
		$rc      = new WC_Stripe_Remote_Config();
		$payload = $this->valid_payload();
		$payload['flags']['optimized_checkout']['value'] = 'true';   // string, not bool

		$this->assertFalse( $rc->apply( 'live', $payload ) );
		$this->assertFalse( get_option( '_wcstripe_remote_config_live' ) );
	}

	public function test_apply_drops_unknown_flag_names_silently(): void {
		$rc                               = new WC_Stripe_Remote_Config();
		$payload                          = $this->valid_payload();
		$payload['flags']['unknown_flag'] = [ 'value' => true ];

		$this->assertTrue( $rc->apply( 'live', $payload ) );
		$stored = get_option( '_wcstripe_remote_config_live' );
		$this->assertArrayNotHasKey( 'unknown_flag', $stored['flags'] );
		$this->assertArrayHasKey( 'optimized_checkout', $stored['flags'] );
	}

	public function test_apply_circuit_breaker_keeps_previous_cache_on_validation_failure(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload( false ) );

		$bad_payload = $this->valid_payload();
		$bad_payload['flags']['optimized_checkout']['value'] = 'string-not-bool';

		$this->assertFalse( $rc->apply( 'live', $bad_payload ) );

		$stored = get_option( '_wcstripe_remote_config_live' );
		$this->assertSame( false, $stored['flags']['optimized_checkout']['value'] );
	}

	public function test_apply_rejects_oversized_payload(): void {
		$rc      = new WC_Stripe_Remote_Config();
		$payload = $this->valid_payload();
		// Stuff something large into a known-good top-level field.
		$payload['_padding'] = str_repeat( 'a', WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES + 1 );

		$this->assertFalse( $rc->apply( 'live', $payload ) );
		$this->assertFalse( get_option( '_wcstripe_remote_config_live' ) );
	}

	public function test_resolve_returns_remote_value_when_present(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload( false ) );

		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
	}

	public function test_resolve_returns_local_value_when_remote_absent(): void {
		$rc = new WC_Stripe_Remote_Config();

		$this->assertTrue( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		$this->assertFalse( $rc->resolve( 'optimized_checkout', false, 'live' ) );
	}

	public function test_resolve_returns_local_value_for_unknown_flag(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload() );

		$this->assertSame( 'local', $rc->resolve( 'no_such_flag', 'local', 'live' ) );
	}

	public function test_resolve_modes_are_isolated(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload( false ) );
		$rc->apply( 'test', $this->valid_payload( true ) );

		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		$this->assertTrue( $rc->resolve( 'optimized_checkout', false, 'test' ) );
	}

	public function test_get_flag_returns_null_when_no_cache(): void {
		$rc = new WC_Stripe_Remote_Config();
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'live' ) );
	}

	public function test_get_flag_returns_null_for_unknown_flag(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload() );
		$this->assertNull( $rc->get_flag( 'no_such_flag', 'live' ) );
	}

	public function test_get_flag_returns_value_from_cache(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->valid_payload( false ) );
		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
	}

	public function test_get_flag_treats_corrupt_cache_option_as_no_cache(): void {
		update_option( '_wcstripe_remote_config_live', 'corrupt-string-not-array' );

		$rc = new WC_Stripe_Remote_Config();
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'live' ) );
	}

	public function test_get_flag_treats_wrong_schema_version_as_no_cache(): void {
		update_option(
			'_wcstripe_remote_config_live',
			[
				'schema_version' => 999,
				'fetched_at'     => time(),
				'ttl'            => 86400,
				'flags'          => [ 'optimized_checkout' => [ 'value' => false ] ],
			]
		);

		$rc = new WC_Stripe_Remote_Config();
		$this->assertNull( $rc->get_flag( 'optimized_checkout', 'live' ) );
	}
}
