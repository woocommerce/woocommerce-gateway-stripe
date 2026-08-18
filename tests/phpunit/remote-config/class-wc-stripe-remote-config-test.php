<?php
/**
 * @package WooCommerce/Stripe
 */

class WC_Stripe_Remote_Config_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION, 'yes' );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
	}

	public function tear_down(): void {
		delete_option( WC_Stripe_Remote_Config_Flags::ENABLED_OVERRIDE_OPTION );
		WC_Stripe_Remote_Config::reset_in_memory_cache();
		delete_option( '_wcstripe_remote_config_live' );
		delete_option( '_wcstripe_remote_config_test' );
		parent::tear_down();
	}

	private function get_valid_payload( bool $optimized_checkout_flag_value = false ): array {
		return [
			'flags'        => [ 'optimized_checkout' => [ 'value' => $optimized_checkout_flag_value ] ],
			'generated_at' => '2026-05-09T12:00:00Z',
		];
	}

	public function test_apply_writes_to_per_mode_option_and_returns_true(): void {
		$rc = new WC_Stripe_Remote_Config();
		$this->assertTrue( $rc->apply( 'live', $this->get_valid_payload( false ) ) );

		$stored = get_option( '_wcstripe_remote_config_live' );
		$this->assertIsArray( $stored );
		$this->assertSame( 1, $stored['schema_version'] );
		$this->assertSame( false, $stored['flags']['optimized_checkout']['value'] );
		$this->assertFalse( get_option( '_wcstripe_remote_config_test' ) );
	}

	/**
	 * @dataProvider provide_invalid_payloads
	 */
	public function test_apply_rejects_invalid_payload( callable $mutate ): void {
		$rc      = new WC_Stripe_Remote_Config();
		$payload = $this->get_valid_payload();
		$mutate( $payload );

		$this->assertFalse( $rc->apply( 'live', $payload ) );
		$this->assertFalse( get_option( '_wcstripe_remote_config_live' ) );
	}

	public function provide_invalid_payloads(): array {
		return [
			'missing generated_at' => [
				static function ( array &$p ): void {
					unset( $p['generated_at'] );
				},
			],
			'non-iso generated_at' => [
				static function ( array &$p ): void {
					$p['generated_at'] = 'not-a-date';
				},
			],
			'wrong flag type'      => [
				static function ( array &$p ): void {
					$p['flags']['optimized_checkout']['value'] = 'true';
				},
			],
			'oversized payload'    => [
				static function ( array &$p ): void {
					$p['_padding'] = str_repeat( 'a', WC_Stripe_Remote_Config_Flags::MAX_PAYLOAD_BYTES + 1 );
				},
			],
		];
	}

	public function test_apply_drops_unknown_flag_names_silently(): void {
		$rc                               = new WC_Stripe_Remote_Config();
		$payload                          = $this->get_valid_payload();
		$payload['flags']['unknown_flag'] = [ 'value' => true ];

		$this->assertTrue( $rc->apply( 'live', $payload ) );
		$stored = get_option( '_wcstripe_remote_config_live' );
		$this->assertArrayNotHasKey( 'unknown_flag', $stored['flags'] );
		$this->assertArrayHasKey( 'optimized_checkout', $stored['flags'] );
	}

	public function test_apply_circuit_breaker_keeps_previous_cache_on_validation_failure(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->get_valid_payload( false ) );

		$bad_payload = $this->get_valid_payload();
		$bad_payload['flags']['optimized_checkout']['value'] = 'string-not-bool';

		$this->assertFalse( $rc->apply( 'live', $bad_payload ) );

		$stored = get_option( '_wcstripe_remote_config_live' );
		$this->assertSame( false, $stored['flags']['optimized_checkout']['value'] );
	}

	public function test_get_cache_snapshot_returns_null_when_no_cache(): void {
		$rc = new WC_Stripe_Remote_Config();
		$this->assertNull( $rc->get_cache_snapshot( 'live' ) );
	}

	public function test_get_cache_snapshot_returns_flags_and_timestamp_after_apply(): void {
		$before = time();
		$rc     = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->get_valid_payload( false ) );
		$after = time();

		$snapshot = $rc->get_cache_snapshot( 'live' );
		$this->assertIsArray( $snapshot );
		$this->assertSame( [ 'fetched_at', 'flags' ], array_keys( $snapshot ) );
		$this->assertGreaterThanOrEqual( $before, $snapshot['fetched_at'] );
		$this->assertLessThanOrEqual( $after, $snapshot['fetched_at'] );
		$this->assertSame( [ 'optimized_checkout' => [ 'value' => false ] ], $snapshot['flags'] );
	}

	public function test_resolve_remote_wins_when_present_else_local(): void {
		$rc = new WC_Stripe_Remote_Config();

		// No remote yet: local fallback.
		$this->assertTrue( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		$this->assertSame( 'local', $rc->resolve( 'no_such_flag', 'local', 'live' ) );

		// Remote present: remote wins.
		$rc->apply( 'live', $this->get_valid_payload( false ) );
		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
	}

	public function test_resolve_modes_are_isolated(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->get_valid_payload( false ) );
		$rc->apply( 'test', $this->get_valid_payload( true ) );

		$this->assertFalse( $rc->resolve( 'optimized_checkout', true, 'live' ) );
		$this->assertTrue( $rc->resolve( 'optimized_checkout', false, 'test' ) );
	}

	/**
	 * @dataProvider provide_get_flag_null_cases
	 */
	public function test_get_flag_returns_null( callable $arrange, string $flag_name ): void {
		$arrange();

		$rc = new WC_Stripe_Remote_Config();
		$this->assertNull( $rc->get_flag( $flag_name, 'live' ) );
	}

	public function provide_get_flag_null_cases(): array {
		$valid_payload = [
			'flags'        => [ 'optimized_checkout' => [ 'value' => false ] ],
			'generated_at' => '2026-05-09T12:00:00Z',
		];

		return [
			'no cache at all'                 => [
				static function (): void {
					// nothing arranged
				},
				'optimized_checkout',
			],
			'unknown flag with cache present' => [
				static function () use ( $valid_payload ): void {
					( new WC_Stripe_Remote_Config() )->apply( 'live', $valid_payload );
				},
				'no_such_flag',
			],
			'corrupt cache option'            => [
				static function (): void {
					update_option( '_wcstripe_remote_config_live', 'corrupt-string-not-array' );
				},
				'optimized_checkout',
			],
			'wrong schema_version'            => [
				static function (): void {
					update_option(
						'_wcstripe_remote_config_live',
						[
							'schema_version' => 999,
							'fetched_at'     => time(),
							'flags'          => [ 'optimized_checkout' => [ 'value' => false ] ],
						]
					);
				},
				'optimized_checkout',
			],
		];
	}

	public function test_get_flag_returns_value_from_cache(): void {
		$rc = new WC_Stripe_Remote_Config();
		$rc->apply( 'live', $this->get_valid_payload( false ) );
		$this->assertSame( false, $rc->get_flag( 'optimized_checkout', 'live' ) );
	}
}
