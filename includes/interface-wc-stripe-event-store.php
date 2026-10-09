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
	/**
	 * Stripe did not deliver the event. It waits for the processing job, including after a failed fetch.
	 */
	public const STATUS_PENDING = 'pending';

	/**
	 * Claimed by a webhook delivery or the processing job, or deferred by the webhook handler.
	 */
	public const STATUS_PROCESSING = 'processing';

	/**
	 * The webhook handler finished with the event.
	 */
	public const STATUS_PROCESSED = 'processed';

	/**
	 * Processing threw. Not retried, since it would most likely fail the same way.
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * The event could not be fetched from Stripe: it no longer exists, or every attempt failed.
	 */
	public const STATUS_ABANDONED = 'abandoned';

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
	 * Returns the records with the given status and mode, fewest attempts first, then oldest event first.
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
	 * Deletes records of events created before the given time, oldest first.
	 *
	 * @param int $timestamp Events created before this time are deleted.
	 * @param int $limit     Maximum number of records to delete.
	 * @return int Number of records deleted.
	 */
	public function delete_older_than( int $timestamp, int $limit ): int;

	/**
	 * Deletes all stored records.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_all(): int;
}
