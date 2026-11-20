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
	 * Event properties WooCommerce reads from the current request in WC_Tracks::get_server_details().
	 *
	 * @var string[]
	 */
	private const REQUEST_DETAIL_PROPERTIES = [ '_via_ua', '_via_ip', '_lg', '_dr', '_dl' ];

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
			return self::get_default_event_properties();
		}

		return array_merge( self::get_default_event_properties(), $event_properties );
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
	 * Record a Tracks event, leaving out the request details when the current user is a shopper or
	 * logged-out visitor rather than someone who can manage WooCommerce.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name       The name of the event to record.
	 * @param array  $event_properties The event properties to record.
	 * @return void
	 */
	public static function record_direct_event_without_shopper_details( string $event_name, array $event_properties = [] ): void {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			self::record_direct_event( $event_name, $event_properties );
			return;
		}

		if ( ! self::is_tracking_enabled() ) {
			return;
		}

		if ( ! class_exists( 'WC_Tracks' ) || ! method_exists( WC_Tracks::class, 'get_properties' ) || ! class_exists( 'WC_Tracks_Event' ) ) { // @phpstan-ignore function.alreadyNarrowedType (Defensive check to make sure the function is available in WooCommerce.)
			return;
		}

		$properties = WC_Tracks::get_properties( WC_Tracks::PREFIX . $event_name, $event_properties );
		foreach ( self::REQUEST_DETAIL_PROPERTIES as $property ) {
			unset( $properties[ $property ] );
		}

		$event = new WC_Tracks_Event( $properties );
		if ( is_wp_error( $event->error ) ) {
			return;
		}

		$event->record();
	}

	/**
	 * Get the default event properties. Callers should generally call {@see add_default_event_properties()}.
	 *
	 * This method should only be called within loops to avoid unnecessary overhead.
	 *
	 * @since x.x.x
	 * @internal
	 *
	 * @return array The default event properties.
	 */
	public static function get_default_event_properties(): array {
		return [
			'is_test_mode'   => WC_Stripe_Mode::is_test() ? 1 : 0,
			'stripe_version' => WC_STRIPE_VERSION,
		];
	}
}
