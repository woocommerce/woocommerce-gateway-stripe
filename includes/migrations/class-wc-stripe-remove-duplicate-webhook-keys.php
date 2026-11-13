<?php

defined( 'ABSPATH' ) || exit;

/**
 * Keeps legacy webhook credentials when they may be needed to delete an old endpoint.
 *
 * @since x.x.x
 * @version x.x.x
 */
class WC_Stripe_Remove_Duplicate_Webhook_Keys {
	private const MIGRATION_OPTION = 'wc_stripe_removed_duplicate_webhook_keys';

	/**
	 * Runs once so a rollback cannot cause a later upgrade to remove restored legacy credentials.
	 *
	 * @since x.x.x
	 * @version x.x.x
	 * @return void
	 */
	public function maybe_migrate(): void {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$settings     = WC_Stripe::get_instance()->get_settings();
		$updated      = $settings;
		$removed_keys = [];
		foreach ( [ '', 'test_' ] as $prefix ) {
			$data = $settings[ $prefix . 'webhook_data' ] ?? null;
			if ( ! is_array( $data ) || ! isset( $data['secret'] ) ) {
				continue;
			}

			// A different legacy key may be the only credential that can delete the old endpoint.
			if ( ( $settings[ $prefix . 'secret_key' ] ?? '' ) === $data['secret'] ) {
				unset( $updated[ $prefix . 'webhook_data' ]['secret'] );
				$removed_keys[] = $prefix . 'webhook_data';
			}
		}

		if ( $removed_keys ) {
			// A concurrent key change may have replaced the webhook after these settings were read.
			if ( WC_Stripe::get_instance()->get_settings() !== $settings ) {
				return;
			}

			WC_Stripe::get_instance()->update_settings( $updated );
			$stored = WC_Stripe::get_instance()->get_settings();
			foreach ( $removed_keys as $key ) {
				$data = $stored[ $key ] ?? [];
				if ( ! is_array( $data ) || array_key_exists( 'secret', $data ) ) {
					WC_Stripe_Logger::error( 'Could not remove duplicate account API keys from webhook settings.' );
					return;
				}
			}
		}

		update_option( self::MIGRATION_OPTION, 'yes' );
	}
}
