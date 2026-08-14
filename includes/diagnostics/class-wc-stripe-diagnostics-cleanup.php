<?php

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler job that keeps the diagnostics trace store tidy.
 *
 * Runs once a day to delete traces older than seven days. Enforces the
 * "store never grows without bound" guarantee regardless of traffic.
 *
 * Stale `pending` traces (checkouts the shopper bailed on without sending a
 * session-end signal) are intentionally left alone — they age out via the
 * daily sweep, and the dashboard reads "pending" as "still in flight," which
 * is acceptable for the short while before deletion.
 */
class WC_Stripe_Diagnostics_Cleanup {

	/**
	 * Action Scheduler hook for the daily 7-day purge.
	 *
	 * @var string
	 */
	protected const DAILY_ACTION = 'wc_stripe_diagnostics_daily_cleanup';

	/**
	 * Action Scheduler group. Shared with the rest of the plugin's jobs so
	 * the diagnostics actions surface alongside them in WP Admin → Tools →
	 * Scheduled Actions.
	 *
	 * @var string
	 */
	protected const GROUP = 'woocommerce-gateway-stripe';

	/**
	 * Maximum age of a trace before the daily sweep deletes it. Bounds
	 * total store size in time, regardless of traffic.
	 *
	 * @var int
	 */
	protected const TRACE_TTL_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Trace store dependency.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * Build a cleanup runner.
	 *
	 * @param WC_Stripe_Diagnostics_Trace_Store|null $store Optional trace store
	 *                                                      to inject (mainly for tests);
	 *                                                      defaults to a fresh instance.
	 */
	public function __construct( ?WC_Stripe_Diagnostics_Trace_Store $store = null ) {
		$this->store = $store ?? new WC_Stripe_Diagnostics_Trace_Store();
	}

	/**
	 * Wire up the job. Idempotent: safe to call on every request.
	 *
	 * @return void
	 */
	public function init(): void {
		// run_daily_cleanup returns a count so tests can assert on it;
		// discard the return value when invoked as an action callback.
		add_action(
			self::DAILY_ACTION,
			function () {
				$this->run_daily_cleanup();
			}
		);
		add_action( 'init', [ $this, 'ensure_scheduled' ], 20 );
	}

	/**
	 * Make sure the daily job is scheduled. Called late enough that
	 * Action Scheduler is available.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( ! did_action( 'action_scheduler_init' ) ) {
			return;
		}
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::DAILY_ACTION, [], self::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::DAILY_ACTION, [], self::GROUP );
		}
	}

	/**
	 * Delete traces older than {@see self::TRACE_TTL_SECONDS}.
	 *
	 * @return int Number of traces deleted.
	 */
	public function run_daily_cleanup(): int {
		$cutoff  = time() - self::TRACE_TTL_SECONDS;
		$deleted = 0;
		foreach ( $this->store->get_all_ids() as $id ) {
			$trace = $this->store->get( $id );
			if ( null === $trace ) {
				continue;
			}
			$created_at = isset( $trace['created_at'] ) ? (int) $trace['created_at'] : 0;
			if ( $created_at > 0 && $created_at < $cutoff ) {
				$this->store->delete( $id );
				++$deleted;
			}
		}
		return $deleted;
	}
}
