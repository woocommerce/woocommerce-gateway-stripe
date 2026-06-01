<?php
/**
 * Class WC_Stripe_OCS_AP_Default_On_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_OCS_AP_Default_On_Update
 *
 * Handle x.y.z->10.8 migration that defaults Optimized Checkout (OC) and
 * Adaptive Pricing (AP) on for back-book merchants, decides per-merchant
 * banner visibility.
 *
 * @since 10.8.0
 */
class WC_Stripe_OCS_AP_Default_On_Update {

	/**
	 * Option flag used to ensure the migration only runs once.
	 */
	private const MIGRATION_FLAG_OPTION = 'wc_stripe_ocs_ap_default_on_migration_ran';

	/**
	 * Server-side visibility flags consumed by the settings-controller gate.
	 */
	private const SHOW_OCS_AP_BANNER_OPTION  = 'wc_stripe_show_ocs_ap_banner';
	private const SHOW_AP_ONLY_BANNER_OPTION = 'wc_stripe_show_ap_only_banner';

	/**
	 * Epoch time for 2026-05-14 09:30 UTC.
	 * Stripe accounts created on or after this timestamp are treated as
	 * likely-10.7 frontbook signups for the audience-exclusion heuristic.
	 */
	private const OC_AP_DEFAULT_ON_RELEASE_TS = 1778751000;

	/**
	 * ISO country code for the banner geo-exclusion.
	 */
	private const EXCLUDED_COUNTRY = 'IN';

	/**
	 * Entry point invoked by WC_Stripe_Update_Manager.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		if ( 'yes' === get_option( self::MIGRATION_FLAG_OPTION ) ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Skipping: migration already ran on this site.' );
			return;
		}

		$previous_version = get_option( 'wc_stripe_version' );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- distinguishing `false` (option unset) from string versions in the log message.
		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Migration started. previous_version=%s.', var_export( $previous_version, true ) ) );

		if ( false === $previous_version ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Skipping: new install (no previous_version recorded). Frontbook 10.8 path will be handled at OAuth time.' );
			update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
			return;
		}

		if ( version_compare( (string) $previous_version, '10.8.0', '>=' ) ) {
			WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Skipping: previous_version=%s already >= 10.8.0.', $previous_version ) );
			update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
			return;
		}

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$oc_pre          = ( $stripe_settings['optimized_checkout_element'] ?? 'no' ) === 'yes';
		$ap_pre          = ( $stripe_settings['adaptive_pricing'] ?? 'no' ) === 'yes';

		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Pre-flip gateway state: optimized_checkout_element=%s, adaptive_pricing=%s.', $oc_pre ? 'yes' : 'no', $ap_pre ? 'yes' : 'no' ) );

		$country      = $this->get_account_country();
		$is_india     = self::EXCLUDED_COUNTRY === $country;
		$is_frontbook = $this->is_likely_frontbook_10_7( (string) $previous_version );

		[ $show_a, $show_b ] = $this->decide_banner_visibility( $oc_pre, $ap_pre, $is_frontbook, $is_india );

		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Banner decision: wc_stripe_show_ocs_ap_banner=%s, wc_stripe_show_ap_only_banner=%s.', $show_a ? 'yes' : 'no', $show_b ? 'yes' : 'no' ) );

		update_option( self::SHOW_OCS_AP_BANNER_OPTION, $show_a ? 'yes' : 'no' );
		update_option( self::SHOW_AP_ONLY_BANNER_OPTION, $show_b ? 'yes' : 'no' );

		// - India merchants are excluded from backbook defaults
		// - Skip the flip for any feature the merchant likely
		//   explicitly disabled after frontbook default on in 10.7
		$flip_oc = ! $is_india && ! ( $is_frontbook && ! $oc_pre );
		$flip_ap = ! $is_india && ! ( $is_frontbook && ! $ap_pre );

		if ( $flip_oc ) {
			$stripe_settings['optimized_checkout_element'] = 'yes';
		}
		if ( $flip_ap ) {
			$stripe_settings['adaptive_pricing'] = 'yes';
		}
		if ( $flip_oc || $flip_ap ) {
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}

		$skip_reason = $is_india ? 'skipped (India geo-exclusion)' : 'skipped (respect-disable)';
		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Flip applied: optimized_checkout_element=%s, adaptive_pricing=%s.', $flip_oc ? 'yes' : $skip_reason, $flip_ap ? 'yes' : $skip_reason ) );

		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );

		WC_Stripe_Logger::info( '[OCS+AP 10.8] Migration complete. Banner-visibility options and ran-once flag written.' );
	}

	/**
	 * Apply the audience exclusion rules from the spec.
	 *
	 * @param bool $oc_pre       Whether OC was 'yes' before this migration.
	 * @param bool $ap_pre       Whether AP was 'yes' before this migration.
	 * @param bool $is_frontbook Whether the merchant is likely a 10.7 frontbook signup.
	 * @param bool $is_india     Whether the connected Stripe account is in India.
	 *
	 * @return array{0: bool, 1: bool} Tuple of (show_banner_a, show_banner_b).
	 */
	protected function decide_banner_visibility( bool $oc_pre, bool $ap_pre, bool $is_frontbook, bool $is_india ): array {
		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Decision inputs: oc_pre=%s, ap_pre=%s, is_india=%s, is_frontbook=%s.', $oc_pre ? 'yes' : 'no', $ap_pre ? 'yes' : 'no', $is_india ? 'yes' : 'no', $is_frontbook ? 'yes' : 'no' ) );

		if ( $is_india ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: India geo-exclusion -> no banner.' );
			return [ false, false ];
		}

		if ( $oc_pre && $ap_pre ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: OC and AP both already on -> spec exclusion #1, no banner.' );
			return [ false, false ];
		}

		if ( ! $oc_pre && $ap_pre ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: OC=no AP=yes (merchant explicitly disabled OC) -> no banner.' );
			return [ false, false ];
		}

		if ( ! $oc_pre && ! $ap_pre ) {
			if ( $is_frontbook ) {
				WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: both off + likely frontbook 10.7 -> spec exclusion #2, no Banner A.' );
			} else {
				WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: both off + not frontbook 10.7 -> Banner A.' );
			}
			return [ ! $is_frontbook, false ];
		}

		// Remaining branch: oc_pre=yes, ap_pre=no.
		if ( $is_frontbook ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: OC=yes AP=no + likely frontbook 10.7 (disabled AP) -> spec exclusion #2, no Banner B.' );
		} else {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Audience: OC=yes AP=no + not frontbook 10.7 -> Banner B.' );
		}
		return [ false, ! $is_frontbook ];
	}

	/**
	 * Heuristic for "the merchant likely received the 10.7 frontbook OC+AP
	 * default-on at OAuth time". Both conditions must hold:
	 *
	 * 1. They had 10.7 installed at some point before this 10.8 upgrade
	 * 2. Their Stripe account was created on/after the 10.7 release date
	 *    (so they most plausibly signed up via 10.7+).
	 *
	 * Returns false on missing account data to keep the conservative path
	 * "show the banner if otherwise eligible."
	 *
	 * @param string $previous_version
	 *
	 * @return bool
	 */
	protected function is_likely_frontbook_10_7( string $previous_version ): bool {
		if ( version_compare( $previous_version, '10.7.0', '<' ) ) {
			WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Frontbook heuristic: previous_version=%s < 10.7.0 -> not frontbook.', $previous_version ) );
			return false;
		}
		$created = $this->get_account_created_ts();
		if ( null === $created ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Frontbook heuristic: account.created unavailable -> not frontbook (conservative).' );
			return false;
		}
		$is_frontbook = $created >= self::OC_AP_DEFAULT_ON_RELEASE_TS;
		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Frontbook heuristic: account.created=%d, threshold=%d -> frontbook=%s.', $created, self::OC_AP_DEFAULT_ON_RELEASE_TS, $is_frontbook ? 'yes' : 'no' ) );
		return $is_frontbook;
	}

	/**
	 * Resolves the connected Stripe account's ISO country code.
	 *
	 * @return string
	 */
	protected function get_account_country(): string {
		$country = WC_Stripe::get_instance()->account->get_account_country();
		return is_string( $country ) ? $country : '';
	}

	/**
	 * Resolves the connected Stripe account's `created` Unix timestamp.
	 * Note: this is a best effort attempt. If it's a standard account, API response
	 * will not include `created` and this will return null. If it's a connected account, it should be present.
	 *
	 * @return int|null
	 */
	protected function get_account_created_ts(): ?int {
		$account = WC_Stripe::get_instance()->account->get_cached_account_data();
		$created = $account['created'] ?? null;
		return is_int( $created ) ? $created : null;
	}
}
