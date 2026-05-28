<?php

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for WC_Stripe_OCS_AP_Default_On_Update.
 */
class WC_Stripe_OCS_AP_Default_On_Update_Test extends WP_UnitTestCase {

	private const MIGRATION_FLAG_OPTION      = 'wc_stripe_ocs_ap_default_on_migration_ran';
	private const SHOW_OCS_AP_BANNER_OPTION  = 'wc_stripe_show_ocs_ap_banner';
	private const SHOW_AP_ONLY_BANNER_OPTION = 'wc_stripe_show_ap_only_banner';
	private const STRIPE_VERSION_OPTION      = 'wc_stripe_version';

	public function set_up() {
		parent::set_up();
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::SHOW_OCS_AP_BANNER_OPTION );
		delete_option( self::SHOW_AP_ONLY_BANNER_OPTION );
		delete_option( self::STRIPE_VERSION_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
	}

	public function tear_down() {
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::SHOW_OCS_AP_BANNER_OPTION );
		delete_option( self::SHOW_AP_ONLY_BANNER_OPTION );
		delete_option( self::STRIPE_VERSION_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
		parent::tear_down();
	}

	/**
	 * Build a partial mock so account-country and account-created reads don't
	 * touch the Stripe API.
	 *
	 * @param string   $country Country code returned by get_account_country(). Empty string by default.
	 * @param int|null $created Unix timestamp returned by get_account_created_ts(). Null by default.
	 *
	 * @return MockObject|WC_Stripe_OCS_AP_Default_On_Update
	 */
	private function build_migration( string $country = '', ?int $created = null ) {
		$migration = $this->getMockBuilder( WC_Stripe_OCS_AP_Default_On_Update::class )
							->disableOriginalConstructor()
							->onlyMethods( [ 'get_account_country', 'get_account_created_ts' ] )
							->getMock();
		$migration->method( 'get_account_country' )->willReturn( $country );
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

		$this->build_migration()->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'Flip must not run when guard is set.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Flip must not run when guard is set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ), 'Banner A option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ), 'Banner B option must not be set.' );
	}

	public function test_new_install_writes_only_ran_once_flag() {
		// No wc_stripe_version option present — simulates a new install.
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			]
		);

		$this->build_migration()->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'New install must not flip OC.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'New install must not flip AP.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ) );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ) );
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

		$this->build_migration()->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'Already-10.8 must not flip OC.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Already-10.8 must not flip AP.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ) );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ) );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * Full audience-decision matrix. Each row drives one execution of maybe_run()
	 * and asserts both banner-visibility options and post-flip gateway state.
	 *
	 * @dataProvider audience_matrix_provider
	 */
	public function test_audience_decision_matrix(
		string $previous_version,
		string $oc_pre,
		string $ap_pre,
		string $country,
		?int $account_created,
		string $expected_show_a,
		string $expected_show_b,
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

		$this->build_migration( $country, $account_created )->maybe_run();

		$created_label = null === $account_created ? 'null' : (string) $account_created;
		$context       = sprintf( 'prev=%s oc=%s ap=%s country=%s created=%s', $previous_version, $oc_pre, $ap_pre, $country, $created_label );

		$this->assertSame(
			$expected_show_a,
			get_option( self::SHOW_OCS_AP_BANNER_OPTION ),
			sprintf( 'Banner A flag mismatch for %s', $context )
		);
		$this->assertSame(
			$expected_show_b,
			get_option( self::SHOW_AP_ONLY_BANNER_OPTION ),
			sprintf( 'Banner B flag mismatch for %s', $context )
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
			// previous_version, oc_pre, ap_pre, country, account_created, expected show_a, show_b, oc_after, ap_after.
			'both-on backbook'                      => [ '10.7.0', 'yes', 'yes', 'US', $new_ts, 'no', 'no', 'yes', 'yes' ],
			'OC-only backbook old-account'          => [ '10.7.0', 'yes', 'no', 'US', $old_ts, 'no', 'yes', 'yes', 'yes' ],
			'OC-only frontbook-10.7 disabled-AP'    => [ '10.7.0', 'yes', 'no', 'US', $new_ts, 'no', 'no', 'yes', 'no' ],
			'both-off backbook old-account'         => [ '10.7.0', 'no', 'no', 'US', $old_ts, 'yes', 'no', 'yes', 'yes' ],
			'both-off frontbook-10.7 disabled-both' => [ '10.7.0', 'no', 'no', 'US', $new_ts, 'no', 'no', 'no', 'no' ],
			'AP-only-on disabled-OC'                => [ '10.7.0', 'no', 'yes', 'US', $new_ts, 'no', 'no', 'no', 'yes' ],
			'previous 10.6 both-off recent-account' => [ '10.6.0', 'no', 'no', 'US', $new_ts, 'yes', 'no', 'yes', 'yes' ],
			'previous 10.6 OC-only recent-account'  => [ '10.6.0', 'yes', 'no', 'US', $new_ts, 'no', 'yes', 'yes', 'yes' ],
			'India backbook both-off'               => [ '10.7.0', 'no', 'no', 'IN', $old_ts, 'no', 'no', 'no', 'no' ],
			'India backbook OC-only'                => [ '10.7.0', 'yes', 'no', 'IN', $old_ts, 'no', 'no', 'yes', 'no' ],
			'unavailable-country backbook both-off' => [ '10.7.0', 'no', 'no', '', $old_ts, 'yes', 'no', 'yes', 'yes' ],
		];
	}

	public function test_flip_writes_yes_for_back_book_merchant() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
				'pmc_enabled'                => 'no',
			]
		);

		$this->build_migration( 'US', 1747008000 )->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stored['optimized_checkout_element'], 'OC must be flipped to yes.' );
		$this->assertSame( 'yes', $stored['adaptive_pricing'], 'AP must be flipped to yes.' );
		$this->assertSame( 'no', $stored['pmc_enabled'], 'Unrelated stored keys must survive.' );
	}

	public function test_flip_is_idempotent_for_already_on_merchant() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'yes',
				'adaptive_pricing'           => 'yes',
			]
		);

		$this->build_migration( 'US', 1779148800 )->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stored['optimized_checkout_element'] );
		$this->assertSame( 'yes', $stored['adaptive_pricing'] );
	}

	public function test_flip_skipped_for_india_merchants() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			]
		);

		// India geo-exclusion covers both banner and underlying feature flip,
		// regardless of frontbook status.
		$this->build_migration( 'IN', 1747008000 )->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'India: OC flip must be skipped.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'India: AP flip must be skipped.' );
		$this->assertSame( 'no', get_option( self::SHOW_OCS_AP_BANNER_OPTION ), 'India: banner is suppressed.' );
	}

	public function test_flip_skipped_for_frontbook_with_both_disabled() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
			]
		);

		// Recent account = likely 10.7 frontbook who explicitly disabled both.
		$this->build_migration( 'US', 1779148800 )->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'Frontbook-disabled OC must not be re-flipped.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Frontbook-disabled AP must not be re-flipped.' );
	}

	public function test_flip_skipped_only_for_disabled_feature_in_frontbook() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'yes',
				'adaptive_pricing'           => 'no',
			]
		);

		// Recent account = likely 10.7 frontbook who kept OC on but disabled AP.
		$this->build_migration( 'US', 1779148800 )->maybe_run();

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stored['optimized_checkout_element'], 'OC stays as set; no change needed.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Frontbook-disabled AP must not be re-flipped.' );
	}
}
