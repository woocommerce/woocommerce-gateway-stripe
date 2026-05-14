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
}
