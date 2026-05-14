<?php
/**
 * Class WC_Stripe_PP_Settings_Migration_Test
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-pp-settings-migration.php';

/**
 * Unit tests for {@see WC_Stripe_PP_Settings_Migration}.
 *
 * Verifies orchestrator behavior end-to-end against seeded PP option state:
 *   - Idempotency (completed flag short-circuits).
 *   - Unknown-version fall-through.
 *   - AUTO and TRANSFORM application with destination-set preservation.
 *   - Per-method enable migration (UPM-gated).
 *   - Per-method order migration.
 *   - Ledger and snapshot side effects.
 */
class WC_Stripe_PP_Settings_Migration_Test extends WP_UnitTestCase {

	/**
	 * PP option keys touched by the orchestrator, for tear_down cleanup.
	 *
	 * @var array<int, string>
	 */
	private const PP_OPTION_KEYS = [
		'stripe_wc_version',
		'woocommerce_stripe_api_settings',
		'woocommerce_stripe_advanced_settings',
		'woocommerce_stripe_cc_settings',
		'woocommerce_stripe_upm_settings',
		'woocommerce_stripe_express_checkout_settings',
		'woocommerce_stripe_amazonpay_settings',
		'woocommerce_stripe_klarna_settings',
		'woocommerce_stripe_sepa_settings',
		'woocommerce_stripe_billie_settings',
		'woocommerce_gateway_order',
	];

	public function tear_down() {
		delete_option( WC_Stripe_PP_Settings_Migration::COMPLETED_OPTION );
		delete_option( WC_Stripe_Pre_Migration_Snapshot::CURRENT_OPTION );
		delete_option( WC_Stripe_Settings_Migration_Ledger::OPTION_NAME );

		foreach ( self::PP_OPTION_KEYS as $key ) {
			delete_option( $key );
		}

		// Reset Woo Stripe main settings to a clean baseline. Test environment may have seeded
		// gateway defaults; we restore the same baseline before each subsequent test.
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	/**
	 * Seeds the PP version option so the version detector resolves to 3.X without needing the
	 * plugin file on disk.
	 *
	 * @return void
	 */
	private function seed_pp_3x(): void {
		update_option( 'stripe_wc_version', '3.3.106' );
	}

	public function test_short_circuits_when_completed_flag_set() {
		update_option( WC_Stripe_PP_Settings_Migration::COMPLETED_OPTION, 'yes' );
		$this->seed_pp_3x();
		update_option(
			'woocommerce_stripe_advanced_settings',
			[ 'debug_log' => 'yes' ]
		);

		WC_Stripe_PP_Settings_Migration::maybe_run();

		// Migration should not have run, so logging should not have been pre-filled into Woo
		// Stripe settings, and no ledger or snapshot should have been written.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayNotHasKey( 'logging', $stripe_settings, 'Completed flag did not short-circuit AUTO row application' );
		$this->assertNull( WC_Stripe_Pre_Migration_Snapshot::get_current() );
		$this->assertSame( [], WC_Stripe_Settings_Migration_Ledger::load() );
	}

	public function test_marks_complete_when_no_pp_data_present() {
		WC_Stripe_PP_Settings_Migration::maybe_run();

		$this->assertSame( 'yes', get_option( WC_Stripe_PP_Settings_Migration::COMPLETED_OPTION ) );
		// No snapshot should be captured when no PP data exists.
		$this->assertNull( WC_Stripe_Pre_Migration_Snapshot::get_current() );
		// No ledger should be written.
		$this->assertSame( [], WC_Stripe_Settings_Migration_Ledger::load() );
	}

	public function test_applies_auto_row_when_source_present_and_dest_empty() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stripe_settings['logging'] ?? null );

		// Ledger row recorded.
		$ledger_rows = WC_Stripe_Settings_Migration_Ledger::find_by_source_key( 'debug_log' );
		$this->assertCount( 1, $ledger_rows );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::OUTCOME_APPLIED, $ledger_rows[0]['outcome'] );
	}

	public function test_skips_auto_row_when_destination_already_set() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );
		// Merchant already had logging disabled in Woo Stripe — must be preserved.
		WC_Stripe_Helper::update_main_stripe_settings( [ 'logging' => 'no' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stripe_settings['logging'], 'Merchant value was overwritten' );

		$ledger_rows = WC_Stripe_Settings_Migration_Ledger::find_by_source_key( 'debug_log' );
		$this->assertCount( 1, $ledger_rows );
		$this->assertSame(
			WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
			$ledger_rows[0]['outcome']
		);
	}

	public function test_skips_auto_row_when_source_missing() {
		$this->seed_pp_3x();
		// `woocommerce_stripe_advanced_settings` exists but does NOT have `debug_log`.
		update_option( 'woocommerce_stripe_advanced_settings', [ 'other_key' => 'value' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$ledger_rows = WC_Stripe_Settings_Migration_Ledger::find_by_source_key( 'debug_log' );
		$this->assertCount( 1, $ledger_rows );
		$this->assertSame(
			WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
			$ledger_rows[0]['outcome']
		);
	}

	public function test_applies_transform_row_for_test_mode() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_api_settings', [ 'mode' => 'test' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stripe_settings['testmode'] ?? null );

		$ledger_rows = WC_Stripe_Settings_Migration_Ledger::find_by_source_key( 'mode' );
		$this->assertCount( 1, $ledger_rows );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::OUTCOME_APPLIED, $ledger_rows[0]['outcome'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::CATEGORY_TRANSFORM, $ledger_rows[0]['category'] );
	}

	public function test_applies_transform_row_for_capture() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_cc_settings', [ 'charge_type' => 'authorize' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'no', $stripe_settings['capture'] ?? null );
	}

	public function test_applies_statement_descriptor_with_placeholder_stripped() {
		$this->seed_pp_3x();
		update_option(
			'woocommerce_stripe_advanced_settings',
			[
				'statement_descriptor'        => 'ACME {order_id} STORE',
				'statement_descriptor_suffix' => '#{order_number}',
			]
		);

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'ACME STORE', $stripe_settings['statement_descriptor'] ?? null );
		$this->assertSame( '#', $stripe_settings['short_statement_descriptor'] ?? null );
	}

	public function test_records_drop_investigate_build_rows_in_ledger() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'installments' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$ledger = WC_Stripe_Settings_Migration_Ledger::load();

		$drop_rows        = array_filter( $ledger, fn( $r ) => WC_Stripe_Settings_Migration_Ledger::CATEGORY_DROP === $r['category'] );
		$investigate_rows = array_filter( $ledger, fn( $r ) => WC_Stripe_Settings_Migration_Ledger::CATEGORY_INVESTIGATE === $r['category'] );
		$build_rows       = array_filter( $ledger, fn( $r ) => WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD === $r['category'] );

		$this->assertNotEmpty( $drop_rows, 'No DROP rows recorded' );
		$this->assertNotEmpty( $investigate_rows, 'No INVESTIGATE rows recorded' );
		$this->assertNotEmpty( $build_rows, 'No BUILD rows recorded' );

		// `installments` is an INVESTIGATE row and we seeded a value — verify the ledger captured it.
		$installments_rows = array_values(
			array_filter(
				$investigate_rows,
				fn( $r ) => 'installments' === $r['source_key']
			)
		);
		$this->assertCount( 1, $installments_rows );
		$this->assertSame( 'yes', $installments_rows[0]['source_value'] );
	}

	public function test_captures_pre_migration_snapshot_before_writes() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );

		// Set a sentinel value in Woo Stripe settings that the migration would normally NOT
		// overwrite (because the destination is non-empty). The snapshot must record this exact
		// sentinel so we can prove the snapshot was captured BEFORE any AUTO/TRANSFORM writes.
		$existing                         = WC_Stripe_Helper::get_stripe_settings();
		$existing['statement_descriptor'] = 'PRE_MIGRATION_SENTINEL';
		WC_Stripe_Helper::update_main_stripe_settings( $existing );

		$pre_capture_value = WC_Stripe_Helper::get_stripe_settings();

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertNotNull( $snapshot );

		// Snapshot's recorded Woo Stripe main settings must exactly equal the pre-migration state.
		// Since `statement_descriptor` is already set, the migration's TRANSFORM row for it is a
		// no-op; the post-state should equal the pre-state for this key.
		$captured_stripe = $snapshot['options']['woocommerce_stripe_settings'] ?? [];
		$this->assertSame(
			'PRE_MIGRATION_SENTINEL',
			$captured_stripe['statement_descriptor'] ?? null,
			'Snapshot did not capture the pre-migration sentinel value'
		);

		// And the full snapshot equals what get_stripe_settings returned right before the call.
		$this->assertSame( $pre_capture_value, $captured_stripe );
	}

	public function test_is_idempotent_across_two_invocations() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();
		$first_ledger_count = count( WC_Stripe_Settings_Migration_Ledger::load() );
		$this->assertGreaterThan( 0, $first_ledger_count );

		WC_Stripe_PP_Settings_Migration::maybe_run();
		$second_ledger_count = count( WC_Stripe_Settings_Migration_Ledger::load() );
		$this->assertSame( $first_ledger_count, $second_ledger_count, 'Second invocation appended ledger rows' );
	}

	public function test_per_method_enable_migration_writes_upe_method_ids() {
		$this->seed_pp_3x();
		// UPM is OFF — per-method enable migration should run.
		update_option( 'woocommerce_stripe_upm_settings', [ 'enabled' => 'no' ] );
		update_option( 'woocommerce_stripe_cc_settings', [ 'enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_klarna_settings', [ 'enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_sepa_settings', [ 'enabled' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$enabled_methods = $stripe_settings[ WC_Stripe_PP_Settings_Migration::DEST_KEY_ENABLED_METHODS ] ?? [];

		$this->assertContains( 'card', $enabled_methods );
		$this->assertContains( 'klarna', $enabled_methods );
		$this->assertContains( 'sepa_debit', $enabled_methods );
	}

	public function test_per_method_enable_migration_skipped_when_upm_enabled() {
		$this->seed_pp_3x();
		// UPM is ON — per-method enable migration should SKIP because UPM merchants manage
		// methods centrally via PMC and per-gateway flags are unreliable.
		update_option( 'woocommerce_stripe_upm_settings', [ 'enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_klarna_settings', [ 'enabled' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$enabled_methods = $stripe_settings[ WC_Stripe_PP_Settings_Migration::DEST_KEY_ENABLED_METHODS ] ?? null;

		// Klarna should NOT have been added to the Woo Stripe enabled list because UPM-gated.
		$this->assertTrue(
			null === $enabled_methods || ! in_array( 'klarna', $enabled_methods, true ),
			'Per-method enable migration ran despite UPM being enabled'
		);
	}

	public function test_per_method_enable_records_pp_only_methods_as_build_rows() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_upm_settings', [ 'enabled' => 'no' ] );
		update_option( 'woocommerce_stripe_billie_settings', [ 'enabled' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$build_rows = array_values(
			array_filter(
				WC_Stripe_Settings_Migration_Ledger::load(),
				fn( $r ) => WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD === $r['category']
					&& 'stripe_billie' === $r['source_key']
			)
		);

		$this->assertCount( 1, $build_rows, 'PP-only method not recorded as BUILD row' );
	}

	public function test_per_method_order_migration_writes_mapped_order() {
		$this->seed_pp_3x();
		// Merchant put Klarna first, then CC, then SEPA in WC core's gateway order.
		update_option(
			'woocommerce_gateway_order',
			[
				'stripe_klarna' => 0,
				'stripe_cc'     => 1,
				'stripe_sepa'   => 2,
				'cheque'        => 3, // non-Stripe gateway — should be dropped.
			]
		);

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$order           = $stripe_settings[ WC_Stripe_PP_Settings_Migration::DEST_KEY_METHOD_ORDER ] ?? null;

		$this->assertSame(
			[ 'klarna', 'card', 'sepa_debit' ],
			$order
		);
	}

	public function test_per_method_order_skipped_when_no_pp_stripe_gateways_in_order() {
		$this->seed_pp_3x();
		update_option(
			'woocommerce_gateway_order',
			[
				'cheque' => 0,
				'bacs'   => 1,
			]
		);

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayNotHasKey( WC_Stripe_PP_Settings_Migration::DEST_KEY_METHOD_ORDER, $stripe_settings );
	}

	public function test_per_method_order_does_not_overwrite_existing_woo_stripe_order() {
		$this->seed_pp_3x();
		update_option(
			'woocommerce_gateway_order',
			[
				'stripe_klarna' => 0,
				'stripe_cc'     => 1,
			]
		);

		// Merchant already configured order on Woo Stripe — must not be overwritten.
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				WC_Stripe_PP_Settings_Migration::DEST_KEY_METHOD_ORDER => [ 'card', 'klarna' ],
			]
		);

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame(
			[ 'card', 'klarna' ],
			$stripe_settings[ WC_Stripe_PP_Settings_Migration::DEST_KEY_METHOD_ORDER ]
		);
	}

	public function test_orchestrator_sets_completed_flag_after_run() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$this->assertSame( 'yes', get_option( WC_Stripe_PP_Settings_Migration::COMPLETED_OPTION ) );
	}

	public function test_falls_back_to_3x_mapping_for_unknown_future_version() {
		// PP 5.X (hypothetical) — no concrete map. Detector returns '5'; orchestrator should
		// fall back to 3.X best-effort.
		update_option( 'stripe_wc_version', '5.0.0' );
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		// 3.X mapping was applied as fallback.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( 'yes', $stripe_settings['logging'] ?? null );
	}

	public function test_pp_4x_map_returns_empty_so_no_writes_happen() {
		// PP 4.X has an empty placeholder map — orchestrator uses it directly (no fallback
		// because the map class exists). No AUTO/TRANSFORM rows means no writes.
		update_option( 'stripe_wc_version', '4.0.0' );
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertArrayNotHasKey( 'logging', $stripe_settings, '4.X placeholder map performed writes' );

		// But completed flag is still set so we don't re-run.
		$this->assertSame( 'yes', get_option( WC_Stripe_PP_Settings_Migration::COMPLETED_OPTION ) );
	}

	public function test_ledger_run_id_groups_all_decisions_from_one_invocation() {
		$this->seed_pp_3x();
		update_option( 'woocommerce_stripe_advanced_settings', [ 'debug_log' => 'yes' ] );
		update_option( 'woocommerce_stripe_api_settings', [ 'mode' => 'live' ] );

		WC_Stripe_PP_Settings_Migration::maybe_run();

		$rows    = WC_Stripe_Settings_Migration_Ledger::load();
		$run_ids = array_unique( array_column( $rows, 'run_id' ) );

		$this->assertCount( 1, $run_ids, 'Multiple run_ids found in a single orchestrator invocation' );
	}
}
