<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules and runs the Stripe remote-config sync.
 *
 * - Daily Action Scheduler job (`wc_stripe_remote_config_sync`).
 * - Immediate single-action enqueue on plugin upgrade
 * - Immediate single-action enqueue when API keys or the test/live mode change
 * - In-cycle retries with backoff after a failed fetch (see RETRY_DELAYS)
 *
 * On each run (skipped entirely when no mode has keys), makes one combined
 * Client::fetch_all call and applies each mode's payload.
 */
class WC_Stripe_Remote_Config_Scheduler {

	public const SYNC_ACTION     = 'wc_stripe_remote_config_sync';
	public const SCHEDULER_GROUP = 'woocommerce-gateway-stripe';

	/**
	 * Option tracking consecutive failed fetches. Reset on the first
	 * successful fetch; never autoloaded.
	 */
	public const FAILURE_COUNT_OPTION = '_wcstripe_remote_config_sync_failures';

	/**
	 * Delays (seconds) before each in-cycle retry after a failed fetch: a run
	 * at attempt N that fails schedules attempt N+1 after RETRY_DELAYS[N].
	 * Past the end of the list, the sync waits for the next daily run, so a
	 * fully failed cycle contacts the endpoint three times per day.
	 */
	protected const RETRY_DELAYS = [ HOUR_IN_SECONDS, 4 * HOUR_IN_SECONDS ];

	/**
	 * Cache age (seconds) past which a failed fetch logs at warning level:
	 * remote flag changes have not reached this site for this long.
	 */
	protected const STALENESS_WARNING_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Outbound HTTP client used to fetch remote-config payloads.
	 *
	 * @var WC_Stripe_Remote_Config_Client
	 */
	private $client;

	/**
	 * Cache + resolver that persists fetched payloads.
	 *
	 * @var WC_Stripe_Remote_Config
	 */
	private $remote_config;

	public function __construct( ?WC_Stripe_Remote_Config_Client $client = null, ?WC_Stripe_Remote_Config $remote_config = null ) {
		$this->client        = null === $client ? new WC_Stripe_Remote_Config_Client() : $client;
		$this->remote_config = null === $remote_config ? WC_Stripe_Remote_Config::get_instance() : $remote_config;
	}

	/**
	 * Wire up hooks. Called once during plugin bootstrap.
	 */
	public function init_hooks(): void {
		add_action( self::SYNC_ACTION, [ $this, 'run' ] );
		add_action( 'woocommerce_stripe_updated', [ self::class, 'on_plugin_upgrade' ] );
		add_action( 'update_option_woocommerce_stripe_settings', [ self::class, 'maybe_sync_on_connection_change' ], 10, 2 );
		add_action( 'init', [ $this, 'maybe_schedule_daily_sync' ] );
		// Re-arm the recurring action if the schedule is purged.
		add_action( 'action_scheduler_run_recurring_actions_schedule_hook', [ $this, 'maybe_schedule_daily_sync' ] );
	}

	/**
	 * Ensures the daily recurring action is scheduled.
	 *
	 * The first run is randomized within a ±1h window around 01:00 UTC (WP pins
	 * PHP's timezone to UTC, so local DST transitions can't make the anchor
	 * ambiguous) to spread out traffic across the merchant base. The offset is
	 * chosen once per store and inherited by every subsequent recurrence.
	 */
	public function maybe_schedule_daily_sync(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::SYNC_ACTION, [], self::SCHEDULER_GROUP ) ) {
			return;
		}

		$jitter = wp_rand( -HOUR_IN_SECONDS, HOUR_IN_SECONDS );
		$start  = strtotime( 'tomorrow 01:00' ) + $jitter;
		as_schedule_recurring_action( $start, DAY_IN_SECONDS, self::SYNC_ACTION, [], self::SCHEDULER_GROUP );
	}

	/**
	 * Runs the sync. Safe to call directly.
	 *
	 * One combined fetch covers both modes, and both payloads are cached even
	 * when only one mode has keys, so a later mode switch or go-live starts
	 * from a warm cache instead of local fallbacks.
	 *
	 * @param int $attempt In-cycle attempt number; 0 for scheduled/immediate
	 *                     syncs, incremented by the retry chain.
	 * @return void
	 */
	public function run( $attempt = 0 ): void {
		if ( ! WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() ) {
			return;
		}

		// Don't phone home from stores with no Stripe connection at all.
		if ( [] === $this->connected_modes() ) {
			return;
		}

		$response = $this->client->fetch_all();
		if ( is_wp_error( $response ) ) {
			$this->handle_failed_fetch( $response, (int) $attempt );
			return;
		}

		foreach ( [ 'live', 'test' ] as $mode ) {
			if ( isset( $response['modes'][ $mode ] ) && is_array( $response['modes'][ $mode ] ) ) {
				$this->remote_config->apply( $mode, $response['modes'][ $mode ] );
			}
		}

		if ( 0 !== (int) get_option( self::FAILURE_COUNT_OPTION, 0 ) ) {
			delete_option( self::FAILURE_COUNT_OPTION );
		}
	}

	/**
	 * Records a failed fetch and schedules the next in-cycle retry.
	 *
	 * The cache is deliberately left untouched: last-known-good wins until a
	 * fetch succeeds, however stale it gets. Expiring values back to local
	 * defaults would re-enable a remotely disabled feature on exactly the
	 * sites the disable can no longer reach.
	 *
	 * @param WP_Error $error   The fetch failure.
	 * @param int      $attempt In-cycle attempt number of the failed run.
	 * @return void
	 */
	private function handle_failed_fetch( WP_Error $error, int $attempt ): void {
		$failures = (int) get_option( self::FAILURE_COUNT_OPTION, 0 ) + 1;
		update_option( self::FAILURE_COUNT_OPTION, $failures, false );

		$ages = [
			'live' => $this->remote_config->get_cache_age( 'live' ),
			'test' => $this->remote_config->get_cache_age( 'test' ),
		];

		$context = [
			'error'                => $error->get_error_code(),
			'attempt'              => $attempt,
			'consecutive_failures' => $failures,
			'cache_age_seconds'    => $ages,
			'previous_cache'       => [
				'live' => $this->remote_config->get_cache_snapshot( 'live' ),
				'test' => $this->remote_config->get_cache_snapshot( 'test' ),
			],
		];

		// A long-unreachable endpoint means remote flag changes are no longer
		// arriving; escalate so the staleness is visible in the logs.
		if ( $this->is_cache_stale( $ages ) ) {
			WC_Stripe_Logger::warning( 'Stripe remote-config: fetch failed and cache is stale; keeping last-known-good values.', $context );
		} else {
			WC_Stripe_Logger::debug( 'Stripe remote-config: fetch failed; keeping previous cache.', $context );
		}

		$this->maybe_schedule_retry( $error, $attempt );
	}

	/**
	 * Whether a connected mode's cached config is older than the warning threshold.
	 *
	 * A mode with no cache at all is not stale: it holds no remote overrides,
	 * so the site already runs on local defaults.
	 *
	 * @param array<string, int|null> $ages Cache age per mode, null when uncached.
	 * @return bool
	 */
	private function is_cache_stale( array $ages ): bool {
		foreach ( $this->connected_modes() as $mode ) {
			$age = $ages[ $mode ] ?? null;
			if ( null !== $age && $age > self::STALENESS_WARNING_SECONDS ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Schedules the next retry attempt after a failed fetch, with backoff.
	 *
	 * Only transport-level failures are retried: a disabled channel is a
	 * deliberate state, and retrying it sooner cannot change the outcome.
	 *
	 * @param WP_Error $error   The fetch failure.
	 * @param int      $attempt In-cycle attempt number of the failed run.
	 * @return void
	 */
	private function maybe_schedule_retry( WP_Error $error, int $attempt ): void {
		if ( 'wc_stripe_remote_config_disabled' === $error->get_error_code() ) {
			return;
		}

		if ( ! isset( self::RETRY_DELAYS[ $attempt ] ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$next_attempt = $attempt + 1;
		if ( as_has_scheduled_action( self::SYNC_ACTION, [ $next_attempt ], self::SCHEDULER_GROUP ) ) {
			return;
		}

		as_schedule_single_action( time() + self::RETRY_DELAYS[ $attempt ], self::SYNC_ACTION, [ $next_attempt ], self::SCHEDULER_GROUP );
	}

	/**
	 * Hook callback for `woocommerce_stripe_updated`. Enqueues an immediate
	 * single sync after the plugin updates.
	 *
	 * Static because it uses no instance state and is registered directly as a
	 * hook callback, which keeps it decoupled from the upgrade-handling refactor.
	 */
	public static function on_plugin_upgrade(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		as_enqueue_async_action( self::SYNC_ACTION, [], self::SCHEDULER_GROUP );
	}

	/**
	 * Hook callback for `update_option_woocommerce_stripe_settings`. Enqueues an
	 * immediate sync when API keys or the test/live mode change.
	 *
	 * The config is cached per mode and run() skips modes without keys, so a
	 * store that connects a new mode (e.g. goes live after being test-only)
	 * would otherwise serve local fallbacks for that mode until the next daily
	 * run — leaving a remote disable inert for up to a day.
	 *
	 * @param mixed $old_value Previous value of the settings option.
	 * @param mixed $value     New value of the settings option.
	 */
	public static function maybe_sync_on_connection_change( $old_value, $value ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$old = is_array( $old_value ) ? $old_value : [];
		$new = is_array( $value ) ? $value : [];

		foreach ( [ 'testmode', 'secret_key', 'test_secret_key' ] as $field ) {
			if ( ( $old[ $field ] ?? '' ) !== ( $new[ $field ] ?? '' ) ) {
				as_enqueue_async_action( self::SYNC_ACTION, [], self::SCHEDULER_GROUP );
				return;
			}
		}
	}

	/**
	 * Returns the modes for which the merchant has Stripe API keys configured.
	 *
	 * @return string[] Subset of ['live', 'test'].
	 */
	private function connected_modes(): array {
		$modes = [];
		if ( WC_Stripe_Helper::is_connected( 'live' ) ) {
			$modes[] = 'live';
		}
		if ( WC_Stripe_Helper::is_connected( 'test' ) ) {
			$modes[] = 'test';
		}
		return $modes;
	}
}
