<?php

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update_Test
 *
 * Unit tests for the change-payment-method location migration.
 */
class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update_Test extends WP_UnitTestCase {

	private const MIGRATION_FLAG_OPTION = 'wc_stripe_express_checkout_cpm_location_migrated';

	public function set_up() {
		parent::set_up();

		delete_option( self::MIGRATION_FLAG_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
	}

	public function tear_down() {
		delete_option( self::MIGRATION_FLAG_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
		parent::tear_down();
	}

	/**
	 * Build a partial mock of the migration. `is_subscriptions_enabled()` is
	 * stubbed so we don't depend on the Subscriptions plugin state.
	 *
	 * @param bool $subscriptions_enabled Value `is_subscriptions_enabled()` should return.
	 *
	 * @return MockObject|WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update
	 */
	private function build_migration( bool $subscriptions_enabled = true ) {
		$migration = $this->getMockBuilder( WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update::class )
							->disableOriginalConstructor()
							->onlyMethods( [ 'is_subscriptions_enabled' ] )
							->getMock();
		$migration->method( 'is_subscriptions_enabled' )->willReturn( $subscriptions_enabled );
		return $migration;
	}

	/**
	 * Seed the stored stripe settings, mirroring how the option is persisted in
	 * production. We deliberately go through `update_main_stripe_settings` so
	 * the `pre_update_option_woocommerce_stripe_settings` filter runs, just
	 * like production writes.
	 *
	 * @param array $settings Settings array to persist.
	 * @return void
	 */
	private function set_stored_settings( array $settings ): void {
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}

	/**
	 * The merchant had every pre-PR default location enabled. The migration
	 * appends `change_payment_method` so the upgrade preserves the
	 * "everything on" state. Unrelated stored keys (e.g. `pmc_enabled`) must
	 * survive the write — the migration must not clobber them.
	 */
	public function test_appends_change_payment_method_when_all_legacy_locations_enabled() {
		$this->set_stored_settings(
			[
				'express_checkout_button_locations' => [ 'product', 'cart', 'checkout' ],
				'pmc_enabled'                       => 'no',
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			[ 'product', 'cart', 'checkout', 'change_payment_method' ],
			$stored['express_checkout_button_locations']
		);
		$this->assertSame( 'no', $stored['pmc_enabled'], 'unrelated stored keys must survive the migration write.' );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * The merchant disabled at least one of the pre-PR defaults. We treat
	 * this as a deliberate customization and don't add the new location.
	 */
	public function test_leaves_partial_legacy_set_alone() {
		$this->set_stored_settings(
			[
				'express_checkout_button_locations' => [ 'checkout' ],
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [ 'checkout' ], $stored['express_checkout_button_locations'] );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * `change_payment_method` is already in the stored set (e.g. fresh install
	 * picked up the new default, or the merchant added it manually). Nothing
	 * to do.
	 */
	public function test_skips_when_change_payment_method_already_present() {
		$this->set_stored_settings(
			[
				'express_checkout_button_locations' => [ 'product', 'cart', 'checkout', 'change_payment_method' ],
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			[ 'product', 'cart', 'checkout', 'change_payment_method' ],
			$stored['express_checkout_button_locations']
		);
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * The migration must not run a second time. A merchant who removes
	 * `change_payment_method` after the first run shouldn't have it
	 * silently re-added on the next version bump.
	 */
	public function test_does_not_run_when_flag_already_set() {
		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
		$this->set_stored_settings(
			[
				'express_checkout_button_locations' => [ 'product', 'cart', 'checkout' ],
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			[ 'product', 'cart', 'checkout' ],
			$stored['express_checkout_button_locations']
		);
	}

	/**
	 * Defensive case: a corrupted/non-array stored value shouldn't crash the
	 * migration, just leave the option alone and mark the flag.
	 */
	public function test_leaves_non_array_stored_value_alone() {
		$this->set_stored_settings(
			[
				'express_checkout_button_locations' => '',
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( '', $stored['express_checkout_button_locations'] );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * Subscriptions isn't installed: skip without touching settings and
	 * leave the flag unset so a later plugin update can run the migration
	 * if the merchant installs Subscriptions.
	 */
	public function test_skips_and_does_not_set_flag_when_subscriptions_not_enabled() {
		$this->set_stored_settings(
			[
				'express_checkout_button_locations' => [ 'product', 'cart', 'checkout' ],
			]
		);

		$this->build_migration( false )->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			[ 'product', 'cart', 'checkout' ],
			$stored['express_checkout_button_locations']
		);
		$this->assertFalse( get_option( self::MIGRATION_FLAG_OPTION ) );
	}
}
