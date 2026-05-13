<?php

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update_Test
 *
 * Unit tests for the change-payment-method location migration.
 */
class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update_Test extends WP_UnitTestCase {

	private const MIGRATION_FLAG_OPTION = 'wc_stripe_express_checkout_cpm_location_migrated';

	/**
	 * Stripe gateway mock.
	 *
	 * @var MockObject|WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway_mock;

	public function set_up() {
		parent::set_up();

		delete_option( self::MIGRATION_FLAG_OPTION );

		$this->gateway_mock = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
									->disableOriginalConstructor()
									->getMock();
	}

	public function tear_down() {
		delete_option( self::MIGRATION_FLAG_OPTION );
		parent::tear_down();
	}

	/**
	 * Build a partial mock of the migration. `get_gateway()` returns the
	 * shared gateway mock; `is_subscriptions_enabled()` is stubbed so we
	 * don't depend on Subscriptions plugin state.
	 *
	 * @param bool $subscriptions_enabled Value `is_subscriptions_enabled()` should return.
	 *
	 * @return MockObject|WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update
	 */
	private function build_migration( bool $subscriptions_enabled = true ) {
		$migration = $this->getMockBuilder( WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update::class )
							->disableOriginalConstructor()
							->onlyMethods( [ 'get_gateway', 'is_subscriptions_enabled' ] )
							->getMock();
		$migration->method( 'get_gateway' )->willReturn( $this->gateway_mock );
		$migration->method( 'is_subscriptions_enabled' )->willReturn( $subscriptions_enabled );
		return $migration;
	}

	/**
	 * The merchant had every pre-PR default location enabled. The migration
	 * appends `change_payment_method` so the upgrade preserves the
	 * "everything on" state.
	 */
	public function test_appends_change_payment_method_when_all_legacy_locations_enabled() {
		$this->gateway_mock->method( 'get_option' )
							->with( 'express_checkout_button_locations' )
							->willReturn( [ 'product', 'cart', 'checkout' ] );

		$this->gateway_mock->expects( $this->once() )
							->method( 'update_option' )
							->with(
								'express_checkout_button_locations',
								[ 'product', 'cart', 'checkout', 'change_payment_method' ]
							);

		$this->build_migration()->maybe_migrate();

		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * The merchant disabled at least one of the pre-PR defaults. We treat
	 * this as a deliberate customization and don't add the new location.
	 */
	public function test_leaves_partial_legacy_set_alone() {
		$this->gateway_mock->method( 'get_option' )
							->with( 'express_checkout_button_locations' )
							->willReturn( [ 'checkout' ] );

		$this->gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->build_migration()->maybe_migrate();

		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * `change_payment_method` is already in the stored set (e.g. fresh install
	 * picked up the new default, or the merchant added it manually). Nothing
	 * to do.
	 */
	public function test_skips_when_change_payment_method_already_present() {
		$this->gateway_mock->method( 'get_option' )
							->with( 'express_checkout_button_locations' )
							->willReturn( [ 'product', 'cart', 'checkout', 'change_payment_method' ] );

		$this->gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->build_migration()->maybe_migrate();

		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * The migration must not run a second time. A merchant who removes
	 * `change_payment_method` after the first run shouldn't have it
	 * silently re-added on the next version bump.
	 */
	public function test_does_not_run_when_flag_already_set() {
		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );

		$this->gateway_mock->expects( $this->never() )->method( 'get_option' );
		$this->gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->build_migration()->maybe_migrate();
	}

	/**
	 * Defensive case: a corrupted/non-array stored value shouldn't crash the
	 * migration, just leave the option alone and mark the flag.
	 */
	public function test_leaves_non_array_stored_value_alone() {
		$this->gateway_mock->method( 'get_option' )
							->with( 'express_checkout_button_locations' )
							->willReturn( '' );

		$this->gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->build_migration()->maybe_migrate();

		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * Subscriptions isn't installed: skip without touching gateway state
	 * and leave the flag unset so a later plugin update can run the
	 * migration if the merchant installs Subscriptions.
	 */
	public function test_skips_and_does_not_set_flag_when_subscriptions_not_enabled() {
		$this->gateway_mock->expects( $this->never() )->method( 'get_option' );
		$this->gateway_mock->expects( $this->never() )->method( 'update_option' );

		$this->build_migration( false )->maybe_migrate();

		$this->assertFalse( get_option( self::MIGRATION_FLAG_OPTION ) );
	}
}
