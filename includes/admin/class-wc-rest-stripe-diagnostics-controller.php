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

	const SETTINGS_OPTION = 'woocommerce_stripe_settings';
	const SETTINGS_KEY    = 'diagnostics';

	const CAPTURE_LIMIT_KEY     = 'diagnostics_capture_limit';
	const DEFAULT_CAPTURE_LIMIT = 10;
	// Auto-off is mandatory; the REST enum and `capture_limit()` both rely on
	// this list being a finite set of positive integers.
	const CAPTURE_LIMIT_PRESETS = [ 5, 10, 25, 50 ];

	// EXPLORATION (RSM-1638): per-IP rate-limit window for the unauthenticated
	// /events endpoint. A single token over N seconds — enough to bound disk
	// I/O and FIFO-eviction cost against a basic flooder. Filterable so we
	// can tune without a release. Default is 2s rather than 1s so wallet flows
	// (Apple Pay / Google Pay can emit a quick burst) and shoppers behind a
	// shared NAT egress don't 429 each other on legitimate double-taps.
	const EVENTS_RATE_LIMIT_KEY_PREFIX  = 'stripe_diag_events_';
	const DEFAULT_EVENTS_RATE_LIMIT_SEC = 2;

	// Per-store 429 counter surfaced in the admin summary so operators can see
	// whether the limit is biting on this store without needing fleet Tracks
	// data. Transient (24h TTL) — decays naturally so a single old spike doesn't
	// linger forever.
	const RATE_LIMITED_COUNT_TRANSIENT = 'wc_stripe_diag_rate_limited_count_24h';
	const RATE_LIMITED_COUNT_TTL       = DAY_IN_SECONDS;

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
				'counts'             => $counts,
				'total'              => array_sum( $counts ),
				'rate_limited_count' => self::get_rate_limited_count(),
			],
			200
		);
	}

	/**
	 * Read the rolling 24-hour 429 count. Returns 0 when no requests have been
	 * rate-limited recently, which is the common case. Surfaced via
	 * {@see self::get_summary()} and used by the admin UI to indicate whether
	 * the /events rate limit has been tripping for this store.
	 */
	public static function get_rate_limited_count(): int {
		$value = get_transient( self::RATE_LIMITED_COUNT_TRANSIENT );
		return is_numeric( $value ) ? (int) $value : 0;
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
		if ( self::is_rate_limited() ) {
			self::record_rate_limited_event();
			return new WP_Error( 'wc_stripe_diagnostics_rate_limited', 'Too many requests.', [ 'status' => 429 ] );
		}
		return true;
	}

	/**
	 * EXPLORATION (RSM-1638): emit a Tracks event when the /events rate limit
	 * trips. Parallels the `wcstripe_diagnostics_auto_off` event so operators
	 * have visibility into whether the limit is biting in the wild — useful
	 * for tuning the default window via the
	 * `wc_stripe_diagnostics_events_rate_limit` filter.
	 *
	 * Deliberately omits any IP-derived field; the gate is meant to bound
	 * abuse, not to identify the actor.
	 */
	private static function record_rate_limited_event(): void {
		self::increment_rate_limited_count();

		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}
		wc_admin_record_tracks_event(
			'wcstripe_diagnostics_rate_limited',
			[
				'test_mode' => class_exists( 'WC_Stripe_Mode' ) && WC_Stripe_Mode::is_test() ? 1 : 0,
			]
		);
	}

	/**
	 * Bump the 24h 429 counter that {@see self::get_summary()} surfaces.
	 *
	 * Transient rather than option so the count fades out 24h after the
	 * last hit — operators see "recent abuse" rather than a lifetime
	 * total that gives no signal about the current state of the store.
	 *
	 * The read-modify-write race window is acceptable: a missed increment
	 * under contention only undercounts the surface; the gate itself is
	 * still firing and the Tracks event still emits.
	 */
	private static function increment_rate_limited_count(): void {
		$current = get_transient( self::RATE_LIMITED_COUNT_TRANSIENT );
		$next    = ( is_numeric( $current ) ? (int) $current : 0 ) + 1;
		set_transient( self::RATE_LIMITED_COUNT_TRANSIENT, $next, self::RATE_LIMITED_COUNT_TTL );
	}

	/**
	 * EXPLORATION (RSM-1638): per-IP rate-limit gate for the unauthenticated
	 * /events endpoint.
	 *
	 * The endpoint stays unauthenticated by design (guest shoppers need to
	 * post traces), and `is_enabled()` is the only existing gate. An
	 * attacker can spam the endpoint while diagnostics is on — burning disk
	 * I/O, padding traces to the 200-event cap, and (if `capture_limit` is
	 * high) evicting legitimate sessions from the FIFO trace store. A
	 * per-IP delay window bounds the attack rate without locking out
	 * legitimate shoppers.
	 *
	 * Caveats worth weighing in review:
	 *   - Per-IP keys collide for shoppers behind the same NAT/proxy; the
	 *     current 1s window is loose enough that this should be tolerable
	 *     for the bounded debug session this feature targets, but it is
	 *     an inherent trade-off.
	 *   - `WC_Rate_Limiter::set_rate_limit()` writes to the
	 *     `wc_rate_limits` table on every request; expensive at scale but
	 *     scoped to the diagnostics-on window.
	 *   - The IP is hashed (`wp_hash`) so the rate-limit table never holds
	 *     a raw client address.
	 */
	private static function is_rate_limited(): bool {
		if ( ! class_exists( 'WC_Geolocation' ) || ! class_exists( 'WC_Rate_Limiter' ) ) {
			return false;
		}
		$ip = WC_Geolocation::get_ip_address();
		if ( '' === $ip ) {
			// No IP signal (CLI/cron path or a hosting setup we can't read);
			// don't block — the diagnostics-enabled gate upstream still applies.
			return false;
		}
		$key = self::EVENTS_RATE_LIMIT_KEY_PREFIX . substr( wp_hash( $ip ), 0, 16 );
		return WC_Rate_Limiter::retried_too_soon( $key );
	}

	/**
	 * Counterpart to {@see self::is_rate_limited()} — extends the per-IP
	 * window after a request lands. Kept private and called from
	 * `ingest_events()` so the gate trips on the next request rather than
	 * the current one.
	 */
	private static function arm_rate_limit(): void {
		if ( ! class_exists( 'WC_Geolocation' ) || ! class_exists( 'WC_Rate_Limiter' ) ) {
			return;
		}
		$ip = WC_Geolocation::get_ip_address();
		if ( '' === $ip ) {
			return;
		}
		/**
		 * Filter the per-IP rate-limit window, in seconds, for the
		 * shopper-facing diagnostics `/events` endpoint.
		 *
		 * Defaults to 2s. A single accepted POST opens a window of this
		 * length for the requester's IP; subsequent posts inside the
		 * window return HTTP 429 and increment the admin-visible
		 * `rate_limited_count` returned by `/diagnostics/summary`. The
		 * frontend recorder reads the same value (in ms) via
		 * `wcStripeDiag.rateLimitMs` and coalesces its own flushes so a
		 * single shopper does not burn 429s on the server.
		 *
		 * Set to 0 (or any non-positive value) to disable the gate
		 * entirely. Raise it for high-NAT environments where legitimate
		 * shoppers share an egress IP and trip the limit on wallet
		 * bursts.
		 *
		 * @since 10.8.0
		 * @param int $delay Window in seconds.
		 */
		$delay = (int) apply_filters( 'wc_stripe_diagnostics_events_rate_limit', self::DEFAULT_EVENTS_RATE_LIMIT_SEC );
		if ( $delay <= 0 ) {
			return;
		}
		$key = self::EVENTS_RATE_LIMIT_KEY_PREFIX . substr( wp_hash( $ip ), 0, 16 );
		WC_Rate_Limiter::set_rate_limit( $key, $delay );
	}

	/**
	 * Ingest a batch of events.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_events( $request ) {
		// EXPLORATION (RSM-1638): arm the next-request rate-limit window for
		// this IP as soon as we accept a request, regardless of whether the
		// payload is valid. A malformed-events flooder still costs disk and
		// CPU, so the window has to start before we bail on validation.
		self::arm_rate_limit();

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
