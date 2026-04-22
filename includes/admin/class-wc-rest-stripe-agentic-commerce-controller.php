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
 * @since 10.7.0
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
			'/' . $this->rest_base . '/status',
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
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status() {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			return new WP_Error(
				'stripe_agentic_commerce_unavailable',
				__( 'Agentic Commerce integration is not available.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 503 ]
			);
		}

		// Refresh any pending entries from Stripe before reading.
		$this->refresh_pending_sync_statuses();

		$last_sync   = get_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );
		$history_raw = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		if ( ! is_array( $last_sync ) ) {
			$last_sync = [];
		}
		if ( ! is_array( $history_raw ) ) {
			$history_raw = [];
		}

		// Filter out any non-array entries that may have been stored by corrupted data.
		$history_raw = array_filter( $history_raw, 'is_array' );

		// Return the 20 most recent history entries, newest first.
		$history = array_map(
			[ $this, 'format_entry' ],
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
				'last_sync' => empty( $last_sync ) ? null : $this->format_entry( $last_sync ),
				'history'   => $history,
				'next_sync' => $next_sync,
			]
		);
	}

	/**
	 * Trigger a manual product feed sync.
	 *
	 * On success, the next scheduled recurring sync is rescheduled from the
	 * current time so the manual sync resets the automatic sync window.
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

		// Prevent concurrent sync runs using a transient-based lock.
		if ( get_transient( 'wc_stripe_agentic_sync_lock' ) ) {
			return new WP_Error(
				'stripe_agentic_commerce_sync_locked',
				__( 'A sync is already in progress.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 409 ]
			);
		}

		set_transient( 'wc_stripe_agentic_sync_lock', true, 5 * MINUTE_IN_SECONDS );

		try {
			$integration = new WC_Stripe_Agentic_Commerce_Integration();
			$success     = $integration->sync_feed();

			if ( ! $success ) {
				$last_sync = get_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );
				$message   = ! empty( $last_sync['error'] )
					? $last_sync['error']
					: __( 'Sync did not complete successfully.', 'woocommerce-gateway-stripe' );

				return new WP_Error(
					'stripe_agentic_commerce_sync_failed',
					$message,
					[ 'status' => 500 ]
				);
			}

			// Reset the automatic sync window so the next scheduled run starts
			// from now, rather than running again shortly after a manual sync.
			if ( function_exists( 'as_unschedule_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
				as_unschedule_action( WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
				as_schedule_recurring_action(
					time() + WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL,
					WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL,
					WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
					[],
					'wc-stripe'
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'stripe_agentic_commerce_sync_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		} finally {
			delete_transient( 'wc_stripe_agentic_sync_lock' );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Refresh any pending sync entries by polling Stripe for their current status.
	 *
	 * Called lazily when the status endpoint is read. Only entries with a
	 * "pending" status and a valid import_set_id are refreshed.
	 *
	 * @since 10.6.0
	 * @return void
	 */
	private function refresh_pending_sync_statuses(): void {
		$history = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		if ( ! is_array( $history ) ) {
			return;
		}

		$updated = false;

		foreach ( $history as &$entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( 'pending' !== ( $entry['status'] ?? '' ) ) {
				continue;
			}

			$import_set_id = $entry['import_set_id'] ?? '';
			if ( '' === $import_set_id ) {
				continue;
			}

			try {
				$delivery   = $this->create_delivery();
				$import_set = $delivery->get_import_set( $import_set_id );
				$new_status = $import_set['status'] ?? 'pending';

				if ( 'pending' !== $new_status ) {
					$entry['status'] = $new_status;
					$updated         = true;
				}
			} catch ( Exception $e ) {
				WC_Stripe_Logger::error(
					'Agentic Commerce: Failed to refresh ImportSet status',
					[
						'import_set_id' => $import_set_id,
						'error'         => $e->getMessage(),
					]
				);
			}
		}
		unset( $entry );

		if ( $updated ) {
			update_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history, false );

			// Also update the last_sync snapshot if it was pending.
			$last = end( $history );
			if ( is_array( $last ) ) {
				update_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, $last, false );
			}
		}
	}

	/**
	 * Create a Files API delivery instance using the current Stripe settings.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_Agentic_Commerce_Files_Api_Delivery
	 */
	private function create_delivery(): WC_Stripe_Agentic_Commerce_Files_Api_Delivery {
		$settings  = WC_Stripe_Helper::get_stripe_settings();
		$test_mode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];
		$secret    = $test_mode
			? ( $settings['test_secret_key'] ?? '' )
			: ( $settings['secret_key'] ?? '' );

		return new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( $secret );
	}

	/**
	 * Normalise a raw sync option array into a consistent shape for the API response.
	 *
	 * Used for both the last_sync snapshot and individual history entries.
	 *
	 * @since 10.6.0
	 * @param array $entry Raw entry from options table.
	 * @return array
	 */
	private function format_entry( array $entry ): array {
		return [
			'status'        => $entry['status'] ?? 'unknown',
			'timestamp'     => isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : null,
			'products'      => isset( $entry['products'] ) ? (int) $entry['products'] : null,
			'import_set_id' => $entry['import_set_id'] ?? null,
			'file_id'       => $entry['file_id'] ?? null,
			'error'         => $entry['error'] ?? null,
		];
	}
}
