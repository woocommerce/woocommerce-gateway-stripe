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
	private const SHOW_OCS_AP_BANNER_OPTION   = 'wc_stripe_show_ocs_ap_banner';
	private const SHOW_AP_ONLY_BANNER_OPTION  = 'wc_stripe_show_ap_only_banner';
	private const SHOW_OCS_ONLY_BANNER_OPTION = 'wc_stripe_show_ocs_only_banner';

	/**
	 * Epoch time for 2026-05-14 09:30 UTC.
	 * Stripe accounts created on or after this timestamp are treated as
	 * creating their account after 10.7 was released.
	 */
	private const OC_AP_DEFAULT_ON_RELEASE_TS = 1778751000;

	/**
	 * Entry point invoked by WC_Stripe_Update_Manager.
	 *
	 * @param string|false $previous_version The plugin version recorded before this upgrade, or false on a new install.
	 *
	 * @return void
	 */
	public function maybe_migrate( $previous_version = false ): void {
		if ( 'yes' === get_option( self::MIGRATION_FLAG_OPTION ) ) {
			WC_Stripe_Logger::info( '[OCS+AP 10.8] Skipping: migration already ran on this site.' );
			return;
		}

		// json-encode so `false` (new install) is distinguishable from string versions in the log message.
		WC_Stripe_Logger::info( sprintf( '[OCS+AP 10.8] Migration started. previous_version=%s.', wp_json_encode( $previous_version ) ) );

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

		$ap_unavailable_reason = $this->get_ap_unavailable_reason();
		$ap_available          = null === $ap_unavailable_reason;
		$is_frontbook          = $this->is_likely_frontbook_10_7( (string) $previous_version );

		// OCS and AP only function when the store is connected to our platform account
		// (pmc_enabled='yes'); AP additionally rides on OCS at runtime. Withhold both
		// flips for connected non-PMC accounts. For an empty account read we stay
		// optimistic and still enable.
		$has_account_data = ! empty( $this->get_account_data() );
		$pmc_enabled      = ( $stripe_settings['pmc_enabled'] ?? 'no' ) === 'yes';
		$oc_eligible      = ! $has_account_data || $pmc_enabled;

		$enable_oc = $oc_eligible && ! ( $is_frontbook && ! $oc_pre );
		$enable_ap = $oc_eligible && $ap_available && ! ( $is_frontbook && ! $ap_pre );

		$oc_newly_enabled = $enable_oc && ! $oc_pre;
		$ap_newly_enabled = $enable_ap && ! $ap_pre;

		$show_ocs_ap   = $oc_newly_enabled && $ap_newly_enabled;
		$show_ap_only  = $ap_newly_enabled && ! $oc_newly_enabled;
		$show_ocs_only = $oc_newly_enabled && ! $enable_ap;

		WC_Stripe_Logger::info(
			sprintf(
				'[OCS+AP 10.8] Decision: ap_available=%s (reason=%s), is_frontbook=%s, has_account_data=%s, pmc_enabled=%s, oc_eligible=%s, enable_oc=%s, enable_ap=%s -> show_ocs_ap=%s, show_ap_only=%s, show_ocs_only=%s.',
				$ap_available ? 'yes' : 'no',
				$ap_unavailable_reason ?? 'available',
				$is_frontbook ? 'yes' : 'no',
				$has_account_data ? 'yes' : 'no',
				$pmc_enabled ? 'yes' : 'no',
				$oc_eligible ? 'yes' : 'no',
				$enable_oc ? 'yes' : 'no',
				$enable_ap ? 'yes' : 'no',
				$show_ocs_ap ? 'yes' : 'no',
				$show_ap_only ? 'yes' : 'no',
				$show_ocs_only ? 'yes' : 'no'
			)
		);

		update_option( self::SHOW_OCS_AP_BANNER_OPTION, $show_ocs_ap ? 'yes' : 'no' );
		update_option( self::SHOW_AP_ONLY_BANNER_OPTION, $show_ap_only ? 'yes' : 'no' );
		update_option( self::SHOW_OCS_ONLY_BANNER_OPTION, $show_ocs_only ? 'yes' : 'no' );

		if ( $enable_oc ) {
			$stripe_settings['optimized_checkout_element'] = 'yes';
		}
		if ( $enable_ap ) {
			$stripe_settings['adaptive_pricing'] = 'yes';
		}
		if ( $enable_oc || $enable_ap ) {
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}

		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );

		WC_Stripe_Logger::info( '[OCS+AP 10.8] Migration complete. Banner-visibility options and ran-once flag written.' );
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
	 * Returns the reason Adaptive Pricing is unavailable for the connected account,
	 * or null when AP is available. Wraps the shared helper so it can be overridden
	 * in tests.
	 *
	 * @return string|null
	 */
	protected function get_ap_unavailable_reason(): ?string {
		return WC_Stripe_Helper::get_adaptive_pricing_account_unavailable_reason();
	}

	/**
	 * Resolves the connected Stripe account's `created` Unix timestamp.
	 * Note: this is a best effort attempt. If it's a standard account, API response
	 * will not include `created` and this will return null. If it's a connected account, it should be present.
	 *
	 * @return int|null
	 */
	protected function get_account_created_ts(): ?int {
		$created = $this->get_account_data()['created'] ?? null;
		return is_int( $created ) ? $created : null;
	}

	/**
	 * Returns the cached Stripe account data, or an empty array when the account
	 * cannot be read (invalid/absent credentials). Wrapped so it can be overridden
	 * in tests.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_account_data(): array {
		$account = WC_Stripe::get_instance()->account->get_cached_account_data();
		return is_array( $account ) ? $account : [];
	}
}
