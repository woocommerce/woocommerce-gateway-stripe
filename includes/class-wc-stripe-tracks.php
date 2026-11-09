<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks helper class for WooCommerce Stripe.
 *
 * @since x.x.x
 */
final class WC_Stripe_Tracks {
	/**
	 * Whether site tracking is available and enabled.
	 *
	 * @since x.x.x
	 *
	 * @return bool Whether site tracking is available and enabled.
	 */
	public static function is_tracking_enabled(): bool {
		return function_exists( 'wc_admin_record_tracks_event' )
			&& class_exists( 'WC_Site_Tracking' )
			&& method_exists( WC_Site_Tracking::class, 'is_tracking_enabled' ) // @phpstan-ignore function.alreadyNarrowedType (Defensive check to make sure the function is available in WooCommerce.)
			&& WC_Site_Tracking::is_tracking_enabled();
	}

	/**
	 * Add the default event properties to the supplied event properties array.
	 * Note that the supplied properties take precedence over the default properties.
	 *
	 * @since x.x.x
	 *
	 * @param array $event_properties The event properties to add the default properties to.
	 * @return array The combined set of event properties.
	 */
	public static function add_default_event_properties( array $event_properties = [] ): array {
		if ( [] === $event_properties ) {
			return self::get_default_properties();
		}

		return array_merge( self::get_default_properties(), $event_properties );
	}

	/**
	 * Record a Tracks event by calling WC_Tracks::record_event() directly.
	 * This should be used when a redirect may occur in the current request.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name       The name of the event to record.
	 * @param array  $event_properties The event properties to record.
	 * @return void
	 */
	public static function record_direct_event( string $event_name, array $event_properties = [] ): void {
		if ( ! self::is_tracking_enabled() ) {
			return;
		}

		if ( ! class_exists( 'WC_Tracks' ) || ! method_exists( WC_Tracks::class, 'record_event' ) ) { // @phpstan-ignore function.alreadyNarrowedType (Defensive check to make sure the function is available in WooCommerce.)
			return;
		}

		WC_Tracks::record_event( $event_name, $event_properties );
	}

	/**
	 * Get the default event properties.
	 *
	 * @since x.x.x
	 *
	 * @return array The default event properties.
	 */
	private static function get_default_properties(): array {
		return [
			'is_test_mode'   => WC_Stripe_Mode::is_test(),
			'stripe_version' => WC_STRIPE_VERSION,
		];
	}
}
