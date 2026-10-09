<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds tools for undelivered Stripe events to WooCommerce > Status > Tools.
 */
class WC_Stripe_Event_Debug_Tools {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Returns the shared instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the hooks.
	 */
	public function init(): void {
		if ( ! WC_Stripe_Feature_Flags::is_event_reconciliation_enabled() ) {
			return;
		}

		add_filter( 'woocommerce_debug_tools', [ $this, 'add_tools' ] );
	}

	/**
	 * Adds the tools.
	 *
	 * @param mixed $tools Registered tools.
	 * @return mixed
	 */
	public function add_tools( $tools ) {
		if ( ! is_array( $tools ) ) {
			return $tools;
		}

		$tools['wc_stripe_queue_undelivered_events'] = [
			'name'     => __( 'Process undelivered Stripe events', 'woocommerce-gateway-stripe' ),
			'button'   => __( 'Process events', 'woocommerce-gateway-stripe' ),
			'desc'     => __( 'Fetches the events Stripe could not deliver to this store through webhooks, and processes them in the background.', 'woocommerce-gateway-stripe' ),
			'callback' => [ $this, 'queue_undelivered_events' ],
		];

		$tools['wc_stripe_clear_stored_events'] = [
			'name'     => __( 'Clear stored Stripe events', 'woocommerce-gateway-stripe' ),
			'button'   => __( 'Clear events', 'woocommerce-gateway-stripe' ),
			'desc'     => __( 'Deletes the processing state stored for Stripe events, so the next run of "Process undelivered Stripe events" starts over. Events are not removed from Stripe.', 'woocommerce-gateway-stripe' ),
			'callback' => [ $this, 'clear_stored_events' ],
		];

		return $tools;
	}

	/**
	 * Queues undelivered events and schedules their processing.
	 *
	 * @return string Message shown by WooCommerce once the tool has run.
	 */
	public function queue_undelivered_events(): string {
		$reconciler = new WC_Stripe_Event_Reconciler();
		$summary    = $reconciler->queue_undelivered_events();
		$reconciler->schedule_processing();

		$queued = count( wp_list_filter( $summary['events'], [ 'result' => 'queued' ] ) );

		return sprintf(
			/* translators: 1: number of events queued, 2: number of undelivered events found */
			__( 'Queued %1$d of %2$d undelivered Stripe events for processing.', 'woocommerce-gateway-stripe' ),
			$queued,
			count( $summary['events'] )
		);
	}

	/**
	 * Deletes all stored events.
	 *
	 * @return string Message shown by WooCommerce once the tool has run.
	 */
	public function clear_stored_events(): string {
		$deleted = ( new WC_Stripe_Event_Reconciler() )->reset();

		return sprintf(
			/* translators: %d: number of events deleted */
			_n( 'Deleted %d stored Stripe event.', 'Deleted %d stored Stripe events.', $deleted, 'woocommerce-gateway-stripe' ),
			$deleted
		);
	}
}
