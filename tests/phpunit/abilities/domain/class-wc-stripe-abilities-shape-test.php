<?php
/**
 * Parameterized shape + wiring tests for the read abilities.
 *
 * Covers the mechanical assertions (callbacks wired correctly, annotations
 * set correctly, show_in_rest + mcp.public on, permission callback
 * resolves to manage_woocommerce) for every read ability except the
 * Phase II reference ability — which keeps its own per-ability test
 * file as a demonstration of the per-Domain-class testing pattern.
 *
 * @package WooCommerce_Stripe
 */

/**
 * @covers WC_Stripe_Ability_Get_Webhook_Status
 * @covers WC_Stripe_Ability_Get_Settings
 * @covers WC_Stripe_Ability_Get_Account_Keys_Fingerprints
 * @covers WC_Stripe_Ability_Get_Terminal_Locations
 * @covers WC_Stripe_Ability_Get_Agentic_Commerce_Sync_Status
 * @covers WC_Stripe_Ability_Get_Agentic_Commerce_Settings
 * @covers WC_Stripe_Ability_Get_Charges
 * @covers WC_Stripe_Ability_Get_Charge
 * @covers WC_Stripe_Ability_Get_Payment_Intent
 * @covers WC_Stripe_Ability_Get_Disputes
 * @covers WC_Stripe_Ability_Get_Dispute
 * @covers WC_Stripe_Ability_Get_Payouts
 * @covers WC_Stripe_Ability_Get_Balance
 * @covers WC_Stripe_Ability_Get_Balance_Transactions
 */
class WC_Stripe_Abilities_Shape_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for these tests.' );
		}
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Every read ability registered by the plugin (except the Phase II
	 * reference ability, which has its own per-ability test).
	 *
	 * @return array<string, array{0: class-string, 1: string}>
	 */
	public function read_ability_provider(): array {
		return [
			// Tier 1 — REST-backed.
			'get-webhook-status'               => [ WC_Stripe_Ability_Get_Webhook_Status::class, 'woocommerce-gateway-stripe/get-webhook-status' ],
			'get-settings'                     => [ WC_Stripe_Ability_Get_Settings::class, 'woocommerce-gateway-stripe/get-settings' ],
			'get-account-keys-fingerprints'    => [ WC_Stripe_Ability_Get_Account_Keys_Fingerprints::class, 'woocommerce-gateway-stripe/get-account-keys-fingerprints' ],
			'get-terminal-locations'           => [ WC_Stripe_Ability_Get_Terminal_Locations::class, 'woocommerce-gateway-stripe/get-terminal-locations' ],
			'get-agentic-commerce-sync-status' => [ WC_Stripe_Ability_Get_Agentic_Commerce_Sync_Status::class, 'woocommerce-gateway-stripe/get-agentic-commerce-sync-status' ],
			'get-agentic-commerce-settings'    => [ WC_Stripe_Ability_Get_Agentic_Commerce_Settings::class, 'woocommerce-gateway-stripe/get-agentic-commerce-settings' ],

			// Tier 2 — Stripe-API-backed (WooPayments parity).
			'get-charges'                      => [ WC_Stripe_Ability_Get_Charges::class, 'woocommerce-gateway-stripe/get-charges' ],
			'get-charge'                       => [ WC_Stripe_Ability_Get_Charge::class, 'woocommerce-gateway-stripe/get-charge' ],
			'get-payment-intent'               => [ WC_Stripe_Ability_Get_Payment_Intent::class, 'woocommerce-gateway-stripe/get-payment-intent' ],
			'get-disputes'                     => [ WC_Stripe_Ability_Get_Disputes::class, 'woocommerce-gateway-stripe/get-disputes' ],
			'get-dispute'                      => [ WC_Stripe_Ability_Get_Dispute::class, 'woocommerce-gateway-stripe/get-dispute' ],
			'get-payouts'                      => [ WC_Stripe_Ability_Get_Payouts::class, 'woocommerce-gateway-stripe/get-payouts' ],
			'get-balance'                      => [ WC_Stripe_Ability_Get_Balance::class, 'woocommerce-gateway-stripe/get-balance' ],
			'get-balance-transactions'         => [ WC_Stripe_Ability_Get_Balance_Transactions::class, 'woocommerce-gateway-stripe/get-balance-transactions' ],
		];
	}

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_name_matches_provider( string $class, string $expected_name ) {
		$this->assertSame( $expected_name, $class::get_name() );
	}

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_callbacks_are_wired_correctly( string $class ) {
		$args = $class::get_registration_args();

		$this->assertSame(
			[ $class, 'execute' ],
			$args['execute_callback'],
			"$class::execute_callback must point at the Domain class's own execute method."
		);
		$this->assertSame(
			[ WC_Stripe_Abilities_Registrar::class, 'can_manage_woocommerce' ],
			$args['permission_callback'],
			"$class::permission_callback must point at WC_Stripe_Abilities_Registrar::can_manage_woocommerce."
		);
	}

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_has_readonly_annotations( string $class ) {
		$args        = $class::get_registration_args();
		$annotations = $args['meta']['annotations'] ?? [];

		$this->assertTrue( $annotations['readonly'] ?? false, "$class must have readonly: true." );
		$this->assertFalse( $annotations['destructive'] ?? true, "$class must have destructive: false." );
		$this->assertTrue( $annotations['idempotent'] ?? false, "$class must have idempotent: true." );
	}

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_is_opted_into_rest_and_mcp( string $class ) {
		$args = $class::get_registration_args();

		$this->assertTrue(
			$args['meta']['show_in_rest'] ?? false,
			"$class must be exposed via show_in_rest for the Abilities API REST bridge."
		);
		$this->assertTrue(
			$args['meta']['mcp']['public'] ?? false,
			"$class must be opted into MCP discovery via meta.mcp.public."
		);
	}

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_uses_shared_woocommerce_category( string $class ) {
		$args = $class::get_registration_args();
		$this->assertSame(
			WC_Stripe_Abilities_Registrar::CATEGORY_SLUG,
			$args['category'],
			"$class must register under the shared `woocommerce` category."
		);
	}

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_permission_callback_denies_subscribers( string $class ) {
		$args          = $class::get_registration_args();
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse(
			call_user_func( $args['permission_callback'] ),
			"$class permission_callback must deny subscribers."
		);
	}
}
