<?php
/**
 * WC_Stripe_Action_Scheduler_Service class
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class which handles setting up all ActionScheduler hooks.
 */
class WC_Stripe_Action_Scheduler_Service {

	const GROUP_ID = 'woocommerce_stripe';

	/**
	 * Schedule an action scheduler job. Also unschedules (replaces) any previous instances of the same job.
	 * This prevents duplicate jobs, for example when multiple events fire as part of the order update process.
	 * The `as_unschedule_action` function will only replace a job which has the same $hook, $args AND $group.
	 *
	 * @param int    $timestamp - When the job will run.
	 * @param string $hook      - The hook to trigger.
	 * @param array  $args      - An array containing the arguments to be passed to the hook.
	 * @param string $group     - The AS group the action will be created under.
	 *
	 * @return void
	 */
	public function schedule_job( int $timestamp, string $hook, array $args = [], string $group = self::GROUP_ID ) {
		// Unschedule any previously scheduled instances of this particular job.
		as_unschedule_action( $hook, $args, $group );

		// Schedule the job.
		as_schedule_single_action( $timestamp, $hook, $args, $group );
	}
}
