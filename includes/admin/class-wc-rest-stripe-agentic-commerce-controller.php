<?php
/**
 * Class WC_REST_Stripe_Agentic_Commerce_Controller
 *
 * REST API controller for the Agentic Commerce product feed dashboard.
 * Provides read access to sync status/history, a sync trigger endpoint,
 * merchant onboarding settings, and an account session creator.
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
	 * Option key for merchant onboarding settings.
	 *
	 * @var string
	 */
	const MERCHANT_SETTINGS_OPTION = 'wc_stripe_agentic_merchant_settings';

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/merchant-settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_merchant_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_merchant_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_merchant_settings_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/account-session',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_account_session' ],
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
		$last_sync   = get_option( WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );
		$history_raw = get_option( WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

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
				'last_sync' => empty( $last_sync ) ? null : $this->format_sync_entry( $last_sync ),
				'history'   => $history,
				'next_sync' => $next_sync,
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
	 * Return the stored merchant onboarding settings.
	 * The hook_secret field is masked for display.
	 *
	 * @since 10.6.0
	 * @return WP_REST_Response
	 */
	public function get_merchant_settings(): WP_REST_Response {
		$settings = get_option( self::MERCHANT_SETTINGS_OPTION, [] );

		return rest_ensure_response( $this->format_merchant_settings( $settings ) );
	}

	/**
	 * Save merchant onboarding settings.
	 *
	 * @since 10.6.0
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function save_merchant_settings( WP_REST_Request $request ): WP_REST_Response {
		$existing = get_option( self::MERCHANT_SETTINGS_OPTION, [] );

		$updatable_fields = [
			'terms_of_service_url',
			'privacy_policy_url',
			'refund_policy_url',
			'hook_url',
		];

		$updated = array_merge( $existing, array_filter( array_intersect_key( $request->get_params(), array_flip( $updatable_fields ) ), 'is_string' ) );

		// Only overwrite the secret if a non-empty value is explicitly provided.
		$raw_secret = $request->get_param( 'hook_secret' );
		if ( is_string( $raw_secret ) && '' !== $raw_secret ) {
			$updated['hook_secret'] = $raw_secret;
		}

		update_option( self::MERCHANT_SETTINGS_OPTION, $updated );

		return rest_ensure_response( $this->format_merchant_settings( $updated ) );
	}

	/**
	 * Create a Stripe account session for the Agentic Commerce component.
	 *
	 * Wraps POST /v1/account_sessions with the agentic_commerce component.
	 *
	 * @since 10.6.0
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_account_session() {
		$account = WC_Stripe_API::retrieve( 'account' );

		if ( is_wp_error( $account ) ) {
			return $account;
		}

		if ( is_null( $account ) || ! is_object( $account ) ) {
			return new WP_Error(
				'stripe_account_unavailable',
				__( 'Unable to retrieve Stripe account. Please check your API keys.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 503 ]
			);
		}

		$account_id = $account->id ?? null;
		if ( empty( $account_id ) ) {
			return new WP_Error(
				'stripe_account_id_missing',
				__( 'Stripe account ID could not be determined.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 503 ]
			);
		}

		$response = WC_Stripe_API::request(
			[
				'account'    => $account_id,
				'components' => [
					'agentic_commerce_settings' => [ 'enabled' => true ],
				],
			],
			'account_sessions'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$client_secret = is_object( $response ) ? ( $response->client_secret ?? null ) : null;

		if ( empty( $client_secret ) ) {
			return new WP_Error(
				'stripe_session_failed',
				__( 'Failed to create an account session.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 502 ]
			);
		}

		return rest_ensure_response( [ 'client_secret' => $client_secret ] );
	}

	/**
	 * Argument schema for the save_merchant_settings endpoint.
	 *
	 * @since 10.6.0
	 * @return array
	 */
	private function get_merchant_settings_args(): array {
		$url_schema = [
			'type'              => 'string',
			'format'            => 'uri',
			'sanitize_callback' => 'esc_url_raw',
			'required'          => false,
		];

		return [
			'terms_of_service_url' => $url_schema,
			'privacy_policy_url'   => $url_schema,
			'refund_policy_url'    => $url_schema,
			'hook_url'             => $url_schema,
			'hook_secret'          => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => false,
			],
		];
	}

	/**
	 * Format stored merchant settings for API responses.
	 * The hook_secret is masked so it is never returned in full.
	 *
	 * @since 10.6.0
	 * @param array $settings Raw stored settings.
	 * @return array
	 */
	private function format_merchant_settings( array $settings ): array {
		$raw_secret = $settings['hook_secret'] ?? '';
		$secret_set = '' !== $raw_secret;

		return [
			'terms_of_service_url' => $settings['terms_of_service_url'] ?? '',
			'privacy_policy_url'   => $settings['privacy_policy_url'] ?? '',
			'refund_policy_url'    => $settings['refund_policy_url'] ?? '',
			'hook_url'             => $settings['hook_url'] ?? '',
			// Never expose the real secret — return a placeholder when set.
			'hook_secret'          => $secret_set ? '••••••••' : '',
			'hook_secret_is_set'   => $secret_set,
		];
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
