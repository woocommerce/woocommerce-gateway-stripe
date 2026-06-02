<?php
/**
 * ID-validation tests for the by-id read abilities.
 *
 * Covers the three-check ID guard documented in
 * `wp-abilities-api/references/input-schema-gotchas.md` — `isset` +
 * `is_string` + non-empty (not `empty()`, which would false-reject "0").
 *
 * @package WooCommerce_Stripe
 */

/**
 * @covers WC_Stripe_Ability_Get_Charge
 * @covers WC_Stripe_Ability_Get_Payment_Intent
 * @covers WC_Stripe_Ability_Get_Dispute
 * @covers WC_Stripe_Ability_Get_Payout
 */
class WC_Stripe_Abilities_Id_Validation_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for these tests.' );
		}
	}

	/**
	 * @return array<string, array{0: class-string, 1: string, 2: string}>
	 *                Each row: [ Domain class, required-input-key, error-code-suffix ]
	 */
	public function by_id_ability_provider(): array {
		return [
			'get-charge'         => [ WC_Stripe_Ability_Get_Charge::class, 'charge_id', 'charge_id' ],
			'get-payment-intent' => [ WC_Stripe_Ability_Get_Payment_Intent::class, 'payment_intent_id', 'payment_intent_id' ],
			'get-dispute'        => [ WC_Stripe_Ability_Get_Dispute::class, 'dispute_id', 'dispute_id' ],
			'get-payout'         => [ WC_Stripe_Ability_Get_Payout::class, 'payout_id', 'payout_id' ],
		];
	}

	/**
	 * @dataProvider by_id_ability_provider
	 */
	public function test_execute_returns_wp_error_when_id_is_missing( string $class, string $field, string $code_suffix ) {
		$result = $class::execute( [] );
		$this->assertInstanceOf( WP_Error::class, $result, "$class::execute() must return WP_Error when $field is missing." );
		$this->assertSame( "wc_stripe_missing_$code_suffix", $result->get_error_code() );
	}

	/**
	 * @dataProvider by_id_ability_provider
	 */
	public function test_execute_returns_wp_error_when_id_is_empty_string( string $class, string $field, string $code_suffix ) {
		$result = $class::execute( [ $field => '' ] );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( "wc_stripe_missing_$code_suffix", $result->get_error_code() );
	}

	/**
	 * Regression guard for the `empty()` antipattern documented in
	 * wp-abilities-api/references/input-schema-gotchas.md §3 — a callback
	 * that uses `empty()` would false-pass an integer 123 and fall through
	 * to rawurlencode() with a non-string argument.
	 *
	 * @dataProvider by_id_ability_provider
	 */
	public function test_execute_returns_wp_error_when_id_is_not_a_string( string $class, string $field, string $code_suffix ) {
		$result = $class::execute( [ $field => 123 ] );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( "wc_stripe_missing_$code_suffix", $result->get_error_code() );
	}

	/**
	 * @return array<string, array{0: class-string, 1: int}>
	 *                Each row: [ Domain class, expected expand allowlist size ]
	 */
	public function expandable_ability_provider(): array {
		return [
			'get-charge'         => [ WC_Stripe_Ability_Get_Charge::class, 5 ],
			'get-payment-intent' => [ WC_Stripe_Ability_Get_Payment_Intent::class, 4 ],
			'get-dispute'        => [ WC_Stripe_Ability_Get_Dispute::class, 11 ],
		];
	}

	/**
	 * @dataProvider expandable_ability_provider
	 */
	public function test_expand_input_is_declared_as_array_with_enum_allowlist( string $class, int $expected_size ) {
		$args   = $class::get_registration_args();
		$schema = $args['input_schema']['properties']['expand'] ?? null;

		$this->assertIsArray( $schema, "$class must declare an `expand` input property." );
		$this->assertSame( 'array', $schema['type'] ?? null );
		$this->assertTrue( $schema['uniqueItems'] ?? false, '`expand` must enforce unique items so the same field can\'t be inflated twice.' );
		$this->assertSame( $expected_size, $schema['maxItems'] ?? null, '`expand` maxItems must match the size of the allowlist.' );

		$items = $schema['items'] ?? [];
		$this->assertSame( 'string', $items['type'] ?? null );
		$this->assertIsArray( $items['enum'] ?? null );
		$this->assertCount( $expected_size, $items['enum'], '`expand.items.enum` must enumerate exactly the documented Stripe expandable fields.' );

		// Mirror EXPANDABLE_FIELDS const so a future drift between schema and
		// const surfaces here.
		$this->assertSame( $class::EXPANDABLE_FIELDS, $items['enum'] );
	}
}
