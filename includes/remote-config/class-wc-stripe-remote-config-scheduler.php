<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wc-stripe-remote-config-client.php';
require_once __DIR__ . '/class-wc-stripe-remote-config.php';

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

	const SYNC_ACTION     = 'wc_stripe_remote_config_sync';
	const SCHEDULER_GROUP = 'woocommerce-gateway-stripe';
	const PLUGIN_BASENAME = 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php';

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
		add_action( 'upgrader_process_complete', [ $this, 'on_plugin_upgrade' ], 10, 2 );
		add_action( 'init', [ $this, 'maybe_schedule_daily_sync' ] );
		// Re-arm the recurring action if the schedule is purged.
		add_action( 'action_scheduler_run_recurring_actions_schedule_hook', [ $this, 'maybe_schedule_daily_sync' ] );
	}

	/**
	 * Ensures the daily recurring action is scheduled (idempotent).
	 */
	public function maybe_schedule_daily_sync(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::SYNC_ACTION, [], self::SCHEDULER_GROUP ) ) {
			return;
		}

		$start = strtotime( 'tomorrow 01:00' );
		as_schedule_recurring_action( $start, DAY_IN_SECONDS, self::SYNC_ACTION, [], self::SCHEDULER_GROUP );
	}

	/**
	 * Runs the sync for every connected mode. Safe to call directly.
	 */
	public function run(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		foreach ( $this->connected_modes() as $mode ) {
			$response = $this->client->fetch( $mode );
			if ( is_wp_error( $response ) ) {
				WC_Stripe_Logger::debug(
					'Stripe remote-config: fetch failed; keeping previous cache.',
					[
						'mode'  => $mode,
						'error' => $response->get_error_code(),
					]
				);
				continue;
			}
			$this->remote_config->apply( $mode, $response );
		}
	}

	/**
	 * Hook callback for `upgrader_process_complete`. Enqueues an immediate
	 * single sync if this plugin was part of the upgrade.
	 *
	 * @param mixed $upgrader   Unused (Plugin_Upgrader instance, varies).
	 * @param array $hook_extra Upgrader payload.
	 */
	public function on_plugin_upgrade( $upgrader, $hook_extra ): void {
		if ( ! is_array( $hook_extra ) || ( $hook_extra['type'] ?? '' ) !== 'plugin' ) {
			return;
		}

		$plugins = $hook_extra['plugins'] ?? [];
		if ( ! is_array( $plugins ) || ! in_array( self::PLUGIN_BASENAME, $plugins, true ) ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		as_enqueue_async_action( self::SYNC_ACTION, [], self::SCHEDULER_GROUP );
	}

	private function is_enabled(): bool {
		if ( defined( 'WC_STRIPE_DISABLE_REMOTE_CONFIG' ) && WC_STRIPE_DISABLE_REMOTE_CONFIG ) {
			return false;
		}

		return (bool) apply_filters( 'wc_stripe_remote_config_enabled', true );
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
