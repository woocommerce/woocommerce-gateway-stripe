<?php
/**
 * Append-only structured log of every per-row decision made by the PP→Woo
 * Stripe settings migration. Cross-references the pre-migration snapshot via
 * `snapshot_id` so support and audit workflows can reconstruct exactly what
 * the migration did and why.
 *
 * Storage:
 *   - Custom DB table `{$wpdb->prefix}wc_stripe_settings_migration_ledger`,
 *     one row per decision (append-only, single INSERT, indexed on run_id and
 *     source_key). Created/upgraded via dbDelta() in install_table().
 *   - Mirrored to WC log file under handle
 *     `woocommerce-gateway-stripe-pp-settings-migration` for human tailing.
 *
 * Secret-keyed values (anything matching `secret_key`, `webhook_secret`,
 * `refresh_token`, `access_token`, or ending in `_secret`/`_token`) are
 * redacted to the literal string `__REDACTED__` in both the ledger and the
 * mirrored log line. The row itself is still recorded so the audit trail
 * proves the migration processed the field.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-row ledger for the PP settings migration.
 *
 * @since 10.8.0
 */
class WC_Stripe_Settings_Migration_Ledger {

	/**
	 * Unprefixed ledger table name. Prefixed with $wpdb->prefix at query time.
	 *
	 * @var string
	 */
	public const TABLE_NAME = 'wc_stripe_settings_migration_ledger';

	/**
	 * WC log handle. Maps to `wp-content/uploads/wc-logs/woocommerce-gateway-stripe-pp-settings-migration-{date}.log`.
	 *
	 * @var string
	 */
	public const LOG_HANDLE = 'woocommerce-gateway-stripe-pp-settings-migration';

	/**
	 * Outcome enum values. Used in the `outcome` ledger field.
	 *
	 * @var string
	 */
	public const OUTCOME_APPLIED                  = 'applied';
	public const OUTCOME_SKIPPED_DEST_SET         = 'skipped_dest_set';
	public const OUTCOME_SKIPPED_SOURCE_MISSING   = 'skipped_source_missing';
	public const OUTCOME_SKIPPED_TRANSFORM_FAILED = 'skipped_transform_failed';
	public const OUTCOME_DROPPED                  = 'dropped';
	public const OUTCOME_ERRORED                  = 'errored';
	public const OUTCOME_REVERTED                 = 'reverted';

	/**
	 * Event type enum values. Used in the `type` ledger field.
	 *
	 * @var string
	 */
	public const TYPE_APPLY  = 'apply';
	public const TYPE_SKIP   = 'skip';
	public const TYPE_DROP   = 'drop';
	public const TYPE_ERROR  = 'error';
	public const TYPE_REVERT = 'revert';

	/**
	 * Category enum values. Mirrors the settings-map Strategy column.
	 *
	 * @var string
	 */
	public const CATEGORY_AUTO        = 'AUTO';
	public const CATEGORY_TRANSFORM   = 'TRANSFORM';
	public const CATEGORY_DROP        = 'DROP';
	public const CATEGORY_INVESTIGATE = 'INVESTIGATE';
	public const CATEGORY_BUILD       = 'BUILD';

	/**
	 * Substrings that trigger redaction when found anywhere in a key name.
	 *
	 * @var array<int, string>
	 */
	public const SECRET_PATTERNS = [ 'secret_key', 'webhook_secret', 'refresh_token', 'access_token' ];

	/**
	 * Suffixes that trigger redaction when the key name ends with them.
	 *
	 * @var array<int, string>
	 */
	public const SECRET_SUFFIXES = [ '_secret', '_token' ];

	/**
	 * Sentinel string used in place of secret values.
	 *
	 * @var string
	 */
	public const REDACTED = '__REDACTED__';

	/**
	 * UUID v4 shared across one orchestrator run. Every row appended through this instance
	 * carries this run_id, enabling `find_by_run_id()` to reconstruct an entire migration pass.
	 *
	 * @var string
	 */
	private string $run_id;

	/**
	 * Snapshot timestamp this run is paired with (the value returned by
	 * `WC_Stripe_Pre_Migration_Snapshot::capture()`). Null if no snapshot was captured.
	 *
	 * @var string|null
	 */
	private ?string $snapshot_id;

	/**
	 * Major PP version detected at the start of the run.
	 *
	 * @var string
	 */
	private string $pp_version_detected;

	/**
	 * Constructor.
	 *
	 * @param string      $run_id              UUID v4 for this run.
	 * @param string|null $snapshot_id         Snapshot timestamp this run is paired with.
	 * @param string      $pp_version_detected Major version returned by the detector.
	 */
	public function __construct( string $run_id, ?string $snapshot_id, string $pp_version_detected ) {
		$this->run_id              = $run_id;
		$this->snapshot_id         = $snapshot_id;
		$this->pp_version_detected = $pp_version_detected;
	}

	/**
	 * Records a successful AUTO or TRANSFORM apply.
	 *
	 * @param string $category          One of CATEGORY_AUTO|CATEGORY_TRANSFORM.
	 * @param string $source_option     PP wp_options key the source value lives in.
	 * @param string $source_key        PP nested key.
	 * @param mixed  $source_value      Value read from PP.
	 * @param string $dest_option       Woo Stripe wp_options key written to.
	 * @param string $dest_key          Woo Stripe nested key written to.
	 * @param mixed  $dest_value_before Pre-write destination value.
	 * @param mixed  $dest_value_after  Post-write destination value (what we actually wrote).
	 * @return void
	 */
	public function record_apply(
		string $category,
		string $source_option,
		string $source_key,
		$source_value,
		string $dest_option,
		string $dest_key,
		$dest_value_before,
		$dest_value_after
	): void {
		$this->append(
			[
				'type'              => self::TYPE_APPLY,
				'category'          => $category,
				'source_option'     => $source_option,
				'source_key'        => $source_key,
				'source_value'      => $this->redact( $source_key, $source_value ),
				'dest_option'       => $dest_option,
				'dest_key'          => $dest_key,
				'dest_value_before' => $this->redact( $dest_key, $dest_value_before ),
				'dest_value_after'  => $this->redact( $dest_key, $dest_value_after ),
				'outcome'           => self::OUTCOME_APPLIED,
				'reason'            => null,
			]
		);
	}

	/**
	 * Records a skip: destination already set, source missing, or transformer threw.
	 *
	 * @param string $category          AUTO|TRANSFORM.
	 * @param string $source_option     PP wp_options key.
	 * @param string $source_key        PP nested key.
	 * @param mixed  $source_value      PP value when known; null when source was missing.
	 * @param string $dest_option       Woo Stripe wp_options key.
	 * @param string $dest_key          Woo Stripe nested key.
	 * @param mixed  $dest_value_before Pre-write destination value (preserved on skip).
	 * @param string $outcome           One of the OUTCOME_SKIPPED_* constants.
	 * @param string $reason            Free-text reason.
	 * @return void
	 */
	public function record_skip(
		string $category,
		string $source_option,
		string $source_key,
		$source_value,
		string $dest_option,
		string $dest_key,
		$dest_value_before,
		string $outcome,
		string $reason
	): void {
		$this->append(
			[
				'type'              => self::TYPE_SKIP,
				'category'          => $category,
				'source_option'     => $source_option,
				'source_key'        => $source_key,
				'source_value'      => $this->redact( $source_key, $source_value ),
				'dest_option'       => $dest_option,
				'dest_key'          => $dest_key,
				'dest_value_before' => $this->redact( $dest_key, $dest_value_before ),
				'dest_value_after'  => null,
				'outcome'           => $outcome,
				'reason'            => $reason,
			]
		);
	}

	/**
	 * Records a DROP / INVESTIGATE / BUILD decision. Source value is captured for audit when present.
	 *
	 * @param string      $category     One of CATEGORY_DROP|CATEGORY_INVESTIGATE|CATEGORY_BUILD.
	 * @param string      $source_key   PP key being recorded as a no-op decision.
	 * @param mixed       $source_value Current value in PP options when known; null otherwise.
	 * @param string      $reason       Free-text explanation.
	 * @return void
	 */
	public function record_drop( string $category, string $source_key, $source_value, string $reason ): void {
		$this->append(
			[
				'type'              => self::TYPE_DROP,
				'category'          => $category,
				'source_option'     => null,
				'source_key'        => $source_key,
				'source_value'      => $this->redact( $source_key, $source_value ),
				'dest_option'       => null,
				'dest_key'          => null,
				'dest_value_before' => null,
				'dest_value_after'  => null,
				'outcome'           => self::OUTCOME_DROPPED,
				'reason'            => $reason,
			]
		);
	}

	/**
	 * Records an error during an apply (e.g., update_option() threw).
	 *
	 * @param string $category      AUTO|TRANSFORM.
	 * @param string $source_option PP wp_options key.
	 * @param string $source_key    PP nested key.
	 * @param mixed  $source_value  PP value.
	 * @param string $dest_option   Woo Stripe wp_options key.
	 * @param string $dest_key      Woo Stripe nested key.
	 * @param string $reason        Free-text error description (typically exception message).
	 * @return void
	 */
	public function record_error(
		string $category,
		string $source_option,
		string $source_key,
		$source_value,
		string $dest_option,
		string $dest_key,
		string $reason
	): void {
		$this->append(
			[
				'type'              => self::TYPE_ERROR,
				'category'          => $category,
				'source_option'     => $source_option,
				'source_key'        => $source_key,
				'source_value'      => $this->redact( $source_key, $source_value ),
				'dest_option'       => $dest_option,
				'dest_key'          => $dest_key,
				'dest_value_before' => null,
				'dest_value_after'  => null,
				'outcome'           => self::OUTCOME_ERRORED,
				'reason'            => $reason,
			]
		);
	}

	/**
	 * Records a revert event: a snapshot value was restored over the post-migration state.
	 *
	 * @param string $category             Category of the row being reverted.
	 * @param string $option_name          wp_options key being restored.
	 * @param mixed  $snapshot_value       Value taken from the snapshot.
	 * @param mixed  $pre_revert_value     Value present immediately before the revert.
	 * @param string $snapshot_id_restored Snapshot timestamp the revert pulled from.
	 * @return void
	 */
	public function record_revert(
		string $category,
		string $option_name,
		$snapshot_value,
		$pre_revert_value,
		string $snapshot_id_restored
	): void {
		$this->append(
			[
				'type'              => self::TYPE_REVERT,
				'category'          => $category,
				'source_option'     => $option_name,
				'source_key'        => null,
				'source_value'      => $this->redact( $option_name, $snapshot_value ),
				'dest_option'       => $option_name,
				'dest_key'          => null,
				'dest_value_before' => $this->redact( $option_name, $pre_revert_value ),
				'dest_value_after'  => $this->redact( $option_name, $snapshot_value ),
				'outcome'           => self::OUTCOME_REVERTED,
				'reason'            => sprintf( 'Restored from snapshot %s', $snapshot_id_restored ),
			]
		);
	}

	/**
	 * Loads the full ledger, oldest row first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function load(): array {
		return self::query_rows( '', [] );
	}

	/**
	 * Returns rows where source_key matches the given value (oldest first).
	 *
	 * @param string $source_key PP key to search for.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_by_source_key( string $source_key ): array {
		return self::query_rows( 'WHERE source_key = %s', [ $source_key ] );
	}

	/**
	 * Returns rows recorded by a single orchestrator run (oldest first).
	 *
	 * @param string $run_id UUID v4.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_by_run_id( string $run_id ): array {
		return self::query_rows( 'WHERE run_id = %s', [ $run_id ] );
	}

	/**
	 * Runs a SELECT against the ledger table and hydrates each row. Returns an
	 * empty array if the table has not been installed yet.
	 *
	 * @param string             $where_sql  Optional `WHERE ...` clause with %s placeholders.
	 * @param array<int, scalar> $where_args Values bound into the placeholders.
	 * @return array<int, array<string, mixed>>
	 */
	private static function query_rows( string $where_sql, array $where_args ): array {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return [];
		}

		$table = self::table_name();
		$where = '' === $where_sql ? '' : ' ' . $where_sql;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sql = "SELECT * FROM {$table}{$where} ORDER BY id ASC";
		if ( ! empty( $where_args ) ) {
			$sql = $wpdb->prepare( $sql, $where_args );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $rows ) ? array_map( [ self::class, 'hydrate_row' ], $rows ) : [];
	}

	/**
	 * Decodes the JSON value columns back to their original PHP types and exposes
	 * the stored UTC datetime as an ISO-8601 `timestamp`.
	 *
	 * @param array<string, mixed> $row Raw DB row (all columns as strings or null).
	 * @return array<string, mixed>
	 */
	private static function hydrate_row( array $row ): array {
		foreach ( [ 'source_value', 'dest_value_before', 'dest_value_after' ] as $field ) {
			$row[ $field ] = null === ( $row[ $field ] ?? null )
				? null
				: json_decode( (string) $row[ $field ], true );
		}

		if ( isset( $row['logged_at'] ) ) {
			$row['timestamp'] = gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $row['logged_at'] . ' UTC' ) );
		}

		return $row;
	}

	/**
	 * Whether the ledger table exists. Guards reads that may run before install().
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Schema version for the ledger table. Bump when columns or indexes change so
	 * install_table() re-runs dbDelta() on the next upgrade.
	 *
	 * @var int
	 */
	public const DB_VERSION = 1;

	/**
	 * Option holding the installed ledger-table schema version. Single small
	 * integer option, autoload=false — used only to gate the dbDelta() diff.
	 *
	 * @var string
	 */
	public const DB_VERSION_OPTION = 'wc_stripe_settings_migration_ledger_db_version';

	/**
	 * Creates or upgrades the ledger table. Idempotent: dbDelta() diffs the live
	 * schema against get_schema_sql() and applies only what changed. Gated on
	 * DB_VERSION so the diff only runs when the schema actually changes.
	 *
	 * Call from WC_Stripe::install() (the existing version-change upgrade entry point).
	 *
	 * @return void
	 */
	public static function install_table(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) === self::DB_VERSION ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::get_schema_sql() );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Returns the CREATE TABLE statement for the ledger, formatted for dbDelta().
	 *
	 * Formatting is load-bearing: dbDelta() needs two spaces after PRIMARY KEY, one
	 * field per line, lowercase `key`, and a charset/collate clause from
	 * $wpdb->get_charset_collate().
	 *
	 * @return string
	 */
	public static function get_schema_sql(): string {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// `id` is the AUTO_INCREMENT surrogate key (natural insert order, cheap
		// pagination); `entry_uuid` keeps the per-row uuid as a stable external
		// reference. The three *_value columns hold JSON (scalars, arrays, or the
		// __REDACTED__ sentinel), so they are LONGTEXT, not typed. run_id and
		// source_key are indexed to back find_by_run_id()/find_by_source_key().
		//
		// dbDelta() formatting is strict: two spaces after PRIMARY KEY, one field
		// per line, lowercase `key`.
		return "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_uuid CHAR(36) NOT NULL,
			run_id CHAR(36) NOT NULL,
			snapshot_id VARCHAR(64) DEFAULT NULL,
			logged_at DATETIME NOT NULL,
			pp_version_detected VARCHAR(32) DEFAULT NULL,
			type VARCHAR(32) NOT NULL,
			category VARCHAR(32) NOT NULL,
			outcome VARCHAR(32) NOT NULL,
			reason TEXT DEFAULT NULL,
			source_option VARCHAR(191) DEFAULT NULL,
			source_key VARCHAR(191) DEFAULT NULL,
			source_value LONGTEXT DEFAULT NULL,
			dest_option VARCHAR(191) DEFAULT NULL,
			dest_key VARCHAR(191) DEFAULT NULL,
			dest_value_before LONGTEXT DEFAULT NULL,
			dest_value_after LONGTEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY source_key (source_key)
		) {$charset_collate};";
	}

	/**
	 * Fully-qualified ledger table name (with $wpdb->prefix).
	 *
	 * @return string
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Appends a row to the ledger blob and mirrors it to the WC log.
	 *
	 * Each row receives an id, run_id, snapshot_id, timestamp, and pp_version_detected
	 * from the instance's context.
	 *
	 * @param array<string, mixed> $row Per-event fields.
	 * @return void
	 */
	private function append( array $row ): void {
		$now        = time();
		$entry_uuid = wp_generate_uuid4();

		$this->insert_row( $entry_uuid, gmdate( 'Y-m-d H:i:s', $now ), $row );

		// The structured record mirrored to the WC log keeps the ISO-8601 timestamp
		// and the per-row uuid under `id` for continuity with the human-tailed log.
		$this->mirror_to_log(
			array_merge(
				[
					'id'                  => $entry_uuid,
					'run_id'              => $this->run_id,
					'snapshot_id'         => $this->snapshot_id,
					'timestamp'           => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
					'pp_version_detected' => $this->pp_version_detected,
				],
				$row
			)
		);
	}

	/**
	 * Inserts one decision row into the ledger table.
	 *
	 * The three *_value fields are JSON-encoded so scalars, arrays, and the
	 * __REDACTED__ sentinel all round-trip losslessly; a PHP null is stored as a
	 * SQL NULL (kept distinct from an empty string).
	 *
	 * @param string               $entry_uuid Per-row uuid.
	 * @param string               $logged_at  Capture time, UTC, MySQL datetime format.
	 * @param array<string, mixed> $row        Per-event fields from a record_* call.
	 * @return void
	 */
	private function insert_row( string $entry_uuid, string $logged_at, array $row ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			self::table_name(),
			[
				'entry_uuid'          => $entry_uuid,
				'run_id'              => $this->run_id,
				'snapshot_id'         => $this->snapshot_id,
				'logged_at'           => $logged_at,
				'pp_version_detected' => $this->pp_version_detected,
				'type'                => $row['type'],
				'category'            => $row['category'],
				'outcome'             => $row['outcome'],
				'reason'              => $row['reason'],
				'source_option'       => $row['source_option'],
				'source_key'          => $row['source_key'],
				'source_value'        => self::encode_value( $row['source_value'] ?? null ),
				'dest_option'         => $row['dest_option'],
				'dest_key'            => $row['dest_key'],
				'dest_value_before'   => self::encode_value( $row['dest_value_before'] ?? null ),
				'dest_value_after'    => self::encode_value( $row['dest_value_after'] ?? null ),
			]
		);
	}

	/**
	 * JSON-encodes a value column, preserving null as SQL NULL.
	 *
	 * @param mixed $value Value to store.
	 * @return string|null
	 */
	private static function encode_value( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$encoded = wp_json_encode( $value );

		return false === $encoded ? null : $encoded;
	}

	/**
	 * Mirrors a structured row to the dedicated WC log file for human tailing.
	 *
	 * @param array<string, mixed> $record Row plus run/snapshot context.
	 * @return void
	 */
	private function mirror_to_log( array $record ): void {
		if ( ! class_exists( 'WC_Stripe_Logger' ) ) {
			return;
		}

		// Override the default log source so ledger rows go to a dedicated WC log file
		// (`wp-content/uploads/wc-logs/woocommerce-gateway-stripe-pp-settings-migration-{date}.log`).
		$encoded = wp_json_encode( $record );
		if ( false !== $encoded ) {
			WC_Stripe_Logger::info( $encoded, [ 'source' => self::LOG_HANDLE ] );
		}
	}

	/**
	 * Redacts a value when the associated key name matches a secret pattern.
	 *
	 * @param string|null $key   Key name to test. Null keys pass through.
	 * @param mixed       $value Original value.
	 * @return mixed The original value, or self::REDACTED.
	 */
	private function redact( ?string $key, $value ) {
		if ( null === $key || '' === $key ) {
			return $value;
		}

		foreach ( self::SECRET_PATTERNS as $pattern ) {
			if ( false !== strpos( $key, $pattern ) ) {
				return self::REDACTED;
			}
		}

		foreach ( self::SECRET_SUFFIXES as $suffix ) {
			$len = strlen( $suffix );
			if ( strlen( $key ) >= $len && substr( $key, -$len ) === $suffix ) {
				return self::REDACTED;
			}
		}

		return $value;
	}
}
