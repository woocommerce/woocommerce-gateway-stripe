<?php

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the diagnostics endpoints.
 *
 * Routes:
 * - POST   /wc/v3/wc_stripe/diagnostics/events  (shopper-facing event ingest)
 * - GET    /wc/v3/wc_stripe/diagnostics/summary (admin: per-status counts)
 * - GET    /wc/v3/wc_stripe/diagnostics/traces  (admin: full trace JSON, filtered)
 * - DELETE /wc/v3/wc_stripe/diagnostics/traces  (admin: wipe stored traces)
 */
class WC_REST_Stripe_Diagnostics_Controller extends WP_REST_Controller {

	public const SETTINGS_OPTION = 'woocommerce_stripe_settings';
	public const SETTINGS_KEY    = 'diagnostics';

	public const CAPTURE_LIMIT_KEY     = 'diagnostics_capture_limit';
	public const DEFAULT_CAPTURE_LIMIT = 10;
	// Auto-off is mandatory; the REST enum and `capture_limit()` both rely on
	// this list being a finite set of positive integers.
	public const CAPTURE_LIMIT_PRESETS = [ 5, 10, 25, 50 ];

	protected $namespace = 'wc/v3';
	protected $rest_base = 'wc_stripe/diagnostics';

	/**
	 * Trace store dependency.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * Outcome promoter — keeps the trace's stored status field in sync as
	 * client events come in (createPaymentMethod errors, blocks setup
	 * failures, express cancels) so the merchant-facing "Copy failed
	 * traces" filter has the right answer to read from.
	 *
	 * @var WC_Stripe_Diagnostics_Outcome_Promoter
	 */
	private $promoter;

	/**
	 * Redactor used to scrub client-supplied event payloads before they
	 * land in the trace store. Without this, the un-trusted `data` blob
	 * a shopper's browser POSTs would flow into trace files that the
	 * merchant copies into support tickets.
	 *
	 * @var WC_Stripe_Diagnostics_Redactor
	 */
	private $redactor;

	public function __construct(
		?WC_Stripe_Diagnostics_Trace_Store $store = null,
		?WC_Stripe_Diagnostics_Outcome_Promoter $promoter = null,
		?WC_Stripe_Diagnostics_Redactor $redactor = null
	) {
		$this->store    = $store ?? new WC_Stripe_Diagnostics_Trace_Store();
		$this->promoter = $promoter ?? new WC_Stripe_Diagnostics_Outcome_Promoter( $this->store );
		$this->redactor = $redactor ?? new WC_Stripe_Diagnostics_Redactor();
	}

	/**
	 * Register this controller's routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/events',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ingest_events' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'diag_session_id' => [
						'required' => true,
						'type'     => 'string',
					],
					'events'          => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/traces',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_traces' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
					'args'                => [
						'status' => [
							'required' => false,
							'type'     => 'array',
							'items'    => [ 'type' => 'string' ],
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_traces' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Permission callback for the admin endpoints (summary, traces). The
	 * shopper-facing /events endpoint uses {@see self::permissions_check()}
	 * instead.
	 *
	 * Intentionally does NOT gate on the diagnostics-enabled toggle — a
	 * merchant who disables capture must still be able to copy or clear
	 * traces that were captured while the toggle was on.
	 *
	 * @return bool|WP_Error
	 */
	public function admin_permissions_check() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'wc_stripe_diagnostics_forbidden',
				'Insufficient permissions.',
				[ 'status' => rest_authorization_required_code() ]
			);
		}
		return true;
	}

	/**
	 * Return per-status counts for the admin breakdown row
	 * ("X traces (Y failed, Z abandoned, W succeeded, K pending)"). Always
	 * includes every status as a key — zero-counted buckets render alongside
	 * populated ones without conditional plumbing.
	 *
	 * @return WP_REST_Response
	 */
	public function get_summary() {
		$counts = $this->store->count_by_status();
		return new WP_REST_Response(
			[
				'counts' => $counts,
				'total'  => array_sum( $counts ),
			],
			200
		);
	}

	/**
	 * Return full trace JSON for the requested statuses. Powers the
	 * "Copy failed traces for support" button (status=failed,abandoned)
	 * and the "Copy all instead" link (no status filter).
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_traces( $request ) {
		$statuses = $request->get_param( 'status' );
		if ( ! is_array( $statuses ) || empty( $statuses ) ) {
			// "Copy all" path — no filter, every status included.
			$statuses = [
				WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING,
				WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED,
				WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED,
				WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED,
			];
		}
		$traces = $this->store->get_by_status( $statuses );
		return new WP_REST_Response(
			[
				'traces' => $traces,
				'count'  => count( $traces ),
			],
			200
		);
	}

	/**
	 * Wipe every stored trace. Powers the "Clear" action in the admin trace
	 * list. Does not gate on the diagnostics-enabled toggle: a merchant who
	 * disabled capture must still be able to clear out the bundle they
	 * captured while it was on.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_traces() {
		$deleted = $this->store->delete_all();
		return new WP_REST_Response(
			[
				'deleted' => $deleted,
				'total'   => 0,
			],
			200
		);
	}

	/**
	 * Permission callback.
	 *
	 * The endpoint is intentionally shopper-facing and unauthenticated.
	 * Abuse mitigation relies on the bounded debug window — the
	 * `is_enabled()` toggle is meant to be on only briefly while a
	 * merchant is actively debugging. Don't default it on: with the
	 * toggle on, anyone can rotate `diag_session_id` to evict
	 * legitimate traces from the FIFO trace store.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'wc_stripe_diagnostics_disabled', 'Diagnostics mode is not enabled.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Ingest a batch of events.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_events( $request ) {
		$session_id = WC_Stripe_Diagnostics_Trace_Store::sanitize_id( (string) $request->get_param( 'diag_session_id' ) );
		if ( '' === $session_id ) {
			return new WP_Error( 'wc_stripe_diagnostics_bad_session', 'Invalid diag_session_id.', [ 'status' => 400 ] );
		}

		$raw_events = $request->get_param( 'events' );
		if ( ! is_array( $raw_events ) ) {
			return new WP_Error( 'wc_stripe_diagnostics_bad_events', 'events must be an array.', [ 'status' => 400 ] );
		}
		if ( count( $raw_events ) > 200 ) {
			$raw_events = array_slice( $raw_events, 0, 200 );
		}

		// Lazily create the trace on the first event for this session. When
		// the store is at its trace cap, the store itself evicts the oldest
		// entry (FIFO) to make room.
		if ( null === $this->store->get( $session_id ) ) {
			$this->store->create( $session_id, [ 'source' => 'client' ] );
		}

		$written = 0;
		foreach ( $raw_events as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			// Scrub PII patterns (PAN/CVV/email/IP) from string values
			// before storing. We use scrub_value() rather than the full
			// redact() allow-list because the client SDK event shape
			// uses nested `data.*` fields the current allow-list doesn't
			// describe — full allow-list compliance is a follow-up.
			$scrubbed = $this->redactor->scrub_value( $raw );
			if ( ! is_array( $scrubbed ) ) {
				continue;
			}
			if ( $this->store->append_event( $session_id, $scrubbed ) ) {
				++$written;
				$this->promoter->maybe_promote( $session_id, $scrubbed );
			}
		}

		self::enforce_capture_limit( $this->store );

		return new WP_REST_Response( [ 'written' => $written ], 200 );
	}

	/**
	 * Whether the diagnostics feature is currently enabled.
	 *
	 * Reads from the gateway's settings group so the merchant-facing toggle
	 * (`is_diagnostics_enabled` in the settings REST controller) is the
	 * single source of truth.
	 */
	public static function is_enabled(): bool {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		return is_array( $settings ) && isset( $settings[ self::SETTINGS_KEY ] ) && 'yes' === $settings[ self::SETTINGS_KEY ];
	}

	/**
	 * Resolves to a preset; any drifted/legacy value (including 0) falls back
	 * to DEFAULT_CAPTURE_LIMIT so the auto-off can't be silently disabled.
	 */
	public static function capture_limit(): int {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) || ! array_key_exists( self::CAPTURE_LIMIT_KEY, $settings ) ) {
			return self::DEFAULT_CAPTURE_LIMIT;
		}
		$raw = $settings[ self::CAPTURE_LIMIT_KEY ];
		if ( ! is_numeric( $raw ) ) {
			return self::DEFAULT_CAPTURE_LIMIT;
		}
		$value = (int) $raw;
		if ( ! in_array( $value, self::CAPTURE_LIMIT_PRESETS, true ) ) {
			return self::DEFAULT_CAPTURE_LIMIT;
		}
		return $value;
	}

	/**
	 * Silently flips the diagnostics toggle to 'no' when the stored trace
	 * count reaches the capture limit. Returns true when this call flipped.
	 */
	public static function enforce_capture_limit( ?WC_Stripe_Diagnostics_Trace_Store $store = null ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}
		$limit = self::capture_limit();
		$store = $store ?? new WC_Stripe_Diagnostics_Trace_Store();
		// Note: parallel ingests can each pass this check and land a trace
		// before any of them flips the toggle, this is meant to be a soft limit
		if ( $store->count() < $limit ) {
			return false;
		}

		WC_Stripe::get_instance()->get_main_stripe_gateway()->update_option( self::SETTINGS_KEY, 'no' );

		if ( function_exists( 'wc_admin_record_tracks_event' ) ) {
			wc_admin_record_tracks_event(
				'wcstripe_diagnostics_auto_off',
				[
					'limit'     => $limit,
					'test_mode' => class_exists( 'WC_Stripe_Mode' ) && WC_Stripe_Mode::is_test() ? 1 : 0,
				]
			);
		}

		return true;
	}
}
