<?php
/**
 * Tests for WC_Stripe_Ability_Get_Account_Summary.
 *
 * @package WooCommerce_Stripe
 */

/**
 * @covers WC_Stripe_Ability_Get_Account_Summary
 */
class WC_Stripe_Ability_Get_Account_Summary_Test extends WP_UnitTestCase {

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

	public function test_registration_args_callbacks_are_wired_to_domain_class() {
		$args = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();

		$this->assertSame(
			[ WC_Stripe_Ability_Get_Account_Summary::class, 'execute' ],
			$args['execute_callback'],
			'execute_callback must point to WC_Stripe_Ability_Get_Account_Summary::execute, not a sibling Domain class.'
		);

		$this->assertSame(
			[ WC_Stripe_Abilities_Registrar::class, 'can_manage_woocommerce' ],
			$args['permission_callback'],
			'permission_callback must point to WC_Stripe_Abilities_Registrar::can_manage_woocommerce.'
		);
	}

	public function test_ability_name() {
		$this->assertSame( 'woocommerce-gateway-stripe/get-account-summary', WC_Stripe_Ability_Get_Account_Summary::get_name() );
	}

	public function test_ability_category() {
		$args = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();
		$this->assertSame( WC_Stripe_Ability_Base::CATEGORY_SLUG, $args['category'] );
	}

	public function test_ability_annotations() {
		$args = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();

		$this->assertArrayHasKey( 'meta', $args );
		$annotations = $args['meta']['annotations'] ?? [];
		$this->assertTrue( $annotations['readonly'] ?? false, 'get-account-summary should be readonly.' );
		$this->assertFalse( $annotations['destructive'] ?? true, 'get-account-summary should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'] ?? false, 'get-account-summary should be idempotent.' );
	}

	public function test_output_schema_declares_account_id_field() {
		$args = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();

		$this->assertArrayHasKey( 'output_schema', $args, 'get-account-summary must declare its authored output fields for MCP discovery.' );

		$schema = $args['output_schema'];
		$this->assertSame( 'object', $schema['type'] ?? null );
		$this->assertTrue( $schema['additionalProperties'] ?? false, 'output_schema must be partial — controller-owned fields pass through unmodelled.' );
		$this->assertArrayHasKey( 'id', $schema['properties'] ?? [], 'output_schema must declare the id field that this ability ensures is present in the response.' );

		$id_field = $schema['properties']['id'];
		$this->assertContains( 'string', (array) ( $id_field['type'] ?? [] ) );
		$this->assertContains( 'null', (array) ( $id_field['type'] ?? [] ) );
	}

	public function test_ability_rest_and_mcp_exposure() {
		$args = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();

		$this->assertTrue(
			$args['meta']['show_in_rest'] ?? false,
			'get-account-summary must be exposed via show_in_rest for the Abilities API REST bridge.'
		);
		$this->assertTrue(
			$args['meta']['mcp']['public'] ?? false,
			'get-account-summary must be opted into MCP discovery via meta.mcp.public.'
		);
	}

	public function test_permission_callback_denies_subscribers() {
		$args          = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse(
			call_user_func( $args['permission_callback'] ),
			'Wired permission_callback must deny subscribers.'
		);
	}

	public function test_permission_callback_allows_administrators() {
		$args     = WC_Stripe_Ability_Get_Account_Summary::get_registration_args();
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
			call_user_func( $args['permission_callback'] ),
			'Wired permission_callback must allow users with manage_woocommerce.'
		);
	}
}
