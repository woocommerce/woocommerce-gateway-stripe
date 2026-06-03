<?php
/**
 * Class WC_Stripe_Pre_Migration_Snapshot_Test
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-pre-migration-snapshot.php';

/**
 * Unit tests for {@see WC_Stripe_Pre_Migration_Snapshot}.
 */
class WC_Stripe_Pre_Migration_Snapshot_Test extends WP_UnitTestCase {

	/**
	 * Option keys touched by these tests so tear_down can purge them. Kept in sync with what each
	 * test seeds — easier to maintain than a wildcard delete.
	 *
	 * @var array<int, string>
	 */
	private array $option_keys_to_cleanup = [];

	public function tear_down() {
		delete_option( WC_Stripe_Pre_Migration_Snapshot::CURRENT_OPTION );

		foreach ( $this->option_keys_to_cleanup as $key ) {
			delete_option( $key );
		}
		$this->option_keys_to_cleanup = [];

		parent::tear_down();
	}

	/**
	 * Tracks an option for tear_down cleanup. Returns the value for assignment chaining.
	 */
	private function seed_option( string $key, $value ) {
		update_option( $key, $value );
		$this->option_keys_to_cleanup[] = $key;
		return $value;
	}

	public function test_capture_returns_iso_like_timestamp() {
		$captured_at = WC_Stripe_Pre_Migration_Snapshot::capture();

		$this->assertNotEmpty( $captured_at );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $captured_at );
	}

	public function test_capture_writes_snapshot_to_canonical_option() {
		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertIsArray( $snapshot );
		$this->assertSame( WC_Stripe_Pre_Migration_Snapshot::SNAPSHOT_VERSION, $snapshot['snapshot_version'] );
		$this->assertArrayHasKey( 'captured_at', $snapshot );
		$this->assertArrayHasKey( 'pp_version', $snapshot );
		$this->assertArrayHasKey( 'pp_major_detected', $snapshot );
		$this->assertArrayHasKey( 'wc_stripe_version', $snapshot );
		$this->assertArrayHasKey( 'wp_version', $snapshot );
		$this->assertArrayHasKey( 'options', $snapshot );
		$this->assertIsArray( $snapshot['options'] );
	}

	public function test_capture_persists_with_autoload_disabled() {
		WC_Stripe_Pre_Migration_Snapshot::capture();

		global $wpdb;
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				WC_Stripe_Pre_Migration_Snapshot::CURRENT_OPTION
			)
		);

		// WordPress historically stored autoload=false as the string 'no'; newer versions use 'off'.
		// Both are valid; accept either to keep this test cross-version.
		$this->assertContains( $autoload, [ 'no', 'off' ] );
	}

	public function test_get_current_returns_null_when_no_snapshot_captured() {
		$this->assertNull( WC_Stripe_Pre_Migration_Snapshot::get_current() );
	}

	public function test_captures_known_pp_option_blob() {
		$this->seed_option(
			'woocommerce_stripe_api_settings',
			[
				'mode'                 => 'live',
				'publishable_key_live' => 'pk_live_xxx',
				'secret_key_live'      => 'sk_live_xxx',
			]
		);

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayHasKey( 'woocommerce_stripe_api_settings', $snapshot['options'] );
		$this->assertSame( 'live', $snapshot['options']['woocommerce_stripe_api_settings']['mode'] );
	}

	public function test_captures_woo_stripe_main_settings() {
		// `woocommerce_stripe_settings` is a flat key-value blob. Other test infrastructure may
		// have populated it with WC Stripe defaults (express checkout button options, etc.), so
		// we assert the keys we seeded are present and correct without requiring the snapshot
		// to contain only those keys.
		$existing = WC_Stripe_Helper::get_stripe_settings();
		$merged   = array_merge(
			is_array( $existing ) ? $existing : [],
			[
				'enabled'  => 'yes',
				'testmode' => 'yes',
			]
		);
		WC_Stripe_Helper::update_main_stripe_settings( $merged );

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayHasKey( 'woocommerce_stripe_settings', $snapshot['options'] );
		$this->assertSame( 'yes', $snapshot['options']['woocommerce_stripe_settings']['enabled'] );
		$this->assertSame( 'yes', $snapshot['options']['woocommerce_stripe_settings']['testmode'] );
	}

	public function test_captures_woocommerce_gateway_stripe_retention_via_dedicated_pattern() {
		// `woocommerce_gateway_stripe_*` does NOT match `woocommerce_stripe_*` because the
		// `_gateway_` infix breaks the prefix. This option is captured by its own LIKE pattern.
		$this->seed_option(
			'woocommerce_gateway_stripe_retention',
			[
				'number' => 6,
				'unit'   => 'months',
			]
		);

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayHasKey( 'woocommerce_gateway_stripe_retention', $snapshot['options'] );
	}

	public function test_captures_oauth_state_keys_via_catch_all() {
		$this->seed_option( 'wc_stripe_oauth_updated_at', 1715000000 );
		$this->seed_option( 'wc_stripe_test_oauth_updated_at', 1715000001 );

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayHasKey( 'wc_stripe_oauth_updated_at', $snapshot['options'] );
		$this->assertArrayHasKey( 'wc_stripe_test_oauth_updated_at', $snapshot['options'] );
	}

	public function test_catch_all_picks_up_unknown_keys_in_stripe_namespaces() {
		// Hypothetical future key not in the known list — must be caught by the catch-all sweep.
		$this->seed_option( 'wc_stripe_some_future_feature_flag', 'yes' );
		$this->seed_option( 'stripe_wc_future_pp_state', 'something' );

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayHasKey( 'wc_stripe_some_future_feature_flag', $snapshot['options'] );
		$this->assertArrayHasKey( 'stripe_wc_future_pp_state', $snapshot['options'] );
	}

	public function test_does_not_capture_wcstripe_cache_keys() {
		// `wcstripe_cache_*` (no underscore between `wc` and `stripe`) is intentionally NOT
		// matched by any LIKE pattern — regenerable cache, not configuration state.
		$this->seed_option( 'wcstripe_cache_some_data', 'cached value' );

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayNotHasKey( 'wcstripe_cache_some_data', $snapshot['options'] );
	}

	public function test_does_not_capture_own_canonical_option_in_catch_all() {
		// Seed the canonical option to simulate a prior snapshot. Re-running capture must not
		// embed the previous snapshot's contents inside the new snapshot.
		update_option(
			WC_Stripe_Pre_Migration_Snapshot::CURRENT_OPTION,
			[
				'snapshot_version' => 1,
				'captured_at'      => '2020-01-01 00:00:00',
			],
			false
		);

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayNotHasKey( WC_Stripe_Pre_Migration_Snapshot::CURRENT_OPTION, $snapshot['options'] );
	}

	public function test_does_not_capture_completed_flag_in_catch_all() {
		$this->seed_option( WC_Stripe_Pre_Migration_Snapshot::COMPLETED_OPTION_NAME, 'yes' );

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayNotHasKey(
			WC_Stripe_Pre_Migration_Snapshot::COMPLETED_OPTION_NAME,
			$snapshot['options']
		);
	}

	public function test_distinguishes_missing_from_empty_string_via_sentinel() {
		// Seed an explicitly empty string value — must appear in the snapshot. A missing key
		// must NOT appear (covered by the absence of the next option below).
		$this->seed_option( 'woocommerce_stripe_klarna_settings', '' );
		// Not seeded: 'woocommerce_stripe_affirm_settings' — must be absent.

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertArrayHasKey( 'woocommerce_stripe_klarna_settings', $snapshot['options'] );
		$this->assertSame( '', $snapshot['options']['woocommerce_stripe_klarna_settings'] );
		$this->assertArrayNotHasKey( 'woocommerce_stripe_affirm_settings', $snapshot['options'] );
	}

	public function test_recapture_overwrites_in_place_without_creating_extra_option_rows() {
		WC_Stripe_Pre_Migration_Snapshot::capture();

		// A second capture must reflect newly-seeded state...
		$this->seed_option( 'woocommerce_stripe_api_settings', [ 'mode' => 'test' ] );
		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertSame( 'test', $snapshot['options']['woocommerce_stripe_api_settings']['mode'] );

		// ...and must NOT spawn archive rows. Exactly one snapshot option should exist.
		global $wpdb;
		$snapshot_option_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( WC_Stripe_Pre_Migration_Snapshot::CURRENT_OPTION ) . '%'
			)
		);
		$this->assertSame( 1, $snapshot_option_count, 'Re-capture must overwrite the single snapshot option, not archive copies.' );
	}

	public function test_blob_records_pp_version_from_detector() {
		$this->seed_option(
			WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION,
			'3.3.106'
		);

		WC_Stripe_Pre_Migration_Snapshot::capture();

		$snapshot = WC_Stripe_Pre_Migration_Snapshot::get_current();
		$this->assertSame( '3.3.106', $snapshot['pp_version'] );
		$this->assertSame( '3', $snapshot['pp_major_detected'] );
	}
}
