<?php

defined( 'ABSPATH' ) || exit;

class WC_Stripe_Remove_Duplicate_Webhook_Keys {
	private const MIGRATION_OPTION = 'wc_stripe_removed_duplicate_webhook_keys';

	public function maybe_migrate(): void {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$settings = WC_Stripe::get_instance()->get_settings();
		$updated  = $settings;
		foreach ( [ '', 'test_' ] as $prefix ) {
			$data = $settings[ $prefix . 'webhook_data' ] ?? null;
			if ( ! is_array( $data ) || ! isset( $data['secret'] ) ) {
				continue;
			}

			// A different legacy key may be the only credential that can delete the old endpoint.
			if ( ( $settings[ $prefix . 'secret_key' ] ?? '' ) === $data['secret'] ) {
				unset( $updated[ $prefix . 'webhook_data' ]['secret'] );
			}
		}

		if ( $updated !== $settings ) {
			WC_Stripe::get_instance()->update_settings( $updated );
			if ( WC_Stripe::get_instance()->get_settings() !== $updated ) {
				return;
			}
		}

		update_option( self::MIGRATION_OPTION, 'yes' );
	}
}
