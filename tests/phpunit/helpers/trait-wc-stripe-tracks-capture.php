<?php
/**
 * Captures the Tracks events recorded during a test.
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Lets a test assert on the Tracks events the plugin records. WC_Tracks::record_event() drops
 * every event in this suite (no tracking opt-in, and it refuses `wptests_`-prefixed users), so
 * this lifts both gates and captures the name and properties handed to WooCommerce.
 */
trait WC_Stripe_Tracks_Capture_Trait {

	/**
	 * Events recorded since start_capturing_tracks_events(), oldest first.
	 *
	 * @var array<int, array{name: string, properties: array}>
	 */
	private $captured_tracks_events = [];

	/**
	 * Lifts WooCommerce's tracking gates and starts recording events.
	 *
	 * Call after wp_set_current_user(): switching users replaces the WP_User object whose
	 * capability key is overridden here.
	 */
	private function start_capturing_tracks_events(): void {
		update_option( 'woocommerce_allow_tracking', 'yes' );
		wp_get_current_user()->cap_key = 'wp_capabilities';

		$this->captured_tracks_events = [];
		add_filter( 'woocommerce_tracks_event_properties', [ $this, 'capture_tracks_event' ], 10, 2 );
	}

	/**
	 * Stops recording events and restores the tracking opt-out.
	 */
	private function stop_capturing_tracks_events(): void {
		remove_filter( 'woocommerce_tracks_event_properties', [ $this, 'capture_tracks_event' ], 10 );
		delete_option( 'woocommerce_allow_tracking' );
	}

	/**
	 * Filter callback; public so WordPress can invoke it.
	 *
	 * @param array  $properties Event properties.
	 * @param string $event_name Prefixed event name.
	 * @return array
	 */
	public function capture_tracks_event( $properties, $event_name ) {
		$this->captured_tracks_events[] = [
			'name'       => $event_name,
			'properties' => $properties,
		];

		return $properties;
	}

	/**
	 * Returns the properties of every captured event with the given prefixed name, oldest first.
	 *
	 * @param string $event_name Prefixed event name, e.g. 'wcadmin_wcstripe_stripe_connected'.
	 * @return array[] One properties array per matching event; empty if none was recorded.
	 */
	private function get_captured_tracks_event_properties( string $event_name ): array {
		$matches = array_filter(
			$this->captured_tracks_events,
			function ( array $event ) use ( $event_name ) {
				return $event['name'] === $event_name;
			}
		);

		return array_values( array_column( $matches, 'properties' ) );
	}
}
