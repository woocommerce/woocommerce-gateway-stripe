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
	 * Returns the stored records of the given events.
	 *
	 * @param string[] $event_ids Stripe event IDs.
	 * @return array<string, WC_Stripe_Event_Record> Records keyed by event ID, as passed in. Unknown events are omitted.
	 */
	public function get( array $event_ids ): array;

	/**
	 * Returns the records with the given status, oldest event first.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @param int    $limit  Maximum number of records to return.
	 * @return WC_Stripe_Event_Record[]
	 */
	public function get_by_status( string $status, int $limit ): array;

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
