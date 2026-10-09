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
	 * Returns the stored record of an event.
	 *
	 * @param string $event_id Stripe event ID.
	 * @return WC_Stripe_Event_Record|null The record, or null when the event is unknown.
	 */
	public function get( string $event_id ): ?WC_Stripe_Event_Record;

	/**
	 * Returns the stored records of the given events.
	 *
	 * @param string[] $event_ids Stripe event IDs.
	 * @return array<string, WC_Stripe_Event_Record> Records keyed by event ID, as passed in. Unknown events are omitted.
	 */
	public function get_many( array $event_ids ): array;

	/**
	 * Returns the records with the given status and mode, oldest event first.
	 *
	 * @param string $status   One of the STATUS_* constants.
	 * @param bool   $livemode Whether to return live mode events, rather than test mode ones.
	 * @param int    $limit    Maximum number of records to return.
	 * @return WC_Stripe_Event_Record[]
	 */
	public function get_by_status( string $status, bool $livemode, int $limit ): array;

	/**
	 * Creates the record of an event, or replaces the stored one.
	 *
	 * @param WC_Stripe_Event_Record $record Record to store.
	 * @return bool Whether the record was saved.
	 */
	public function save( WC_Stripe_Event_Record $record ): bool;

	/**
	 * Deletes all stored records.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_all(): int;
}
