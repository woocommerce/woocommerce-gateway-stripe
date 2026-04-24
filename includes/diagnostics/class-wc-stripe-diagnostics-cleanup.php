<?php

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler jobs that keep the diagnostics trace store tidy.
 *
 * Two jobs run in the background:
 *
 * - **Daily 7-day sweep:** deletes traces older than seven days. Enforces the
 *   "store never grows without bound" guarantee regardless of traffic.
 * - **5-minute pending sweep:** promotes traces that have been stuck in
 *   `pending` for more than 90 seconds to `abandoned`. These are checkouts
 *   where the shopper bailed between opening the payment form and sending
 *   a session-end signal.
 */
class WC_Stripe_Diagnostics_Cleanup {

	/**
	 * Action Scheduler hook for the daily 7-day purge.
	 *
	 * @var string
	 */
	const DAILY_ACTION = 'wc_stripe_diagnostics_daily_cleanup';

	/**
	 * Action Scheduler hook for the recurring stale-pending sweep.
	 *
	 * @var string
	 */
	const PENDING_ACTION = 'wc_stripe_diagnostics_promote_abandoned';

	/**
	 * Action Scheduler group. Shared with the rest of the plugin's jobs so
	 * the diagnostics actions surface alongside them in WP Admin → Tools →
	 * Scheduled Actions.
	 *
	 * @var string
	 */
	const GROUP = 'woocommerce-gateway-stripe';

	/**
	 * Maximum age of a trace before the daily sweep deletes it. Bounds
	 * total store size in time, regardless of traffic.
	 *
	 * @var int
	 */
	const TRACE_TTL_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Idle window (in seconds) after which a `pending` trace is treated as
	 * abandoned by the pending sweep. Tuned to be longer than a reasonable
	 * checkout interaction but short enough that abandoned shoppers don't
	 * sit in `pending` indefinitely.
	 *
	 * @var int
	 */
	const PENDING_ABANDON_THRESHOLD_S = 90;

	/**
	 * Cadence of the pending sweep. Five minutes keeps abandoned traces
	 * from lingering long without piling on Action Scheduler.
	 *
	 * @var int
	 */
	const PENDING_SWEEP_INTERVAL_S = 5 * MINUTE_IN_SECONDS;

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
	 * Wire up the jobs. Idempotent: safe to call on every request.
	 *
	 * @return void
	 */
	public function init(): void {
		// The run_* methods return counts so tests can assert on them;
		// discard the return values when invoked as action callbacks.
		add_action(
			self::DAILY_ACTION,
			function () {
				$this->run_daily_cleanup();
			}
		);
		add_action(
			self::PENDING_ACTION,
			function () {
				$this->run_pending_sweep();
			}
		);
		add_action( 'init', [ $this, 'ensure_scheduled' ], 20 );
	}

	/**
	 * Make sure both jobs are scheduled. Called late enough that
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
		if ( ! as_has_scheduled_action( self::PENDING_ACTION, [], self::GROUP ) ) {
			as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, self::PENDING_SWEEP_INTERVAL_S, self::PENDING_ACTION, [], self::GROUP );
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

	/**
	 * Promote any `pending` trace that hasn't been updated in
	 * {@see self::PENDING_ABANDON_THRESHOLD_S} to `abandoned`.
	 *
	 * @return int Number of traces promoted.
	 */
	public function run_pending_sweep(): int {
		$cutoff   = time() - self::PENDING_ABANDON_THRESHOLD_S;
		$promoted = 0;
		foreach ( $this->store->get_all_ids() as $id ) {
			$trace = $this->store->get( $id );
			if ( null === $trace ) {
				continue;
			}
			$status     = $trace['status'] ?? '';
			$updated_at = isset( $trace['updated_at'] ) ? (int) $trace['updated_at'] : 0;
			if ( WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING === $status && $updated_at > 0 && $updated_at < $cutoff ) {
				$this->store->set_status( $id, WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED );
				++$promoted;
			}
		}
		return $promoted;
	}
}
