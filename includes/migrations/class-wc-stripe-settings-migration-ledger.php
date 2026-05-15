<?php
/**
 * Append-only structured log of every per-row decision made by the PP→Woo
 * Stripe settings migration. Cross-references the pre-migration snapshot via
 * `snapshot_id` so support and audit workflows can reconstruct exactly what
 * the migration did and why.
 *
 * Storage:
 *   - wp_options row at `wc_stripe_settings_migration_ledger`, autoload=false.
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
	 * WP options key for the ledger blob.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'wc_stripe_settings_migration_ledger';

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
	 * In-memory row buffer. Rows accumulate here until `flush()` is called, which
	 * writes the entire batch to the DB in a single `update_option()` call.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $buffer = [];

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
	 * Loads the current ledger contents.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function load(): array {
		$ledger = get_option( self::OPTION_NAME, [] );
		return is_array( $ledger ) ? $ledger : [];
	}

	/**
	 * Returns rows where source_key matches the given value.
	 *
	 * @param string $source_key PP key to search for.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_by_source_key( string $source_key ): array {
		return array_values(
			array_filter(
				self::load(),
				static function ( $row ) use ( $source_key ) {
					return ( $row['source_key'] ?? null ) === $source_key;
				}
			)
		);
	}

	/**
	 * Returns rows recorded by a single orchestrator run.
	 *
	 * @param string $run_id UUID v4.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_by_run_id( string $run_id ): array {
		return array_values(
			array_filter(
				self::load(),
				static function ( $row ) use ( $run_id ) {
					return ( $row['run_id'] ?? null ) === $run_id;
				}
			)
		);
	}

	/**
	 * Writes all buffered rows to the DB in a single `update_option()` call and mirrors each to
	 * the WC log. Called once at the end of `WC_Stripe_PP_Settings_Migration::maybe_run()`.
	 *
	 * @return void
	 */
	public function flush(): void {
		if ( empty( $this->buffer ) ) {
			return;
		}

		$ledger = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $ledger ) ) {
			$ledger = [];
		}

		foreach ( $this->buffer as $row ) {
			$ledger[] = $row;

			if ( class_exists( 'WC_Stripe_Logger' ) ) {
				// Rows go to a dedicated WC log file separate from the main gateway log.
				$encoded = wp_json_encode( $row );
				if ( false !== $encoded ) {
					WC_Stripe_Logger::info( $encoded, [ 'source' => self::LOG_HANDLE ] );
				}
			}
		}

		update_option( self::OPTION_NAME, $ledger, false );
		$this->buffer = [];
	}

	/**
	 * Buffers a row in memory. Rows are persisted to the DB only when `flush()` is called.
	 *
	 * Each row receives an id, run_id, snapshot_id, timestamp, and pp_version_detected
	 * from the instance's context.
	 *
	 * @param array<string, mixed> $row Per-event fields.
	 * @return void
	 */
	private function append( array $row ): void {
		$this->buffer[] = array_merge(
			[
				'id'                  => wp_generate_uuid4(),
				'run_id'              => $this->run_id,
				'snapshot_id'         => $this->snapshot_id,
				'timestamp'           => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'pp_version_detected' => $this->pp_version_detected,
			],
			$row
		);
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
