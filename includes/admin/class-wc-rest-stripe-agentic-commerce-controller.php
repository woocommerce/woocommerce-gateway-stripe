<?php
/**
 * Class WC_REST_Stripe_Agentic_Commerce_Controller
 *
 * REST API controller for the Agentic Commerce product feed dashboard.
 * Provides read access to sync status/history and a sync trigger endpoint.
 *
 * @package WooCommerce_Stripe
 * @since 10.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Agentic Commerce dashboard.
 *
 * @since 10.6.0
 */
class WC_REST_Stripe_Agentic_Commerce_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/agentic-commerce';

	/**
	 * Configure REST API routes.
	 *
	 * @since 10.6.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sync',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'trigger_sync' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Return current sync status, history, and next scheduled run.
	 *
	 * @since 10.6.0
	 * @return WP_REST_Response
	 */
	public function get_status(): WP_REST_Response {
		$last_sync   = get_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::LAST_SYNC_OPTION, [] );
		$history_raw = get_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::SYNC_HISTORY_OPTION, [] );

		// Return the 20 most recent history entries, newest first.
		$history = array_map(
			[ $this, 'format_history_entry' ],
			array_reverse( array_slice( $history_raw, -20 ) )
		);

		$next_sync = null;
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$timestamp = as_next_scheduled_action( WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );
			if ( $timestamp ) {
				$next_sync = (int) $timestamp;
			}
		}

		return rest_ensure_response(
			[
				'last_sync'  => empty( $last_sync ) ? null : $this->format_sync_entry( $last_sync ),
				'history'    => $history,
				'next_sync'  => $next_sync,
			]
		);
	}

	/**
	 * Trigger a manual product feed sync.
	 *
	 * @since 10.6.0
	 * @return WP_REST_Response|WP_Error
	 */
	public function trigger_sync() {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			return new WP_Error(
				'stripe_agentic_commerce_unavailable',
				__( 'Agentic Commerce integration is not available.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 503 ]
			);
		}

		$integration = new WC_Stripe_Agentic_Commerce_Integration();
		$integration->sync_feed();

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Normalise a raw sync option array into a consistent shape for the API response.
	 *
	 * @since 10.6.0
	 * @param array $entry Raw entry from options table.
	 * @return array
	 */
	private function format_sync_entry( array $entry ): array {
		return [
			'status'        => $entry['status'] ?? 'unknown',
			'timestamp'     => isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : null,
			'products'      => isset( $entry['products'] ) ? (int) $entry['products'] : null,
			'import_set_id' => $entry['import_set_id'] ?? null,
			'file_id'       => $entry['file_id'] ?? null,
			'error'         => $entry['error'] ?? null,
		];
	}

	/**
	 * Normalise a history entry (subset of fields used by the table).
	 *
	 * @since 10.6.0
	 * @param array $entry Raw history entry.
	 * @return array
	 */
	private function format_history_entry( array $entry ): array {
		return [
			'status'        => $entry['status'] ?? 'unknown',
			'timestamp'     => isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : null,
			'products'      => isset( $entry['products'] ) ? (int) $entry['products'] : null,
			'import_set_id' => $entry['import_set_id'] ?? null,
			'error'         => $entry['error'] ?? null,
		];
	}
}
