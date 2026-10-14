<?php
/**
 * In-memory implementation of WC_Stripe_Event_Store_Interface for tests.
 *
 * @package WooCommerce_Stripe/Tests/Helpers
 */

/**
 * Keeps event records in an array, so reconciler tests do not depend on the post store's queries.
 */
class WC_Stripe_In_Memory_Event_Store implements WC_Stripe_Event_Store_Interface {

	/**
	 * Stored records, keyed by event ID.
	 *
	 * @var array<string, WC_Stripe_Event_Record>
	 */
	public $records = [];

	/**
	 * Time each record was last saved, keyed by event ID.
	 *
	 * @var array<string, int>
	 */
	public $modified = [];

	/**
	 * Arguments of every delete_older_than() call.
	 *
	 * @var array[]
	 */
	public $delete_older_than_calls = [];

	/**
	 * Stores a record as if it was last saved at the given time.
	 *
	 * @param WC_Stripe_Event_Record $record   Record to store.
	 * @param int|null               $modified Time of the last save. Defaults to now.
	 */
	public function seed( WC_Stripe_Event_Record $record, ?int $modified = null ): void {
		$this->records[ $record->id ]  = clone $record;
		$this->modified[ $record->id ] = $modified ?? time();
	}

	public function get( string $event_id ): ?WC_Stripe_Event_Record {
		return isset( $this->records[ $event_id ] ) ? clone $this->records[ $event_id ] : null;
	}

	public function get_many( array $event_ids ): array {
		$found = [];
		foreach ( $event_ids as $event_id ) {
			if ( isset( $this->records[ $event_id ] ) ) {
				$found[ $event_id ] = clone $this->records[ $event_id ];
			}
		}
		return $found;
	}

	public function get_by_status( string $status, bool $livemode, int $limit, ?int $modified_before = null ): array {
		$matches = array_filter(
			$this->records,
			function ( WC_Stripe_Event_Record $record ) use ( $status, $livemode, $modified_before ) {
				return $record->status === $status
					&& $record->livemode === $livemode
					&& ( null === $modified_before || $this->modified[ $record->id ] < $modified_before );
			}
		);

		usort(
			$matches,
			static function ( WC_Stripe_Event_Record $a, WC_Stripe_Event_Record $b ) {
				return [ $a->attempts, $a->created ] <=> [ $b->attempts, $b->created ];
			}
		);

		return array_map(
			static function ( WC_Stripe_Event_Record $record ) {
				return clone $record;
			},
			array_slice( $matches, 0, $limit )
		);
	}

	public function save( WC_Stripe_Event_Record $record ): bool {
		$this->seed( $record );
		return true;
	}

	public function has_older_than( int $timestamp ): bool {
		foreach ( $this->records as $record ) {
			if ( $record->created < $timestamp ) {
				return true;
			}
		}
		return false;
	}

	public function delete_older_than( int $timestamp, int $limit ): int {
		$this->delete_older_than_calls[] = [ $timestamp, $limit ];

		$deleted = 0;
		foreach ( $this->records as $event_id => $record ) {
			if ( $deleted < $limit && $record->created < $timestamp ) {
				unset( $this->records[ $event_id ], $this->modified[ $event_id ] );
				++$deleted;
			}
		}
		return $deleted;
	}

	public function delete_all(): int {
		$deleted        = count( $this->records );
		$this->records  = [];
		$this->modified = [];
		return $deleted;
	}
}
