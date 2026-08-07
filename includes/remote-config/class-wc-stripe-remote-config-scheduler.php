<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules and runs the Stripe remote-config sync.
 *
 * - Daily Action Scheduler job (`wc_stripe_remote_config_sync`).
 * - Immediate single-action enqueue on plugin upgrade
 *
 * On each run, iterates over connected Stripe modes (live/test) and calls
 * Client::fetch -> Remote_Config::apply for each.
 */
class WC_Stripe_Remote_Config_Scheduler {

	public const SYNC_ACTION     = 'wc_stripe_remote_config_sync';
	public const SCHEDULER_GROUP = 'woocommerce-gateway-stripe';

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
		$this->remote_config = null === $remote_config ? new WC_Stripe_Remote_Config() : $remote_config;
	}

	/**
	 * Wire up hooks. Called once during plugin bootstrap.
	 */
	public function init_hooks(): void {
		add_action( self::SYNC_ACTION, [ $this, 'run' ] );
		add_action( 'woocommerce_stripe_updated', [ self::class, 'on_plugin_upgrade' ] );
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
	 * Runs the sync for every connected mode. Safe to call directly.
	 */
	public function run(): void {
		if ( ! WC_Stripe_Remote_Config_Flags::is_remote_config_enabled() ) {
			return;
		}

		foreach ( $this->connected_modes() as $mode ) {
			$response = $this->client->fetch( $mode );
			if ( is_wp_error( $response ) ) {
				WC_Stripe_Logger::debug(
					'Stripe remote-config: fetch failed; keeping previous cache.',
					[
						'mode'           => $mode,
						'error'          => $response->get_error_code(),
						'previous_cache' => $this->remote_config->get_cache_snapshot( $mode ),
					]
				);
				continue;
			}
			$this->remote_config->apply( $mode, $response );
		}
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
