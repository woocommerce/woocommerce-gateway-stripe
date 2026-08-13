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
	private const MIGRATION_FLAG_OPTION = 'wc_stripe_restore_adaptive_pricing_after_amount_mismatch_migration_ran';

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

		$stripe          = WC_Stripe::get_instance();
		$stripe_settings = $stripe->get_settings();

		$stripe_settings['adaptive_pricing'] = 'yes';
		$stripe->update_settings( $stripe_settings );

		// Keep the safety marker in place unless the canonical settings read confirms the write.
		if ( 'yes' !== ( $stripe->get_settings()['adaptive_pricing'] ?? 'no' ) ) {
			return;
		}

		// A failed marker deletion must remain retryable on a later plugin update.
		if ( ! delete_option( self::AMOUNT_MISMATCH_OPTION ) ) {
			return;
		}

		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );

		WC_Stripe_Logger::info(
			'Adaptive Pricing re-enabled during plugin update after a previous automatic disable caused by a Checkout Session amount mismatch.',
			[ 'previous_version' => (string) $previous_version ]
		);
	}
}
