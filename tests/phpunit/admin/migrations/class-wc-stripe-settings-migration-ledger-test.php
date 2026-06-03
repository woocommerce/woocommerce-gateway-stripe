<?php
/**
 * Class WC_Stripe_Settings_Migration_Ledger_Test
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-settings-migration-ledger.php';

/**
 * Unit tests for {@see WC_Stripe_Settings_Migration_Ledger}.
 */
class WC_Stripe_Settings_Migration_Ledger_Test extends WP_UnitTestCase {

	private string $run_id;
	private string $snapshot_id;
	private WC_Stripe_Settings_Migration_Ledger $ledger;

	/**
	 * Create the ledger table once, before the per-test transactions begin.
	 * CREATE TABLE implies a commit, so it must not run inside a test's transaction.
	 *
	 * @param WP_UnitTest_Factory $factory Shared fixture factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		WC_Stripe_Settings_Migration_Ledger::install_table();
	}

	public function set_up() {
		parent::set_up();

		// Start every test from a clean table. Tests that exercise install_table()'s
		// dbDelta path can implicitly commit rows past the per-test transaction
		// rollback; deleting here (inside this test's own transaction) guarantees
		// isolation regardless of what a prior test left behind.
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . WC_Stripe_Settings_Migration_Ledger::TABLE_NAME ); // phpcs:ignore WordPress.DB

		$this->run_id      = wp_generate_uuid4();
		$this->snapshot_id = '2026-05-14 12:00:00';
		$this->ledger      = new WC_Stripe_Settings_Migration_Ledger(
			$this->run_id,
			$this->snapshot_id,
			'3'
		);
	}

	public function tear_down() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . WC_Stripe_Settings_Migration_Ledger::TABLE_NAME ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	public function test_record_apply_creates_one_row_with_correct_fields() {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_advanced_settings',
			'debug_log',
			'yes',
			'woocommerce_stripe_settings',
			'logging',
			'',
			'yes'
		);

		$rows = WC_Stripe_Settings_Migration_Ledger::load();
		$this->assertCount( 1, $rows );

		$row = $rows[0];
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::TYPE_APPLY, $row['type'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO, $row['category'] );
		$this->assertSame( 'woocommerce_stripe_advanced_settings', $row['source_option'] );
		$this->assertSame( 'debug_log', $row['source_key'] );
		$this->assertSame( 'yes', $row['source_value'] );
		$this->assertSame( 'woocommerce_stripe_settings', $row['dest_option'] );
		$this->assertSame( 'logging', $row['dest_key'] );
		$this->assertSame( '', $row['dest_value_before'] );
		$this->assertSame( 'yes', $row['dest_value_after'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::OUTCOME_APPLIED, $row['outcome'] );
		$this->assertNull( $row['reason'] );
		$this->assertSame( $this->run_id, $row['run_id'] );
		$this->assertSame( $this->snapshot_id, $row['snapshot_id'] );
		$this->assertSame( '3', $row['pp_version_detected'] );
		$this->assertArrayHasKey( 'id', $row );
		// entry_uuid is the stable external row reference (CHAR(36) uuid v4), distinct
		// from the auto-increment `id`.
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$row['entry_uuid']
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$row['timestamp']
		);
	}

	public function test_record_skip_dest_set_captures_pre_existing_dest_value() {
		$this->ledger->record_skip(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_cc_settings',
			'enabled',
			'yes',
			'woocommerce_stripe_settings',
			'enabled',
			'no', // merchant had it disabled — must be preserved.
			WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
			'Destination already had merchant value — preserved'
		);

		$row = WC_Stripe_Settings_Migration_Ledger::load()[0];
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::TYPE_SKIP, $row['type'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET, $row['outcome'] );
		$this->assertSame( 'no', $row['dest_value_before'] );
		$this->assertNull( $row['dest_value_after'] );
		$this->assertSame( 'Destination already had merchant value — preserved', $row['reason'] );
	}

	public function test_record_skip_source_missing_uses_null_source_value() {
		$this->ledger->record_skip(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_advanced_settings',
			'never_existed',
			null,
			'woocommerce_stripe_settings',
			'irrelevant',
			'',
			WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
			'PP source option/key not present'
		);

		$row = WC_Stripe_Settings_Migration_Ledger::load()[0];
		$this->assertNull( $row['source_value'] );
		$this->assertSame(
			WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
			$row['outcome']
		);
	}

	public function test_record_drop_for_drop_investigate_build_categories() {
		$this->ledger->record_drop(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_DROP,
			'force_3d_secure',
			'yes',
			'No Woo Stripe equivalent — Stripe Radar handles SCA'
		);
		$this->ledger->record_drop(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_INVESTIGATE,
			'installments',
			null,
			'STRIPE-1072'
		);
		$this->ledger->record_drop(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD,
			'button_radius',
			null,
			'No destination feature in Woo Stripe'
		);

		$rows = WC_Stripe_Settings_Migration_Ledger::load();
		$this->assertCount( 3, $rows );

		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::TYPE_DROP, $rows[0]['type'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::CATEGORY_DROP, $rows[0]['category'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::CATEGORY_INVESTIGATE, $rows[1]['category'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD, $rows[2]['category'] );

		// DROP rows have no destination.
		foreach ( $rows as $row ) {
			$this->assertNull( $row['dest_option'] );
			$this->assertNull( $row['dest_key'] );
		}
	}

	public function test_record_revert_references_snapshot_id_in_reason() {
		$snapshot_to_restore_from = '2026-05-14 11:00:00';

		$this->ledger->record_revert(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_settings',
			[ 'enabled' => 'no' ],   // snapshot value
			[ 'enabled' => 'yes' ],  // current (post-migration) value
			$snapshot_to_restore_from
		);

		$row = WC_Stripe_Settings_Migration_Ledger::load()[0];
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::TYPE_REVERT, $row['type'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::OUTCOME_REVERTED, $row['outcome'] );
		$this->assertStringContainsString( $snapshot_to_restore_from, $row['reason'] );
		$this->assertSame( [ 'enabled' => 'no' ], $row['dest_value_after'] );
		$this->assertSame( [ 'enabled' => 'yes' ], $row['dest_value_before'] );
	}

	/**
	 * @dataProvider secret_key_provider
	 */
	public function test_redacts_values_for_secret_keys( string $key ) {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_api_settings',
			$key,
			'sk_live_super_secret_value',
			'woocommerce_stripe_settings',
			$key,
			'',
			'sk_live_super_secret_value'
		);

		$row = WC_Stripe_Settings_Migration_Ledger::load()[0];
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::REDACTED, $row['source_value'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::REDACTED, $row['dest_value_after'] );
		// The row itself is still recorded — only the values are redacted.
		$this->assertSame( $key, $row['source_key'] );
		$this->assertSame( WC_Stripe_Settings_Migration_Ledger::OUTCOME_APPLIED, $row['outcome'] );
	}

	public function secret_key_provider(): array {
		return [
			'contains secret_key'     => [ 'secret_key_live' ],
			'contains webhook_secret' => [ 'webhook_secret' ],
			'contains refresh_token'  => [ 'refresh_token' ],
			'contains access_token'   => [ 'access_token' ],
			'ends with _secret'       => [ 'some_thing_secret' ],
			'ends with _token'        => [ 'some_thing_token' ],
		];
	}

	public function test_does_not_redact_non_secret_keys() {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_advanced_settings',
			'debug_log',
			'yes',
			'woocommerce_stripe_settings',
			'logging',
			'',
			'yes'
		);

		$row = WC_Stripe_Settings_Migration_Ledger::load()[0];
		$this->assertSame( 'yes', $row['source_value'] );
	}

	public function test_all_rows_in_one_run_share_run_id() {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_cc_settings',
			'enabled',
			'yes',
			'woocommerce_stripe_settings',
			'enabled',
			'',
			'yes'
		);
		$this->ledger->record_skip(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_cc_settings',
			'title_text',
			'PP title',
			'woocommerce_stripe_settings',
			'title',
			'merchant title',
			WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
			'dest preserved'
		);

		foreach ( WC_Stripe_Settings_Migration_Ledger::load() as $row ) {
			$this->assertSame( $this->run_id, $row['run_id'] );
		}
	}

	public function test_find_by_source_key_returns_matching_rows_only() {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_advanced_settings',
			'debug_log',
			'yes',
			'woocommerce_stripe_settings',
			'logging',
			'',
			'yes'
		);
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_cc_settings',
			'enabled',
			'yes',
			'woocommerce_stripe_settings',
			'enabled',
			'',
			'yes'
		);

		$matches = WC_Stripe_Settings_Migration_Ledger::find_by_source_key( 'debug_log' );
		$this->assertCount( 1, $matches );
		$this->assertSame( 'debug_log', $matches[0]['source_key'] );
	}

	public function test_find_by_run_id_returns_only_rows_with_that_run_id() {
		$other_run_id = wp_generate_uuid4();
		$other_ledger = new WC_Stripe_Settings_Migration_Ledger( $other_run_id, $this->snapshot_id, '3' );

		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'a',
			'a',
			'a',
			'b',
			'b',
			'',
			'a'
		);
		$other_ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'c',
			'c',
			'c',
			'd',
			'd',
			'',
			'c'
		);

		$mine  = WC_Stripe_Settings_Migration_Ledger::find_by_run_id( $this->run_id );
		$other = WC_Stripe_Settings_Migration_Ledger::find_by_run_id( $other_run_id );

		$this->assertCount( 1, $mine );
		$this->assertCount( 1, $other );
		$this->assertNotSame( $mine[0]['id'], $other[0]['id'] );
	}

	public function test_ledger_is_append_only_across_multiple_writes() {
		// First run.
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'a',
			'a',
			'a',
			'b',
			'b',
			'',
			'a'
		);
		$this->assertCount( 1, WC_Stripe_Settings_Migration_Ledger::load() );

		// Second run, second ledger instance — must append, not overwrite.
		$next = new WC_Stripe_Settings_Migration_Ledger( wp_generate_uuid4(), $this->snapshot_id, '3' );
		$next->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'c',
			'c',
			'c',
			'd',
			'd',
			'',
			'c'
		);

		$this->assertCount( 2, WC_Stripe_Settings_Migration_Ledger::load() );
	}

	public function test_ledger_data_lives_in_custom_table_not_options() {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'a',
			'a',
			'a',
			'b',
			'b',
			'',
			'a'
		);

		global $wpdb;
		$count = (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . $wpdb->prefix . WC_Stripe_Settings_Migration_Ledger::TABLE_NAME // phpcs:ignore WordPress.DB
		);
		$this->assertSame( 1, $count, 'Row must be written to the custom ledger table.' );

		// The ledger must not fall back to a wp_options blob.
		$this->assertFalse(
			get_option( WC_Stripe_Settings_Migration_Ledger::TABLE_NAME, false ),
			'Ledger must not create a wp_options row.'
		);
	}

	public function test_each_row_has_unique_id() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->ledger->record_apply(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
				'a',
				'a' . $i,
				'a',
				'b',
				'b' . $i,
				'',
				'a'
			);
		}

		$rows = WC_Stripe_Settings_Migration_Ledger::load();

		$ids = array_map(
			static function ( $row ) {
				return $row['id'];
			},
			$rows
		);
		$this->assertSame( $ids, array_unique( $ids ), 'All ledger row ids must be unique.' );

		$uuids = array_map(
			static function ( $row ) {
				return $row['entry_uuid'];
			},
			$rows
		);
		$this->assertCount( 5, $uuids );
		$this->assertSame( $uuids, array_unique( $uuids ), 'All ledger entry_uuids must be unique.' );
	}

	public function test_load_returns_empty_array_when_no_rows() {
		$this->assertSame( [], WC_Stripe_Settings_Migration_Ledger::load() );
	}

	public function test_install_table_rerun_runs_dbdelta_and_preserves_rows() {
		$this->ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'a',
			'a',
			'a',
			'b',
			'b',
			'',
			'a'
		);

		// Clear the schema-version gate so install_table() actually runs dbDelta()
		// against the live table (not just the gated early-return no-op). dbDelta on
		// an unchanged schema must be safe and leave existing rows intact.
		delete_option( WC_Stripe_Settings_Migration_Ledger::DB_VERSION_OPTION );
		WC_Stripe_Settings_Migration_Ledger::install_table();

		$rows = WC_Stripe_Settings_Migration_Ledger::load();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'a', $rows[0]['source_key'] );
	}

	public function test_array_and_scalar_values_round_trip_through_storage() {
		$this->ledger->record_revert(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_stripe_settings',
			[
				'capture' => 'manual',
				'limit'   => 5,
			], // array value
			'automatic',                             // scalar value
			'2026-05-14 11:00:00'
		);

		$row = WC_Stripe_Settings_Migration_Ledger::load()[0];
		$this->assertSame(
			[
				'capture' => 'manual',
				'limit'   => 5,
			],
			$row['dest_value_after']
		);
		$this->assertSame( 'automatic', $row['dest_value_before'] );
	}
}
