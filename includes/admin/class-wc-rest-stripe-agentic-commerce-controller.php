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
	 * Option key for the Agentic Commerce webhook secret.
	 *
	 * @var string
	 */
	const WEBHOOK_SECRET_OPTION = 'wc_stripe_agentic_commerce_webhook_secret';

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

		try {
			$integration = new WC_Stripe_Agentic_Commerce_Integration();
			$integration->sync_feed();

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
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Return the Agentic Commerce feature settings.
	 *
	 * @since 10.6.0
	 * @return WP_REST_Response
	 */
	public function get_agentic_settings(): WP_REST_Response {
		$secret = (string) get_option( self::WEBHOOK_SECRET_OPTION, '' );
		return rest_ensure_response(
			[
				'is_enabled'     => 'yes' === get_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' ),
				// Never expose the real secret. Return '****' when one is stored so
				// the frontend knows a secret exists without round-tripping the value.
				'webhook_secret' => '' !== $secret ? '****' : '',
			]
		);
	}

	/**
	 * Update the Agentic Commerce feature settings.
	 *
	 * @since 10.6.0
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
			// '****' is the masked placeholder returned by GET; skip the update so
			// the stored secret is not overwritten when the user saves without
			// changing the field.
			if ( '****' !== $new_secret ) {
				update_option( self::WEBHOOK_SECRET_OPTION, sanitize_text_field( $new_secret ) );
			}
		}

		return $this->get_agentic_settings();
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
