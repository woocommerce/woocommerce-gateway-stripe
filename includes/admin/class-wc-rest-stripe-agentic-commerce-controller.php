<?php
/**
 * Class WC_REST_Stripe_Agentic_Commerce_Controller
 *
 * REST API controller for the Agentic Commerce product feed dashboard.
 * Provides read access to sync status/history and a sync trigger endpoint.
 *
 * @package WooCommerce_Stripe
 * @since 10.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Agentic Commerce dashboard.
 *
 * @since 10.7.0
 */
class WC_REST_Stripe_Agentic_Commerce_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Option key for the sync lock.
	 *
	 * Uses a dedicated option (not a transient) so the lock is not silently
	 * dropped by object-cache flushes. The stored value is the lock acquisition
	 * timestamp; locks older than {@see self::SYNC_LOCK_TTL} are treated as stale.
	 *
	 * @var string
	 * @since 10.7.0
	 */
	private const SYNC_LOCK_OPTION = 'wc_stripe_agentic_sync_lock';

	/**
	 * Maximum age in seconds before a sync lock is considered stale.
	 *
	 * @var int
	 * @since 10.7.0
	 */
	private const SYNC_LOCK_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * ImportSet statuses that are non-terminal and should be re-polled.
	 *
	 * Stripe advances an ImportSet through `queued` → `validating` →
	 * `pending` → `creating_records` → one of the terminal states
	 * (`succeeded`, `succeeded_with_errors`, or `failed`). Entries in any
	 * non-terminal state get refreshed on dashboard load. `unknown` is also
	 * refreshed so rows persisted before the creation response included a
	 * status eventually resolve.
	 *
	 * @since 10.7.0
	 * @var string[]
	 */
	private const REFRESHABLE_STATUSES = [ 'queued', 'validating', 'pending', 'creating_records', 'unknown' ];

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/agentic-commerce';

	/**
	 * Placeholder returned by GET /settings when a webhook secret is stored.
	 *
	 * Mirrors Stripe's `whsec_` prefix so the field looks recognisable in the UI
	 * without exposing the stored value. The same value is detected on POST so
	 * saving without editing the field does not overwrite the stored secret.
	 *
	 * @var string
	 * @since 10.7.0
	 */
	const MASKED_WEBHOOK_SECRET = 'whsec_********************************';

	/**
	 * Configure REST API routes.
	 *
	 * Routes are only registered when the Agentic Commerce feature flag is on
	 * and the integration class is loaded, so no endpoints exist when the
	 * feature is disabled.
	 *
	 * @since 10.7.0
	 * @return void
	 */
	public function register_routes(): void {
		if ( ! $this->is_available() ) {
			return;
		}

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_agentic_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_agentic_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'is_enabled'     => [
							'description'       => __( 'Whether Agentic Commerce is enabled.', 'woocommerce-gateway-stripe' ),
							'type'              => 'boolean',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'webhook_secret' => [
							'description'       => __( 'Webhook signing secret for Agentic Commerce delegated checkout events.', 'woocommerce-gateway-stripe' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
			]
		);
	}

	/**
	 * Return current sync status, history, and next scheduled run.
	 *
	 * @since 10.7.0
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status() {
		if ( ! $this->is_available() ) {
			return $this->get_unavailable_error();
		}

		// Refresh any pending entries from Stripe before reading.
		$this->refresh_pending_sync_statuses();

		$last_sync   = WC_Stripe_Agentic_Commerce_Integration::get_last_sync();
		$history_raw = WC_Stripe_Agentic_Commerce_Integration::get_sync_history();

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
	 * @since 10.7.0
	 * @return WP_REST_Response|WP_Error
	 */
	public function trigger_sync() {
		if ( ! $this->is_available() ) {
			return $this->get_unavailable_error();
		}

		if ( ! $this->is_merchant_enabled() ) {
			return new WP_Error(
				'stripe_agentic_commerce_disabled',
				__( 'Agentic Commerce is disabled. Enable it in settings before triggering a sync.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 409 ]
			);
		}

		if ( ! $this->acquire_sync_lock() ) {
			return new WP_Error(
				'stripe_agentic_commerce_sync_locked',
				__( 'A sync is already in progress.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 409 ]
			);
		}

		try {
			$integration = new WC_Stripe_Agentic_Commerce_Integration();
			$success     = $integration->sync_feed();

			if ( ! $success ) {
				$last_sync = WC_Stripe_Agentic_Commerce_Integration::get_last_sync();
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
				/**
				 * Filter the recurring sync interval (in seconds) used when the
				 * next scheduled action is rebuilt after a manual sync.
				 *
				 * @since 10.7.0
				 * @param int $sync_interval Default sync interval in seconds.
				 */
				$sync_interval = apply_filters(
					'wc_stripe_agentic_commerce_feed_sync_interval',
					WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL
				);
				if ( ! is_int( $sync_interval ) || $sync_interval <= 0 ) {
					$sync_interval = WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL;
				}

				as_unschedule_action( WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
				as_schedule_recurring_action(
					time() + $sync_interval,
					$sync_interval,
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
			$this->release_sync_lock();
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Return the Agentic Commerce feature settings.
	 *
	 * @since 10.7.0
	 * @return WP_REST_Response
	 */
	public function get_agentic_settings(): WP_REST_Response {
		$secret = (string) get_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION, '' );
		return rest_ensure_response(
			[
				'is_enabled'     => 'yes' === get_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' ),
				// Never expose the real secret. Return a Stripe-style `whsec_`
				// prefixed mask when one is stored so the field looks familiar
				// without round-tripping the value to the client.
				'webhook_secret' => '' !== $secret ? self::MASKED_WEBHOOK_SECRET : '',
			]
		);
	}

	/**
	 * Update the Agentic Commerce feature settings.
	 *
	 * @since 10.7.0
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response
	 */
	public function update_agentic_settings( WP_REST_Request $request ): WP_REST_Response {
		if ( $request->has_param( 'is_enabled' ) ) {
			$value = $request->get_param( 'is_enabled' ) ? 'yes' : 'no';
			update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, $value );
		}

		if ( $request->has_param( 'webhook_secret' ) ) {
			$new_secret = $request->get_param( 'webhook_secret' );
			// Skip the update when the client echoes back the masked placeholder
			// returned by GET, so the stored secret is preserved when the user
			// saves without changing the field.
			if ( self::MASKED_WEBHOOK_SECRET !== $new_secret ) {
				update_option( WC_Stripe_Agentic_Commerce_Integration::WEBHOOK_SECRET_OPTION, sanitize_text_field( $new_secret ) );
			}
		}

		return $this->get_agentic_settings();
	}

	/**
	 * Refresh any non-terminal sync entries by polling Stripe for their current status.
	 *
	 * Called lazily when the status endpoint is read. Entries in any status
	 * listed in {@see self::REFRESHABLE_STATUSES} with a valid import_set_id
	 * are refreshed. The Stripe API round-trips run against an in-memory copy
	 * of the history; the resulting status updates are then applied through
	 * {@see WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses()},
	 * which re-reads the persisted history at write time so any entries
	 * appended concurrently by the scheduled sync or another manual trigger
	 * are not clobbered.
	 *
	 * @since 10.7.0
	 * @return void
	 */
	private function refresh_pending_sync_statuses(): void {
		$history = WC_Stripe_Agentic_Commerce_Integration::get_sync_history();

		if ( empty( $history ) ) {
			return;
		}

		$status_updates = [];
		$delivery       = null;

		foreach ( $history as $entry ) {
			$current_status = $entry['status'] ?? '';
			if ( ! in_array( $current_status, self::REFRESHABLE_STATUSES, true ) ) {
				continue;
			}

			$import_set_id = $entry['import_set_id'] ?? '';
			if ( '' === $import_set_id ) {
				continue;
			}

			try {
				$delivery   = $delivery ?? $this->create_delivery();
				$import_set = $delivery->get_import_set( $import_set_id );
				$new_status = $import_set['status'] ?? $current_status;

				if ( $new_status !== $current_status ) {
					$status_updates[ $import_set_id ] = $new_status;
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

		WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses( $status_updates );
	}

	/**
	 * Create a Files API delivery instance using the current Stripe settings.
	 *
	 * @since 10.7.0
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
	 * @since 10.7.0
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

	/**
	 * Whether the Agentic Commerce integration is available in this request.
	 *
	 * @since 10.7.0
	 * @return bool
	 */
	private function is_available(): bool {
		return class_exists( 'WC_Stripe_Feature_Flags' )
			&& WC_Stripe_Feature_Flags::is_agentic_commerce_enabled()
			&& class_exists( 'WC_Stripe_Agentic_Commerce_Integration' );
	}

	/**
	 * Whether the merchant has enabled Agentic Commerce via the settings UI.
	 *
	 * Distinct from {@see self::is_available()} which checks the developer
	 * feature flag and class availability. This check enforces the merchant-
	 * facing toggle on write paths so a stale admin tab or direct POST cannot
	 * push the catalog to Stripe after the merchant has disabled the feature.
	 *
	 * @since 10.7.0
	 * @return bool
	 */
	private function is_merchant_enabled(): bool {
		return 'yes' === get_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );
	}

	/**
	 * Build the standard "unavailable" error response.
	 *
	 * @since 10.7.0
	 * @return WP_Error
	 */
	private function get_unavailable_error(): WP_Error {
		return new WP_Error(
			'stripe_agentic_commerce_unavailable',
			__( 'Agentic Commerce integration is not available.', 'woocommerce-gateway-stripe' ),
			[ 'status' => 503 ]
		);
	}

	/**
	 * Attempt to acquire the sync lock.
	 *
	 * Uses a dedicated option (with `add_option()` for atomicity) rather than a
	 * transient so the lock survives object-cache flushes. A lock older than
	 * {@see self::SYNC_LOCK_TTL} is considered stale and overwritten.
	 *
	 * @since 10.7.0
	 * @return bool True if the caller holds the lock, false if another caller holds it.
	 */
	private function acquire_sync_lock(): bool {
		$now = time();

		// `add_option` returns false if the option already exists — atomic acquire.
		if ( add_option( self::SYNC_LOCK_OPTION, $now, '', false ) ) {
			return true;
		}

		$existing = (int) get_option( self::SYNC_LOCK_OPTION, 0 );
		if ( $existing > 0 && ( $now - $existing ) < self::SYNC_LOCK_TTL ) {
			return false;
		}

		// Stale lock — take it over.
		update_option( self::SYNC_LOCK_OPTION, $now, false );
		return true;
	}

	/**
	 * Release the sync lock.
	 *
	 * @since 10.7.0
	 * @return void
	 */
	private function release_sync_lock(): void {
		delete_option( self::SYNC_LOCK_OPTION );
	}
}
