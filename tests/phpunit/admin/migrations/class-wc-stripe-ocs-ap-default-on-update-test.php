<?php

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for WC_Stripe_OCS_AP_Default_On_Update.
 */
class WC_Stripe_OCS_AP_Default_On_Update_Test extends WP_UnitTestCase {

	private const MIGRATION_FLAG_OPTION       = 'wc_stripe_ocs_ap_default_on_migration_ran';
	private const SHOW_OCS_AP_BANNER_OPTION   = 'wc_stripe_show_ocs_ap_banner';
	private const SHOW_AP_ONLY_BANNER_OPTION  = 'wc_stripe_show_ap_only_banner';
	private const SHOW_OCS_ONLY_BANNER_OPTION = 'wc_stripe_show_ocs_only_banner';
	private const STRIPE_VERSION_OPTION       = 'wc_stripe_version';

	public function set_up() {
		parent::set_up();
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::SHOW_OCS_AP_BANNER_OPTION );
		delete_option( self::SHOW_AP_ONLY_BANNER_OPTION );
		delete_option( self::SHOW_OCS_ONLY_BANNER_OPTION );
		delete_option( self::STRIPE_VERSION_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
	}

	public function tear_down() {
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::SHOW_OCS_AP_BANNER_OPTION );
		delete_option( self::SHOW_AP_ONLY_BANNER_OPTION );
		delete_option( self::SHOW_OCS_ONLY_BANNER_OPTION );
		delete_option( self::STRIPE_VERSION_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
		parent::tear_down();
	}

	/**
	 * Build a partial mock so AP-availability and account-created reads don't
	 * touch the Stripe API.
	 *
	 * @param string|null $ap_unavailable_reason Reason AP is unavailable, or null when available.
	 * @param int|null    $created               Unix timestamp returned by get_account_created_ts().
	 *
	 * @return MockObject|WC_Stripe_OCS_AP_Default_On_Update
	 */
	private function build_migration( ?string $ap_unavailable_reason = null, ?int $created = null ) {
		$migration = $this->getMockBuilder( WC_Stripe_OCS_AP_Default_On_Update::class )
							->disableOriginalConstructor()
							->onlyMethods( [ 'get_ap_unavailable_reason', 'get_account_created_ts' ] )
							->getMock();
		$migration->method( 'get_ap_unavailable_reason' )->willReturn( $ap_unavailable_reason );
		$migration->method( 'get_account_created_ts' )->willReturn( $created );
		return $migration;
	}

	public function test_ran_once_guard_short_circuits_subsequent_runs() {
		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'Flip must not run when guard is set.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Flip must not run when guard is set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ), 'Banner A option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ), 'Banner B option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ), 'OCS-only banner option must not be set.' );
	}

	public function test_new_install_writes_only_ran_once_flag() {
		// No wc_stripe_version option present — simulates a new install.
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			]
		);

		$this->build_migration()->maybe_migrate();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'New install must not flip OC.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'New install must not flip AP.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ) );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ) );
		$this->assertFalse( get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ) );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	public function test_already_on_10_8_writes_only_ran_once_flag() {
		update_option( self::STRIPE_VERSION_OPTION, '10.8.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			]
		);

		$this->build_migration()->maybe_migrate( '10.8.0' );

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'Already-10.8 must not flip OC.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Already-10.8 must not flip AP.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ) );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ) );
		$this->assertFalse( get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ) );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * Migration scenario matrix. Each row drives one execution of maybe_migrate()
	 * and asserts all three banner-visibility options and post-flip gateway state.
	 *
	 * @dataProvider audience_matrix_provider
	 */
	public function test_migration_scenarios(
		string $previous_version,
		string $oc_pre,
		string $ap_pre,
		?string $ap_unavailable_reason,
		?int $account_created,
		string $expected_show_ocs_ap_banner,
		string $expected_show_ap_banner,
		string $expected_show_ocs_only_banner,
		string $expected_oc_after,
		string $expected_ap_after
	) {
		update_option( self::STRIPE_VERSION_OPTION, $previous_version );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => $oc_pre,
				'adaptive_pricing'           => $ap_pre,
			]
		);

		$this->build_migration( $ap_unavailable_reason, $account_created )->maybe_migrate( $previous_version );

		$created_label = null === $account_created ? 'null' : (string) $account_created;
		$context       = sprintf( 'prev=%s oc=%s ap=%s ap_unavail=%s created=%s', $previous_version, $oc_pre, $ap_pre, $ap_unavailable_reason ?? 'available', $created_label );

		$this->assertSame(
			$expected_show_ocs_ap_banner,
			get_option( self::SHOW_OCS_AP_BANNER_OPTION ),
			sprintf( 'OCS+AP banner flag mismatch for %s', $context )
		);
		$this->assertSame(
			$expected_show_ap_banner,
			get_option( self::SHOW_AP_ONLY_BANNER_OPTION ),
			sprintf( 'AP-only banner flag mismatch for %s', $context )
		);
		$this->assertSame(
			$expected_show_ocs_only_banner,
			get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ),
			sprintf( 'OCS-only banner flag mismatch for %s', $context )
		);

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			$expected_oc_after,
			$stored['optimized_checkout_element'] ?? 'no',
			sprintf( 'optimized_checkout_element mismatch after flip for %s', $context )
		);
		$this->assertSame(
			$expected_ap_after,
			$stored['adaptive_pricing'] ?? 'no',
			sprintf( 'adaptive_pricing mismatch after flip for %s', $context )
		);
	}

	public function audience_matrix_provider(): array {
		$old_ts = 1747008000;   // 2025-05-12 — pre-10.7 era.
		$new_ts = 1779148800;   // 2026-05-19 — post-10.7 era.

		return [
			// prev_ver, oc_pre, ap_pre, ap_unavailable_reason, created, show_ocs_ap, show_ap_only, show_ocs_only, oc_after, ap_after.
			'both-on backbook'                       => [ '10.7.0', 'yes', 'yes', null, $new_ts, 'no', 'no', 'no', 'yes', 'yes' ],
			'OC-only backbook old-account'           => [ '10.7.0', 'yes', 'no', null, $old_ts, 'no', 'yes', 'no', 'yes', 'yes' ],
			'OC-only frontbook-10.7 disabled-AP'     => [ '10.7.0', 'yes', 'no', null, $new_ts, 'no', 'no', 'no', 'yes', 'no' ],
			'both-off backbook old-account'          => [ '10.7.0', 'no', 'no', null, $old_ts, 'yes', 'no', 'no', 'yes', 'yes' ],
			'both-off standard-account null-created' => [ '10.7.0', 'no', 'no', null, null, 'yes', 'no', 'no', 'yes', 'yes' ],
			'both-off frontbook-10.7 disabled-both'  => [ '10.7.0', 'no', 'no', null, $new_ts, 'no', 'no', 'no', 'no', 'no' ],
			'AP-only-on frontbook disabled-OC'       => [ '10.7.0', 'no', 'yes', null, $new_ts, 'no', 'no', 'no', 'no', 'yes' ],
			'AP-only-on backbook OC-newly-enabled'   => [ '10.7.0', 'no', 'yes', null, $old_ts, 'no', 'no', 'no', 'yes', 'yes' ],
			'previous 10.6 both-off recent-account'  => [ '10.6.0', 'no', 'no', null, $new_ts, 'yes', 'no', 'no', 'yes', 'yes' ],
			'previous 10.6 OC-only recent-account'   => [ '10.6.0', 'yes', 'no', null, $new_ts, 'no', 'yes', 'no', 'yes', 'yes' ],
			'India backbook both-off'                => [ '10.7.0', 'no', 'no', 'account-country', $old_ts, 'no', 'no', 'yes', 'yes', 'no' ],
			'India backbook OC-only'                 => [ '10.7.0', 'yes', 'no', 'account-country', $old_ts, 'no', 'no', 'no', 'yes', 'no' ],
			'India frontbook disabled-OC'            => [ '10.7.0', 'no', 'no', 'account-country', $new_ts, 'no', 'no', 'no', 'no', 'no' ],
			'currency-unavailable both-off'          => [ '10.7.0', 'no', 'no', 'store-currency-not-settlement-currency', $old_ts, 'no', 'no', 'yes', 'yes', 'no' ],
			'currency-unavailable OC-only'           => [ '10.7.0', 'yes', 'no', 'no-settlement-currencies', $old_ts, 'no', 'no', 'no', 'yes', 'no' ],
		];
	}

	public function test_migration_preserves_unrelated_settings_keys() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
				'pmc_enabled'                => 'no',
			]
		);

		$this->build_migration( null, 1747008000 )->maybe_migrate( '10.7.0' );

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stored['optimized_checkout_element'], 'OC must be flipped to yes.' );
		$this->assertSame( 'yes', $stored['adaptive_pricing'], 'AP must be flipped to yes.' );
		$this->assertSame( 'no', $stored['pmc_enabled'], 'Unrelated stored keys must survive.' );
	}
}
