<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage for the processing state of Stripe events.
 *
 * Implementations only persist state; deciding what to process and when belongs to the callers.
 */
interface WC_Stripe_Event_Store_Interface {
	public const STATUS_PENDING    = 'pending';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_PROCESSED  = 'processed';
	public const STATUS_FAILED     = 'failed';

	/**
	 * Returns the stored status of each known event.
	 *
	 * @param string[] $event_ids Stripe event IDs.
	 * @return array<string, string> Status keyed by event ID, as passed in. Unknown events are omitted.
	 */
	public function get_statuses( array $event_ids ): array;

	/**
	 * Returns the IDs of events with the given status, oldest event first.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @param int    $limit  Maximum number of IDs to return.
	 * @return string[] Stripe event IDs.
	 */
	public function get_event_ids_by_status( string $status, int $limit ): array;

	/**
	 * Creates or updates the record of an event.
	 *
	 * @param string $event_id Stripe event ID.
	 * @param string $status   One of the STATUS_* constants.
	 * @param array  $data     {
	 *     Optional. Event details. Missing keys keep their stored values.
	 *
	 *     @type string $type     Stripe event type.
	 *     @type int    $created  Stripe event creation timestamp.
	 *     @type int    $order_id ID of the order the event was applied to.
	 * }
	 * @return bool Whether the record was saved.
	 */
	public function save( string $event_id, string $status, array $data = [] ): bool;
}
