<?php
/**
 * Tests for WC_Stripe_Abilities_Registrar.
 *
 * @package WooCommerce_Stripe
 */

/**
 * @covers WC_Stripe_Abilities_Registrar
 */
class WC_Stripe_Abilities_Registrar_Test extends WP_UnitTestCase {

	const LOADER_FILTER  = 'woocommerce_ability_definition_classes';
	const FEATURE_FILTER = 'wc_stripe_abilities_enabled';

	public function tearDown(): void {
		remove_all_filters( self::LOADER_FILTER );
		remove_all_filters( self::FEATURE_FILTER );
		remove_all_filters( 'user_has_cap' );
		WC_Stripe_Abilities_Registrar::reset_initialized_for_testing();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Inject `manage_woocommerce` into the current user's effective caps.
	 *
	 * WooCommerce normally adds this cap to the administrator role via
	 * `WC_Install::create_roles()` on activation; the WP_UnitTestCase
	 * transaction rollback drops that role-cap mapping between tests, so
	 * an admin user created via `self::factory()` does not inherit it.
	 * Filter at `user_has_cap` so the assertion below exercises only the
	 * wiring (`current_user_can( 'manage_woocommerce' )`) and not WC's
	 * role-setup ordering.
	 */
	private static function grant_manage_woocommerce_to_current_user(): callable {
		$grant = static function ( $allcaps ) {
			$allcaps['manage_woocommerce'] = true;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $grant );
		return $grant;
	}

	public function test_init_is_no_op_when_feature_flag_disabled() {
		remove_all_filters( self::LOADER_FILTER );
		remove_all_filters( self::FEATURE_FILTER );

		WC_Stripe_Abilities_Registrar::init();

		$this->assertFalse(
			has_filter( self::LOADER_FILTER, [ WC_Stripe_Abilities_Registrar::class, 'append_classes' ] ),
			'Expected init() to short-circuit when the feature filter is unset (default false).'
		);
	}

	public function test_init_bails_when_loader_absent() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'AbilitiesLoader is present in this environment; bail test only applies when it is absent.' );
		}

		remove_all_filters( self::LOADER_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		WC_Stripe_Abilities_Registrar::init();

		$this->assertFalse(
			has_filter( self::LOADER_FILTER, [ WC_Stripe_Abilities_Registrar::class, 'append_classes' ] ),
			'init() must not wire the loader filter when AbilitiesLoader is absent.'
		);
	}

	public function test_init_wires_filter_when_loader_present() {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for this test.' );
		}

		remove_all_filters( self::LOADER_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		WC_Stripe_Abilities_Registrar::init();

		$this->assertNotFalse(
			has_filter( self::LOADER_FILTER, [ WC_Stripe_Abilities_Registrar::class, 'append_classes' ] ),
			'init() must wire the woocommerce_ability_definition_classes filter when AbilitiesLoader is present.'
		);
	}

	public function test_append_classes_returns_all_registered_ability_classes() {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for this test.' );
		}

		$classes = WC_Stripe_Abilities_Registrar::append_classes( [] );

		$expected = [
			WC_Stripe_Ability_Get_Account_Summary::class,
			WC_Stripe_Ability_Get_Charges::class,
			WC_Stripe_Ability_Get_Charge::class,
			WC_Stripe_Ability_Get_Payment_Intent::class,
			WC_Stripe_Ability_Get_Disputes::class,
			WC_Stripe_Ability_Get_Dispute::class,
			WC_Stripe_Ability_Get_Payouts::class,
			WC_Stripe_Ability_Get_Payout::class,
			WC_Stripe_Ability_Get_Balance::class,
			WC_Stripe_Ability_Get_Balance_Transactions::class,
		];

		foreach ( $expected as $class ) {
			$this->assertContains( $class, $classes, "append_classes() must include $class." );
		}
		$this->assertCount( count( $expected ), $classes, 'append_classes() must return exactly the registered Domain classes.' );
	}

	public function test_can_manage_woocommerce_matches_manage_woocommerce_capability() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse(
			WC_Stripe_Abilities_Registrar::can_manage_woocommerce(),
			'Subscribers must not pass the manage_woocommerce capability check.'
		);

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		self::grant_manage_woocommerce_to_current_user();
		$this->assertTrue(
			WC_Stripe_Abilities_Registrar::can_manage_woocommerce(),
			'Administrators with manage_woocommerce must pass the capability check.'
		);
	}

	public function test_init_is_idempotent_when_feature_flag_enabled() {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required for this test.' );
		}

		remove_all_filters( self::LOADER_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		WC_Stripe_Abilities_Registrar::init();
		WC_Stripe_Abilities_Registrar::init();

		$this->assertSame(
			1,
			self::count_filter_callbacks( self::LOADER_FILTER, [ WC_Stripe_Abilities_Registrar::class, 'append_classes' ] ),
			'init() must not double-register the ability_definition_classes filter callback.'
		);
	}

	/**
	 * Count how many times a specific callback is registered on a filter hook.
	 *
	 * has_filter() returns the priority of the first match or false; it
	 * cannot tell us "the same callback is hooked twice." Walk the global
	 * $wp_filter structure directly so the idempotency assertion is
	 * load-bearing.
	 *
	 * @param string $hook
	 * @param array  $callback
	 * @return int
	 */
	private static function count_filter_callbacks( string $hook, array $callback ): int {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				if ( $entry['function'] === $callback ) {
					++$count;
				}
			}
		}
		return $count;
	}
}
