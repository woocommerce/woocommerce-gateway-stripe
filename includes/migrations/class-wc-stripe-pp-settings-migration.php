<?php
/**
 * Orchestrator for the Payment Plugins for Stripe (PP) → Woo Stripe settings
 * migration.
 *
 * Triggered once at OAuth completion in
 * {@see WC_Stripe_Connect::save_stripe_keys()}, between the call to
 * {@see WC_Stripe_Helper::update_main_stripe_settings()} (which writes OAuth
 * credentials and Woo Stripe defaults) and
 * {@see WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc()}
 * (which pushes Woo Stripe's enabled payment methods into Stripe PMC). Ordering
 * is load-bearing: the per-method enable migration writes the PP-derived
 * enabled list so the existing PMC migration picks it up.
 *
 * Idempotent: gated by a `woocommerce_stripe_pp_settings_migrated` option flag.
 * Safe to call on every OAuth completion; runs at most once per site.
 *
 * Non-destructive: PP options are never modified. Woo Stripe destinations are
 * pre-fill only (`! empty( $dest[ $key ] )` guard preserves merchant overrides
 * and OAuth-set values). The pre-migration snapshot captures both sides before
 * any write so the migration is fully reversible.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-pp-version-detector.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-pre-migration-snapshot.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-settings-migration-ledger.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/pp-settings-maps/class-wc-stripe-pp-settings-map.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/pp-settings-maps/class-wc-stripe-pp-settings-map-3x.php';
require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/pp-settings-maps/class-wc-stripe-pp-settings-map-4x.php';

/**
 * PP→Woo Stripe settings migration orchestrator.
 *
 * @since 9.7.0
 */
class WC_Stripe_PP_Settings_Migration {

	/**
	 * Idempotency flag. When 'yes', `maybe_run()` is a no-op.
	 *
	 * Must match {@see WC_Stripe_Pre_Migration_Snapshot::COMPLETED_OPTION_NAME} so the
	 * snapshot's catch-all sweep excludes this key from itself.
	 *
	 * @var string
	 */
	const COMPLETED_OPTION = 'woocommerce_stripe_pp_settings_migrated';

	/**
	 * PP gateway ID → Woo Stripe UPE method ID mapping. Used by the per-method
	 * enable and per-method order migrations.
	 *
	 * Verified against {@see WC_Stripe_Payment_Methods} constants. PP gateway IDs not
	 * representable in Woo Stripe (e.g., `stripe_fpx`, `stripe_billie`) are absent here;
	 * those methods are recorded as BUILD ledger rows during the migration.
	 *
	 * Express checkout methods (`stripe_applepay`, `stripe_googlepay`,
	 * `stripe_payment_request`) are NOT in this table — they're toggled through the
	 * existing express-checkout enable settings handled by AUTO/TRANSFORM rows.
	 *
	 * @var array<string, string>
	 */
	const PP_GATEWAY_ID_TO_UPE_METHOD = [
		'stripe_cc'            => 'card',
		'stripe_klarna'        => 'klarna',
		'stripe_sepa'          => 'sepa_debit',
		'stripe_ideal'         => 'ideal',
		'stripe_bancontact'    => 'bancontact',
		'stripe_eps'           => 'eps',
		'stripe_p24'           => 'p24',
		'stripe_giropay'       => 'giropay',
		'stripe_sofort'        => 'sofort',
		'stripe_alipay'        => 'alipay',
		'stripe_wechat'        => 'wechat_pay',
		'stripe_afterpay'      => 'afterpay_clearpay',
		'stripe_affirm'        => 'affirm',
		'stripe_ach'           => 'us_bank_account',
		'stripe_becs'          => 'au_becs_debit',
		'stripe_blik'          => 'blik',
		'stripe_boleto'        => 'boleto',
		'stripe_oxxo'          => 'oxxo',
		'stripe_multibanco'    => 'multibanco',
		'stripe_cashapp'       => 'cashapp',
		'stripe_amazonpay'     => 'amazon_pay',
		'stripe_link_checkout' => 'link',
	];

	/**
	 * PP gateway IDs known to PP but absent from Woo Stripe. Recorded as BUILD rows when
	 * encountered in the merchant's PP-enabled list. Source: settings-map.md §7 PP-only methods.
	 *
	 * @var array<int, string>
	 */
	const PP_ONLY_GATEWAY_IDS = [
		'stripe_billie',
		'stripe_konbini',
		'stripe_mobilepay',
		'stripe_paynow',
		'stripe_promptpay',
		'stripe_revolut',
		'stripe_swish',
		'stripe_twint',
		'stripe_zip',
		'stripe_paybybank',
		'stripe_fpx',
		'stripe_grabpay',
	];

	/**
	 * Destination key for the Woo Stripe enabled-methods list (consumed by the PMC migration
	 * at {@see WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc()}).
	 *
	 * @var string
	 */
	const DEST_KEY_ENABLED_METHODS = 'upe_checkout_experience_accepted_payments';

	/**
	 * Destination key for the Woo Stripe payment method order. Read by
	 * {@see WC_Stripe_Helper::get_upe_ordered_payment_method_ids()}.
	 *
	 * Note: do NOT also write `stripe_legacy_method_order` — that sibling key was deprecated in
	 * 10.3.0 and only exists to bridge an internal pre-UPE → UPE transition.
	 *
	 * @var string
	 */
	const DEST_KEY_METHOD_ORDER = 'stripe_upe_payment_method_order';

	/**
	 * Runs the migration once per site. Idempotent and self-gated; safe to call on every
	 * OAuth completion.
	 *
	 * Order of operations:
	 *   1. Completed-flag short-circuit.
	 *   2. Detect PP major version (fall back to 3.X best-effort with warning when unknown).
	 *   3. Capture pre-migration snapshot (both sides).
	 *   4. Open a ledger for this run.
	 *   5. Apply AUTO and TRANSFORM rows.
	 *   6. Apply per-method enable migration (architecture-gated on UPM/PMC state).
	 *   7. Apply per-method order migration (runs unconditionally).
	 *   8. Record DROP/INVESTIGATE/BUILD decisions in the ledger.
	 *   9. Set the completed flag.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		if ( 'yes' === get_option( self::COMPLETED_OPTION ) ) {
			return;
		}

		$version = WC_Stripe_PP_Version_Detector::detect_major_version();

		if ( WC_Stripe_PP_Version_Detector::VERSION_UNKNOWN === $version ) {
			// No PP data present. Mark complete and exit; no snapshot, no ledger needed.
			self::log_info( 'PP settings migration: no PP data detected. Skipping.' );
			update_option( self::COMPLETED_OPTION, 'yes' );
			return;
		}

		$map = self::map_for_version( $version );

		if ( null === $map ) {
			// Future version we don't have a map for — fall back to 3.X best-effort.
			self::log_info(
				sprintf(
					'PP settings migration: detected PP major version %s but no concrete map available. Falling back to 3.X best-effort mapping.',
					$version
				)
			);
			$map     = new WC_Stripe_PP_Settings_Map_3X();
			$version = '3';
		}

		// Capture the full state of both sides BEFORE any writes. Load-bearing for rollback.
		$snapshot_at = WC_Stripe_Pre_Migration_Snapshot::capture();

		$run_id = wp_generate_uuid4();
		$ledger = new WC_Stripe_Settings_Migration_Ledger( $run_id, $snapshot_at, $version );

		self::apply_auto_rows( $map->get_auto_rows(), $ledger );
		self::apply_transform_rows( $map->get_transform_rows(), $ledger );

		// Per-method enable migration — architecture-gated on UPM (UPM merchants manage methods
		// centrally via PMC, so per-method `enabled` flags in PP options are likely stale).
		self::apply_per_method_enable( $ledger );

		// Per-method order migration — runs unconditionally. If OCS is on the stored order sits
		// dormant; if the merchant ever disables OCS, their historical PP order applies.
		self::apply_per_method_order( $ledger );

		// Record DROP/INVESTIGATE/BUILD decisions for audit. No writes performed.
		foreach ( $map->get_dropped_rows() as $key ) {
			$ledger->record_drop(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_DROP,
				$key,
				self::source_value_for_key( $key ),
				'Documented no-op — no Woo Stripe equivalent or auto-handled'
			);
		}
		foreach ( $map->get_investigate_rows() as $key ) {
			$ledger->record_drop(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_INVESTIGATE,
				$key,
				self::source_value_for_key( $key ),
				'Pending product decision — see settings map'
			);
		}
		foreach ( $map->get_build_rows() as $key ) {
			$ledger->record_drop(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD,
				$key,
				self::source_value_for_key( $key ),
				'No destination feature in Woo Stripe — out of scope'
			);
		}

		// Persist all buffered ledger rows in a single DB write.
		$ledger->flush();

		update_option( self::COMPLETED_OPTION, 'yes' );

		self::log_info(
			sprintf(
				'PP settings migration: completed. Run %s. PP version: %s. Snapshot: %s.',
				$run_id,
				$version,
				$snapshot_at
			)
		);
	}

	/**
	 * Returns a concrete map for the given major version, or null when no map is available.
	 *
	 * @param string $version Major version string ('3', '4', etc.).
	 * @return WC_Stripe_PP_Settings_Map|null
	 */
	private static function map_for_version( string $version ): ?WC_Stripe_PP_Settings_Map {
		switch ( $version ) {
			case '3':
				return new WC_Stripe_PP_Settings_Map_3X();
			case '4':
				return new WC_Stripe_PP_Settings_Map_4X();
			default:
				return null;
		}
	}

	/**
	 * Applies AUTO rows: direct copy from source→destination when source is present and
	 * destination is empty.
	 *
	 * @param array<int, array<string, string>> $rows   AUTO row definitions.
	 * @param WC_Stripe_Settings_Migration_Ledger $ledger Run-scoped ledger.
	 * @return void
	 */
	private static function apply_auto_rows( array $rows, WC_Stripe_Settings_Migration_Ledger $ledger ): void {
		foreach ( $rows as $row ) {
			$source_option = $row['source_option'];
			$source_key    = $row['source_key'];
			$dest_option   = $row['dest_option'];
			$dest_key      = $row['dest_key'];

			$source = (array) get_option( $source_option, [] );
			$dest   = (array) get_option( $dest_option, [] );

			$source_value = $source[ $source_key ] ?? null;
			$dest_before  = $dest[ $dest_key ] ?? '';

			if ( ! array_key_exists( $source_key, $source ) ) {
				$ledger->record_skip(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
					$source_option,
					$source_key,
					null,
					$dest_option,
					$dest_key,
					$dest_before,
					WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
					'PP source option/key not present'
				);
				continue;
			}

			// Note: `! empty()` treats '0' as empty, so a future AUTO row targeting a numeric
			// field where 0 is a meaningful merchant choice (e.g., button radius) would incorrectly
			// allow an overwrite. Add a sentinel comment in the map row when such a case first
			// appears so reviewers know to switch this guard to `null !== $dest_before`.
			if ( ! empty( $dest_before ) ) {
				$ledger->record_skip(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$dest_before,
					WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
					'Destination already had merchant value — preserved'
				);
				continue;
			}

			try {
				$dest[ $dest_key ] = $source_value;
				update_option( $dest_option, $dest );
				$ledger->record_apply(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$dest_before,
					$source_value
				);
			} catch ( Throwable $e ) {
				$ledger->record_error(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$e->getMessage()
				);
			}
		}
	}

	/**
	 * Applies TRANSFORM rows: source→destination with a transformer callable.
	 *
	 * @param array<int, array<string, mixed>>    $rows   TRANSFORM row definitions.
	 * @param WC_Stripe_Settings_Migration_Ledger $ledger Run-scoped ledger.
	 * @return void
	 */
	private static function apply_transform_rows( array $rows, WC_Stripe_Settings_Migration_Ledger $ledger ): void {
		foreach ( $rows as $row ) {
			$source_option = $row['source_option'];
			$source_key    = $row['source_key'];
			$dest_option   = $row['dest_option'];
			$dest_key      = $row['dest_key'];
			$transformer   = $row['transformer'];

			$source = (array) get_option( $source_option, [] );
			$dest   = (array) get_option( $dest_option, [] );

			$source_value = $source[ $source_key ] ?? null;
			$dest_before  = $dest[ $dest_key ] ?? '';

			if ( ! array_key_exists( $source_key, $source ) ) {
				$ledger->record_skip(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_TRANSFORM,
					$source_option,
					$source_key,
					null,
					$dest_option,
					$dest_key,
					$dest_before,
					WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
					'PP source option/key not present'
				);
				continue;
			}

			// Note: see the same guard in apply_auto_rows() — same numeric-zero caveat applies here.
			if ( ! empty( $dest_before ) ) {
				$ledger->record_skip(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_TRANSFORM,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$dest_before,
					WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
					'Destination already had merchant value — preserved'
				);
				continue;
			}

			try {
				$transformed = call_user_func( $transformer, $source_value );
			} catch ( Throwable $e ) {
				$ledger->record_skip(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_TRANSFORM,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$dest_before,
					WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_TRANSFORM_FAILED,
					'Transformer threw: ' . $e->getMessage()
				);
				continue;
			}

			try {
				$dest[ $dest_key ] = $transformed;
				update_option( $dest_option, $dest );
				$ledger->record_apply(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_TRANSFORM,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$dest_before,
					$transformed
				);
			} catch ( Throwable $e ) {
				$ledger->record_error(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_TRANSFORM,
					$source_option,
					$source_key,
					$source_value,
					$dest_option,
					$dest_key,
					$e->getMessage()
				);
			}
		}
	}

	/**
	 * Per-method enable migration. Architecture-gated.
	 *
	 * - When PP's UPM gateway was enabled, the merchant managed methods centrally via PMC and
	 *   PP's per-gateway `enabled` flags are likely stale. SKIP.
	 * - Otherwise: read PP's per-gateway `enabled` flags, map to Woo Stripe UPE method IDs,
	 *   and write the consolidated list into `upe_checkout_experience_accepted_payments`.
	 *   The existing PMC migration at
	 *   {@see WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc()}
	 *   then pushes this list to Stripe PMC.
	 *
	 * PP-only methods (Billie, Konbini, MobilePay, etc.) are recorded as BUILD ledger rows so
	 * the audit trail captures the shopper-facing regression.
	 *
	 * @param WC_Stripe_Settings_Migration_Ledger $ledger Run-scoped ledger.
	 * @return void
	 */
	private static function apply_per_method_enable( WC_Stripe_Settings_Migration_Ledger $ledger ): void {
		$upm_settings = get_option( 'woocommerce_stripe_upm_settings', [] );
		$upm_enabled  = is_array( $upm_settings ) && ( $upm_settings['enabled'] ?? 'no' ) === 'yes';

		if ( $upm_enabled ) {
			$ledger->record_skip(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
				'woocommerce_stripe_<gateway>_settings',
				'enabled',
				null,
				'woocommerce_stripe_settings',
				self::DEST_KEY_ENABLED_METHODS,
				null,
				WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
				'UPM enabled in PP — methods are managed centrally via PMC; per-gateway `enabled` flags are unreliable'
			);
			return;
		}

		$dest         = (array) get_option( 'woocommerce_stripe_settings', [] );
		$dest_before  = $dest[ self::DEST_KEY_ENABLED_METHODS ] ?? [];
		$enabled_list = is_array( $dest_before ) ? $dest_before : [];

		// Pre-load all PP per-gateway settings in one pass to avoid 21+ individual get_option()
		// calls inside the loop below.
		$all_gateway_ids      = array_merge( array_keys( self::PP_GATEWAY_ID_TO_UPE_METHOD ), self::PP_ONLY_GATEWAY_IDS );
		$gateway_settings_map = [];
		foreach ( $all_gateway_ids as $gw_id ) {
			$settings = get_option( 'woocommerce_' . $gw_id . '_settings', null );
			if ( is_array( $settings ) ) {
				$gateway_settings_map[ $gw_id ] = $settings;
			}
		}

		foreach ( self::PP_GATEWAY_ID_TO_UPE_METHOD as $pp_gateway_id => $upe_method_id ) {
			$gateway_settings = $gateway_settings_map[ $pp_gateway_id ] ?? null;
			if ( null === $gateway_settings ) {
				continue;
			}

			$gateway_enabled = ( $gateway_settings['enabled'] ?? 'no' ) === 'yes';
			if ( ! $gateway_enabled ) {
				continue;
			}

			if ( in_array( $upe_method_id, $enabled_list, true ) ) {
				continue;
			}

			$enabled_list[] = $upe_method_id;

			$ledger->record_apply(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
				'woocommerce_' . $pp_gateway_id . '_settings',
				'enabled',
				'yes',
				'woocommerce_stripe_settings',
				self::DEST_KEY_ENABLED_METHODS,
				$dest_before,
				$enabled_list
			);
		}

		// Record PP-only methods that were enabled but have no Woo Stripe destination.
		foreach ( self::PP_ONLY_GATEWAY_IDS as $pp_gateway_id ) {
			$gateway_settings = $gateway_settings_map[ $pp_gateway_id ] ?? null;
			if ( null === $gateway_settings ) {
				continue;
			}
			if ( ( $gateway_settings['enabled'] ?? 'no' ) !== 'yes' ) {
				continue;
			}

			$ledger->record_drop(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD,
				$pp_gateway_id,
				'yes',
				'PP-only payment method — no Woo Stripe destination. Shopper-facing regression for this method.'
			);
		}

		// Only persist if we added at least one new entry.
		$current_dest_before = is_array( $dest_before ) ? $dest_before : [];
		if ( $current_dest_before !== $enabled_list ) {
			$dest[ self::DEST_KEY_ENABLED_METHODS ] = array_values( array_unique( $enabled_list ) );
			update_option( 'woocommerce_stripe_settings', $dest );
		}
	}

	/**
	 * Per-method order migration. Runs unconditionally.
	 *
	 * Reads WooCommerce core's `woocommerce_gateway_order` option, filters to PP Stripe gateway
	 * IDs while preserving position, maps each to its Woo Stripe UPE method ID, and writes the
	 * result to `stripe_upe_payment_method_order` in Woo Stripe's main settings.
	 *
	 * If OCS is enabled the stored value sits dormant (PMC handles ordering). If the merchant
	 * ever disables OCS, their historical PP order applies instead of Woo Stripe defaults.
	 *
	 * @param WC_Stripe_Settings_Migration_Ledger $ledger Run-scoped ledger.
	 * @return void
	 */
	private static function apply_per_method_order( WC_Stripe_Settings_Migration_Ledger $ledger ): void {
		$pp_order = get_option( 'woocommerce_gateway_order', [] );
		if ( ! is_array( $pp_order ) || empty( $pp_order ) ) {
			$ledger->record_skip(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
				'woocommerce_gateway_order',
				'<order>',
				null,
				'woocommerce_stripe_settings',
				self::DEST_KEY_METHOD_ORDER,
				null,
				WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
				'WooCommerce gateway order option not set'
			);
			return;
		}

		// `woocommerce_gateway_order` is a map gateway_id => position. Sort by position
		// (ascending) and extract the gateway IDs.
		asort( $pp_order );
		$ordered_gateway_ids = array_keys( $pp_order );

		$mapped_order = [];
		foreach ( $ordered_gateway_ids as $gateway_id ) {
			$gateway_id_str = (string) $gateway_id;
			if ( isset( self::PP_GATEWAY_ID_TO_UPE_METHOD[ $gateway_id_str ] ) ) {
				$mapped_order[] = self::PP_GATEWAY_ID_TO_UPE_METHOD[ $gateway_id_str ];
				continue;
			}

			if ( in_array( $gateway_id_str, self::PP_ONLY_GATEWAY_IDS, true ) ) {
				$ledger->record_drop(
					WC_Stripe_Settings_Migration_Ledger::CATEGORY_BUILD,
					$gateway_id_str,
					'<in gateway order>',
					'PP-only payment method appeared in WC gateway order; dropped from Woo Stripe order list'
				);
			}
		}

		// Nothing mappable.
		if ( empty( $mapped_order ) ) {
			$ledger->record_skip(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
				'woocommerce_gateway_order',
				'<order>',
				$pp_order,
				'woocommerce_stripe_settings',
				self::DEST_KEY_METHOD_ORDER,
				null,
				WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_SOURCE_MISSING,
				'No PP Stripe gateway IDs found in WC gateway order'
			);
			return;
		}

		$dest        = (array) get_option( 'woocommerce_stripe_settings', [] );
		$dest_before = $dest[ self::DEST_KEY_METHOD_ORDER ] ?? [];

		if ( ! empty( $dest_before ) ) {
			$ledger->record_skip(
				WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
				'woocommerce_gateway_order',
				'<order>',
				$pp_order,
				'woocommerce_stripe_settings',
				self::DEST_KEY_METHOD_ORDER,
				$dest_before,
				WC_Stripe_Settings_Migration_Ledger::OUTCOME_SKIPPED_DEST_SET,
				'Destination already had merchant order — preserved'
			);
			return;
		}

		$mapped_order                        = array_values( array_unique( $mapped_order ) );
		$dest[ self::DEST_KEY_METHOD_ORDER ] = $mapped_order;
		update_option( 'woocommerce_stripe_settings', $dest );

		$ledger->record_apply(
			WC_Stripe_Settings_Migration_Ledger::CATEGORY_AUTO,
			'woocommerce_gateway_order',
			'<order>',
			$pp_order,
			'woocommerce_stripe_settings',
			self::DEST_KEY_METHOD_ORDER,
			$dest_before,
			$mapped_order
		);
	}

	/**
	 * Looks up a setting's current PP value by source key name. Best-effort: scans the known PP
	 * option blobs for the key. Used by the ledger to capture context for DROP/INVESTIGATE/BUILD
	 * rows. Returns null when the key isn't found in any scanned blob.
	 *
	 * @param string $key PP source key.
	 * @return mixed
	 */
	private static function source_value_for_key( string $key ) {
		$scan_options = [
			'woocommerce_stripe_api_settings',
			'woocommerce_stripe_advanced_settings',
			'woocommerce_stripe_cc_settings',
			'woocommerce_stripe_express_checkout_settings',
		];

		foreach ( $scan_options as $option_name ) {
			$blob = get_option( $option_name, null );
			if ( is_array( $blob ) && array_key_exists( $key, $blob ) ) {
				return $blob[ $key ];
			}
		}

		return null;
	}

	/**
	 * Writes an info-level entry to the dedicated migration log file.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	private static function log_info( string $message ): void {
		if ( class_exists( 'WC_Stripe_Logger' ) ) {
			WC_Stripe_Logger::info( $message, [ 'source' => WC_Stripe_Settings_Migration_Ledger::LOG_HANDLE ] );
		}
	}
}
