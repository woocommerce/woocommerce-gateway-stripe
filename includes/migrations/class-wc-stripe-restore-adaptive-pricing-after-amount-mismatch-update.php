<?php
/**
 * Class WC_Stripe_Restore_Adaptive_Pricing_After_Amount_Mismatch_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restore Adaptive Pricing for stores affected by the amount-mismatch safety disable.
 *
 * @since 10.9.0
 */
class WC_Stripe_Restore_Adaptive_Pricing_After_Amount_Mismatch_Update {

	/**
	 * Option flag used to ensure the migration only runs once.
	 */
	private const MIGRATION_FLAG_OPTION = 'wc_stripe_restored_adaptive_pricing_after_amount_mismatch';

	/**
	 * This marker is the only reliable distinction between an automatic safety disable and a merchant's setting choice.
	 */
	private const AMOUNT_MISMATCH_OPTION = 'wc_stripe_adaptive_pricing_session_amount_mismatch_detected';

	/**
	 * Restore Adaptive Pricing only when the safety marker proves it was not disabled manually.
	 *
	 * @param string|false $previous_version The plugin version recorded before this upgrade, or false on a new install.
	 * @return void
	 */
	public function maybe_migrate( $previous_version = false ): void {
		if ( 'yes' === get_option( self::MIGRATION_FLAG_OPTION ) ) {
			return;
		}

		if ( false === $previous_version ) {
			update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
			return;
		}

		if ( 'yes' !== get_option( self::AMOUNT_MISMATCH_OPTION, 'no' ) ) {
			update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
			return;
		}

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		$stripe_settings['adaptive_pricing'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Re-read persisted settings. Leave the migration flag unset if the update did not take effect so a later update can retry.
		if ( 'yes' !== ( WC_Stripe_Helper::get_stripe_settings()['adaptive_pricing'] ?? 'no' ) ) {
			return;
		}

		// A failed marker deletion must remain retryable on a later plugin update.
		if ( ! delete_option( self::AMOUNT_MISMATCH_OPTION ) ) {
			// If the option still exists, then return early.
			if ( false !== get_option( self::AMOUNT_MISMATCH_OPTION, false ) ) {
				return;
			}
			// Otherwise the option no longer exists, mark the migration as complete.
			return;
		}

		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );

		WC_Stripe_Logger::error(
			'Adaptive Pricing re-enabled during plugin update after a previous automatic disable caused by a Checkout Session amount mismatch.',
			[ 'previous_version' => (string) $previous_version ]
		);
	}
}
