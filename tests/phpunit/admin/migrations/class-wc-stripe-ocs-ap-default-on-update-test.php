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
	 * Build a partial mock so AP-availability, account-created, and account-data
	 * reads don't touch the Stripe API.
	 *
	 * @param string|null $ap_unavailable_reason Reason AP is unavailable, or null when available.
	 * @param int|null    $created               Unix timestamp returned by get_account_created_ts().
	 * @param bool        $has_account_data      Whether get_account_data() returns a non-empty array
	 *                                           (false simulates invalid/absent credentials).
	 *
	 * @return MockObject|WC_Stripe_OCS_AP_Default_On_Update
	 */
	private function build_migration( ?string $ap_unavailable_reason = null, ?int $created = null, bool $has_account_data = true ) {
		$migration = $this->getMockBuilder( WC_Stripe_OCS_AP_Default_On_Update::class )
							->disableOriginalConstructor()
							->onlyMethods(
								[
									'get_ap_unavailable_reason',
									'get_account_created_ts',
									'get_account_data',
								]
							)
							->getMock();

		$migration->method( 'get_ap_unavailable_reason' )
			->willReturn( $ap_unavailable_reason );

		$migration->method( 'get_account_created_ts' )
			->willReturn( $created );

		$migration->method( 'get_account_data' )
			->willReturn( $has_account_data ? [ 'id' => 'acct_test' ] : [] );

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
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'OCS must remain disabled when guard is set.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Adaptive Pricing must remain disabled when guard is set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ), 'OCS and AP banner option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ), 'AP only banner option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ), 'OCS only banner option must not be set.' );
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
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'New install must leave OCS disabled.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'New install must leave Adaptive Pricing disabled.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ), 'OCS and AP banner option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ), 'AP only banner option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ), 'OCS only banner option must not be set.' );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ), 'Migration ran-once flag must be set.' );
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
		$this->assertSame( 'no', $stored['optimized_checkout_element'], 'Already on 10.8 must leave OCS disabled.' );
		$this->assertSame( 'no', $stored['adaptive_pricing'], 'Already on 10.8 must leave Adaptive Pricing disabled.' );
		$this->assertFalse( get_option( self::SHOW_OCS_AP_BANNER_OPTION ), 'OCS and AP banner option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_AP_ONLY_BANNER_OPTION ), 'AP only banner option must not be set.' );
		$this->assertFalse( get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ), 'OCS only banner option must not be set.' );
		$this->assertSame( 'yes', get_option( self::MIGRATION_FLAG_OPTION ), 'Migration ran-once flag must be set.' );
	}

	/**
	 * Migration scenario matrix. Each row drives one execution of maybe_migrate()
	 * and asserts all three banner-visibility options and post-migration gateway state.
	 *
	 * @dataProvider audience_matrix_provider
	 */
	public function test_migration_scenarios( array $scenario ) {
		update_option( self::STRIPE_VERSION_OPTION, $scenario['previous_version'] );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => $scenario['oc_pre'],
				'adaptive_pricing'           => $scenario['ap_pre'],
				'pmc_enabled'                => $scenario['pmc_enabled'],
			]
		);

		$this->build_migration(
			$scenario['ap_unavailable_reason'],
			$scenario['account_created'],
			$scenario['has_account_data']
		)->maybe_migrate( $scenario['previous_version'] );

		$created_label = null === $scenario['account_created'] ? 'null' : (string) $scenario['account_created'];
		$context       = sprintf(
			'prev=%s oc=%s ap=%s ap_unavail=%s created=%s pmc=%s has_account=%s',
			$scenario['previous_version'],
			$scenario['oc_pre'],
			$scenario['ap_pre'],
			$scenario['ap_unavailable_reason'] ?? 'available',
			$created_label,
			$scenario['pmc_enabled'],
			$scenario['has_account_data'] ? 'yes' : 'no'
		);

		$this->assertSame(
			$scenario['expected_show_ocs_ap'],
			get_option( self::SHOW_OCS_AP_BANNER_OPTION ),
			sprintf( 'OCS+AP banner flag mismatch for %s', $context )
		);
		$this->assertSame(
			$scenario['expected_show_ap_only'],
			get_option( self::SHOW_AP_ONLY_BANNER_OPTION ),
			sprintf( 'AP-only banner flag mismatch for %s', $context )
		);
		$this->assertSame(
			$scenario['expected_show_ocs_only'],
			get_option( self::SHOW_OCS_ONLY_BANNER_OPTION ),
			sprintf( 'OCS-only banner flag mismatch for %s', $context )
		);

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			$scenario['expected_oc_after'],
			$stored['optimized_checkout_element'] ?? 'no',
			sprintf( 'optimized_checkout_element mismatch after migration for %s', $context )
		);
		$this->assertSame(
			$scenario['expected_ap_after'],
			$stored['adaptive_pricing'] ?? 'no',
			sprintf( 'adaptive_pricing mismatch after migration for %s', $context )
		);
	}

	public function audience_matrix_provider(): array {
		$old_ts = 1747008000;   // 2025-05-12 — pre-10.7 era.
		$new_ts = 1779148800;   // 2026-05-19 — post-10.7 era.

		$scenarios = [
			// Platform-connected merchants (pmc_enabled='yes') - OCS can function.
			'both-on backbook'                           => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'yes',
				'ap_unavailable_reason'  => null,
				'account_created'        => $new_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'yes',
			],
			'OC-only backbook old-account'               => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => $old_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'yes',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'yes',
			],
			'OC-only frontbook-10.7 disabled-AP'         => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => $new_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'no',
			],
			'both-off backbook old-account'              => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => $old_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'yes',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'yes',
			],
			'both-off frontbook-10.7 disabled-both'      => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => $new_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'no',
				'expected_ap_after'      => 'no',
			],
			'previous 10.6 both-off recent-account'      => [
				'previous_version'       => '10.6.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => $new_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'yes',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'yes',
			],
			'previous 10.6 OC-only recent-account'       => [
				'previous_version'       => '10.6.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => $new_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'yes',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'yes',
			],
			'India backbook both-off'                    => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => 'account-country',
				'account_created'        => $old_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'yes',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'no',
			],
			'India backbook OC-only'                     => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => 'account-country',
				'account_created'        => $old_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'no',
			],
			'India frontbook disabled-OC'                => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => 'account-country',
				'account_created'        => $new_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'no',
				'expected_ap_after'      => 'no',
			],
			'currency-unavailable both-off'              => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => 'store-currency-not-settlement-currency',
				'account_created'        => $old_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'yes',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'no',
			],
			'currency-unavailable OC-only'               => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => 'no-settlement-currencies',
				'account_created'        => $old_ts,
				'pmc_enabled'            => 'yes',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'no',
			],
			// Connected, non-PMC account (direct API keys, pmc_enabled='no') — neither OCS nor AP
			// can function, so neither is enabled; `created` is absent for these Standard accounts.
			'non-PMC account both-off (nothing enabled)' => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => null,
				'pmc_enabled'            => 'no',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'no',
				'expected_ap_after'      => 'no',
			],
			'non-PMC account OC-already-on (unchanged)'  => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'yes',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => null,
				'pmc_enabled'            => 'no',
				'has_account_data'       => true,
				'expected_show_ocs_ap'   => 'no',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'no',
			],
			// No account data (invalid/absent credentials) — uninformative, so stay optimistic and enable.
			'unreadable account both-off (OC enabled)'   => [
				'previous_version'       => '10.7.0',
				'oc_pre'                 => 'no',
				'ap_pre'                 => 'no',
				'ap_unavailable_reason'  => null,
				'account_created'        => null,
				'pmc_enabled'            => 'no',
				'has_account_data'       => false,
				'expected_show_ocs_ap'   => 'yes',
				'expected_show_ap_only'  => 'no',
				'expected_show_ocs_only' => 'no',
				'expected_oc_after'      => 'yes',
				'expected_ap_after'      => 'yes',
			],
		];

		return array_map(
			static function ( $scenario ) {
				return [ $scenario ];
			},
			$scenarios
		);
	}

	public function test_migration_preserves_unrelated_settings_keys() {
		update_option( self::STRIPE_VERSION_OPTION, '10.7.0' );
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'optimized_checkout_element' => 'no',
				'adaptive_pricing'           => 'no',
				'pmc_enabled'                => 'yes',
				'statement_descriptor'       => 'My Store',
			]
		);

		$this->build_migration( null, 1747008000 )->maybe_migrate( '10.7.0' );

		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stored['optimized_checkout_element'], 'OCS must be enabled.' );
		$this->assertSame( 'yes', $stored['adaptive_pricing'], 'Adaptive Pricing must be enabled.' );
		$this->assertSame( 'My Store', $stored['statement_descriptor'], 'Unrelated stored keys must survive.' );
	}
}
