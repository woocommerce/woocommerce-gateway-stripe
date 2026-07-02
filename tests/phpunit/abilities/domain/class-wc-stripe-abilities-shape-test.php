<?php
/**
 * Parameterized shape + wiring tests for the read abilities.
 *
 * Covers the mechanical assertions (callbacks wired correctly, annotations
 * set correctly, show_in_rest + mcp.public on, permission callback
 * resolves to manage_woocommerce) for every read ability the plugin registers.
 *
 * @package WooCommerce_Stripe
 */

/**
 * @covers WC_Stripe_Ability_Get_Account_Summary
 * @covers WC_Stripe_Ability_Get_Charges
 * @covers WC_Stripe_Ability_Get_Charge
 * @covers WC_Stripe_Ability_Get_Payment_Intent
 * @covers WC_Stripe_Ability_Get_Disputes
 * @covers WC_Stripe_Ability_Get_Dispute
 * @covers WC_Stripe_Ability_Get_Payouts
 * @covers WC_Stripe_Ability_Get_Payout
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
		remove_all_filters( 'user_has_cap' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Every read ability registered by the plugin that calls the Stripe API directly.
	 *
	 * @return array<string, array{0: class-string, 1: string}>
	 */
	public function read_ability_provider(): array {
		return [
			'get-account-summary'      => [ WC_Stripe_Ability_Get_Account_Summary::class, 'woocommerce-gateway-stripe/get-account-summary' ],
			'get-charges'              => [ WC_Stripe_Ability_Get_Charges::class, 'woocommerce-gateway-stripe/get-charges' ],
			'get-charge'               => [ WC_Stripe_Ability_Get_Charge::class, 'woocommerce-gateway-stripe/get-charge' ],
			'get-payment-intent'       => [ WC_Stripe_Ability_Get_Payment_Intent::class, 'woocommerce-gateway-stripe/get-payment-intent' ],
			'get-disputes'             => [ WC_Stripe_Ability_Get_Disputes::class, 'woocommerce-gateway-stripe/get-disputes' ],
			'get-dispute'              => [ WC_Stripe_Ability_Get_Dispute::class, 'woocommerce-gateway-stripe/get-dispute' ],
			'get-payouts'              => [ WC_Stripe_Ability_Get_Payouts::class, 'woocommerce-gateway-stripe/get-payouts' ],
			'get-payout'               => [ WC_Stripe_Ability_Get_Payout::class, 'woocommerce-gateway-stripe/get-payout' ],
			'get-balance'              => [ WC_Stripe_Ability_Get_Balance::class, 'woocommerce-gateway-stripe/get-balance' ],
			'get-balance-transactions' => [ WC_Stripe_Ability_Get_Balance_Transactions::class, 'woocommerce-gateway-stripe/get-balance-transactions' ],
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
			WC_Stripe_Ability_Base::CATEGORY_SLUG,
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

	/**
	 * @dataProvider read_ability_provider
	 */
	public function test_ability_permission_callback_allows_administrators( string $class ) {
		$args     = $class::get_registration_args();
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// WC's role-cap binding for `manage_woocommerce` isn't reproduced by
		// the WP_UnitTestCase transaction rollback; inject the cap so this
		// exercises the wiring rather than WC's bootstrap order.
		add_filter(
			'user_has_cap',
			static function ( $allcaps ) {
				$allcaps['manage_woocommerce'] = true;
				return $allcaps;
			}
		);

		$this->assertTrue(
			(bool) call_user_func( $args['permission_callback'] ),
			"$class permission_callback must allow users with manage_woocommerce."
		);
	}

	/**
	 * The reference ability must accept `execute([])` — it is the only
	 * ability callable with no arguments, the property MCP clients rely on
	 * when first probing the tool surface.
	 */
	public function test_get_account_summary_accepts_zero_arg_execute() {
		$schema = WC_Stripe_Ability_Get_Account_Summary::get_registration_args()['input_schema'];

		$this->assertSame( [], $schema['properties'], 'get-account-summary must expose no properties.' );
		$this->assertSame( [], $schema['required'] ?? [], 'get-account-summary must require no input.' );
		$this->assertFalse( $schema['additionalProperties'] ?? true, 'get-account-summary must reject stray inputs.' );
	}

	/**
	 * get-account-summary authors an `id` field on top of its controller
	 * response, so its output_schema is partial: it declares `id` for MCP
	 * discovery while letting the controller-owned payload pass through
	 * (additionalProperties: true).
	 */
	public function test_get_account_summary_output_schema_declares_authored_id_field() {
		$args = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();

		$this->assertArrayHasKey( 'output_schema', $args );
		$schema = $args['output_schema'];
		$this->assertSame( 'object', $schema['type'] );
		$this->assertTrue( $schema['additionalProperties'] ?? false, 'output_schema must be partial so the controller payload passes through.' );

		$id_field = $schema['properties']['id'] ?? null;
		$this->assertIsArray( $id_field );
		$this->assertContains( 'string', (array) ( $id_field['type'] ?? [] ) );
		$this->assertContains( 'null', (array) ( $id_field['type'] ?? [] ) );
	}
}
