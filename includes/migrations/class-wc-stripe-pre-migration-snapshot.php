<?php
/**
 * Captures a full pre-migration snapshot of every Stripe-related wp_options
 * entry on the site — both Payment Plugins for Stripe (PP) and the official
 * Woo Stripe extension — immediately before the settings migration writes
 * anything.
 *
 * Serves three purposes:
 *   1. Full rollback for the migration's own writes on either side.
 *   2. Forensic / support diagnostics ("you broke setting X" → diff).
 *   3. Migration runway for future iterations of currently-INVESTIGATE rows.
 *
 * The snapshot is one-way: it records, it does not modify. The migration tool
 * never overwrites live state from the snapshot automatically.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pre-migration snapshot capture and retrieval.
 *
 * @since 10.8.0
 */
class WC_Stripe_Pre_Migration_Snapshot {

	/**
	 * WP options key for the single pre-migration snapshot. One bounded option,
	 * autoload=false; re-running capture() overwrites it in place. The migration is
	 * one-time, so the most recent pre-migration state is all that needs retaining.
	 *
	 * @var string
	 */
	public const CURRENT_OPTION = 'wc_stripe_pre_migration_snapshot';

	/**
	 * Schema version for the snapshot blob. Bump when changing the captured structure.
	 *
	 * @var int
	 */
	public const SNAPSHOT_VERSION = 1;

	/**
	 * Sentinel used to distinguish "option missing" from "option exists with empty value".
	 *
	 * Both are legitimate states in PP/Woo Stripe option blobs.
	 *
	 * @var string
	 */
	public const SENTINEL_NOT_SET = '__WC_STRIPE_NOT_SET__';

	/**
	 * The migration's completed-flag option name. Excluded from the catch-all sweep so the
	 * snapshot never captures its own infrastructure state.
	 *
	 * Kept here rather than on the orchestrator so the snapshot has no dependency on the
	 * orchestrator. The orchestrator should reference this constant.
	 *
	 * @var string
	 */
	public const COMPLETED_OPTION_NAME = 'woocommerce_stripe_pp_settings_migrated';

	/**
	 * Explicitly enumerated option keys. Anything missing here is caught by the LIKE-pattern sweep.
	 *
	 * @var array<int, string>
	 */
	private const KNOWN_OPTION_KEYS = [
		// --- Version markers ---
		'stripe_wc_version',
		// --- PP global settings ---
		'woocommerce_stripe_api_settings',
		'woocommerce_stripe_advanced_settings',
		'woocommerce_stripe_express_checkout_settings',
		// --- PP per-gateway settings (derived from PP v3.3.106 gateway IDs) ---
		'woocommerce_stripe_cc_settings',
		'woocommerce_stripe_applepay_settings',
		'woocommerce_stripe_googlepay_settings',
		'woocommerce_stripe_payment_request_settings',
		'woocommerce_stripe_amazonpay_settings',
		'woocommerce_stripe_sepa_settings',
		'woocommerce_stripe_becs_settings',
		'woocommerce_stripe_bancontact_settings',
		'woocommerce_stripe_ideal_settings',
		'woocommerce_stripe_eps_settings',
		'woocommerce_stripe_giropay_settings',
		'woocommerce_stripe_p24_settings',
		'woocommerce_stripe_klarna_settings',
		'woocommerce_stripe_afterpay_settings',
		'woocommerce_stripe_affirm_settings',
		'woocommerce_stripe_link_checkout_settings',
		'woocommerce_stripe_sofort_settings',
		'woocommerce_stripe_alipay_settings',
		'woocommerce_stripe_wechat_settings',
		'woocommerce_stripe_multibanco_settings',
		'woocommerce_stripe_grabpay_settings',
		'woocommerce_stripe_paynow_settings',
		'woocommerce_stripe_promptpay_settings',
		'woocommerce_stripe_revolut_settings',
		'woocommerce_stripe_fpx_settings',
		'woocommerce_stripe_zip_settings',
		'woocommerce_stripe_mobilepay_settings',
		'woocommerce_stripe_blik_settings',
		'woocommerce_stripe_billie_settings',
		'woocommerce_stripe_paybybank_settings',
		'woocommerce_stripe_twint_settings',
		'woocommerce_stripe_cashapp_settings',
		'woocommerce_stripe_upm_settings',
		'woocommerce_stripe_ach_settings',
		'woocommerce_stripe_plaid_settings',
		// --- Woo Stripe (official extension) settings ---
		// Main blob; the destination of most AUTO/TRANSFORM writes. Captured pre-migration so we
		// can fully restore Woo Stripe's settings if needed. Note: this key OVERLAPS with PP's
		// namespace. The snapshot captures whatever is at the key without attributing ownership.
		'woocommerce_stripe_settings',
		// Account retention. Uses `woocommerce_gateway_stripe_` prefix (not `woocommerce_stripe_`),
		// hence the dedicated LIKE pattern in the catch-all sweep.
		'woocommerce_gateway_stripe_retention',
		// OAuth state (live + test variants).
		'wc_stripe_oauth_required',
		'wc_stripe_oauth_updated_at',
		'wc_stripe_oauth_failed_attempts',
		'wc_stripe_oauth_last_failed_at',
		'wc_stripe_test_oauth_updated_at',
		'wc_stripe_test_oauth_failed_attempts',
		'wc_stripe_test_oauth_last_failed_at',
		// Payment Method Configuration fallback IDs.
		'woocommerce_stripe_pmc_fallback_id_live',
		'woocommerce_stripe_pmc_fallback_id_test',
		// Woo Stripe install-time feature defaults (one-shot flags).
		'wc_stripe_amazon_pay_default_on',
		'wc_stripe_optimized_checkout_default_on',
		// Woo Stripe version + prior-migration completion flags (forensic context).
		'wc_stripe_version',
		'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated',
		'woocommerce_stripe_subscriptions_with_legacy_sepa',
	];

	/**
	 * LIKE patterns used by the catch-all sweep. Pulls anything in either plugin's namespace
	 * we didn't explicitly enumerate above.
	 *
	 * Note: `wcstripe_cache_%` (no underscore between `wc` and `stripe`) is intentionally NOT
	 * included — that's the regenerable database cache, can be very large, not configuration state.
	 *
	 * @var array<int, string>
	 */
	private const LIKE_PATTERNS = [
		'woocommerce_stripe_%',
		'woocommerce_gateway_stripe_%',
		'stripe_wc_%',
		'wc_stripe_%',
	];

	/**
	 * Captures a full snapshot of all Stripe-related options into the single
	 * snapshot option, overwriting any prior capture in place.
	 *
	 * @return string ISO-like timestamp (mysql format, UTC) of the capture.
	 */
	public static function capture(): string {
		$captured_at = current_time( 'mysql', true );

		$pp_version_full = WC_Stripe_PP_Version_Detector::detect_full_version();
		$pp_major        = WC_Stripe_PP_Version_Detector::detect_major_version();

		$blob = [
			'snapshot_version'  => self::SNAPSHOT_VERSION,
			'captured_at'       => $captured_at,
			'pp_version'        => $pp_version_full,
			'pp_major_detected' => $pp_major,
			'wc_stripe_version' => defined( 'WC_STRIPE_VERSION' ) ? WC_STRIPE_VERSION : null,
			'wp_version'        => get_bloginfo( 'version' ),
			'options'           => self::collect_options(),
		];

		update_option( self::CURRENT_OPTION, $blob, false );

		if ( class_exists( 'WC_Stripe_Logger' ) ) {
			WC_Stripe_Logger::info(
				sprintf(
					'Pre-migration snapshot captured at %s. PP version: %s. Woo Stripe version: %s. Options captured: %d.',
					$captured_at,
					$pp_version_full ?? 'unknown',
					$blob['wc_stripe_version'] ?? 'unknown',
					count( $blob['options'] )
				)
			);
		}

		return $captured_at;
	}

	/**
	 * Returns the canonical (most recent) snapshot, or null if no snapshot has been captured.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_current(): ?array {
		$snapshot = get_option( self::CURRENT_OPTION, null );
		return is_array( $snapshot ) ? $snapshot : null;
	}

	/**
	 * Builds the `options` portion of the snapshot blob.
	 *
	 * Layer 1: known option keys (explicit enumeration).
	 * Layer 2: LIKE-pattern catch-all for anything we didn't enumerate, on either side.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_options(): array {
		global $wpdb;

		$captured = [];

		// Layer 1: known option keys.
		foreach ( self::KNOWN_OPTION_KEYS as $key ) {
			$value = get_option( $key, self::SENTINEL_NOT_SET );
			if ( self::SENTINEL_NOT_SET !== $value ) {
				$captured[ $key ] = $value;
			}
		}

		// Layer 2: LIKE-pattern catch-all sweep.
		foreach ( self::LIKE_PATTERNS as $pattern ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				),
				ARRAY_A
			);

			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$key = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';

				if ( '' === $key || isset( $captured[ $key ] ) ) {
					continue;
				}

				if ( self::is_snapshot_infrastructure_key( $key ) ) {
					continue;
				}

				$captured[ $key ] = maybe_unserialize( $row['option_value'] ?? '' );
			}
		}

		return $captured;
	}

	/**
	 * Returns true if the option key is part of the snapshot's own infrastructure
	 * and must not be captured in the catch-all sweep (recursion prevention).
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	private static function is_snapshot_infrastructure_key( string $key ): bool {
		if ( self::CURRENT_OPTION === $key ) {
			return true;
		}

		if ( self::COMPLETED_OPTION_NAME === $key ) {
			return true;
		}

		return false;
	}
}
