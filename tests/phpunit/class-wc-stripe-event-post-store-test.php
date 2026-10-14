<?php
/**
 * Tests for WC_Stripe_Event_Post_Store.
 *
 * @package WooCommerce_Stripe/Tests
 */

/**
 * WC_Stripe_Event_Post_Store_Test class.
 */
class WC_Stripe_Event_Post_Store_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Event_Post_Store
	 */
	private $store;

	public function set_up() {
		parent::set_up();
		$this->store = WC_Stripe_Event_Post_Store::get_instance();
	}

	/**
	 * Every field of a record survives a save and a read, for every status.
	 *
	 * @param string $status Store status.
	 * @dataProvider provide_statuses
	 */
	public function test_save_and_get_round_trip( string $status ): void {
		$record = new WC_Stripe_Event_Record( 'evt_1RoundTrip', $status, 'charge.succeeded', 1700000000, true, 123, 2 );

		$this->assertTrue( $this->store->save( $record ) );

		$this->assertEquals( $record, $this->store->get( 'evt_1RoundTrip' ) );
	}

	/**
	 * @return array
	 */
	public function provide_statuses(): array {
		return [
			'pending'    => [ WC_Stripe_Event_Store_Interface::STATUS_PENDING ],
			'processing' => [ WC_Stripe_Event_Store_Interface::STATUS_PROCESSING ],
			'processed'  => [ WC_Stripe_Event_Store_Interface::STATUS_PROCESSED ],
			'abandoned'  => [ WC_Stripe_Event_Store_Interface::STATUS_ABANDONED ],
		];
	}

	/**
	 * Saving a known event updates its record instead of adding a second one.
	 */
	public function test_save_updates_the_existing_record(): void {
		$this->store->save( $this->make_record( 'evt_1Update', WC_Stripe_Event_Store_Interface::STATUS_PROCESSING ) );
		$this->store->save( $this->make_record( 'evt_1Update', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED ) );

		$this->assertSame( WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $this->store->get( 'evt_1Update' )->status );
		$this->assertSame( 1, $this->count_posts() );
	}

	/**
	 * Records that cannot be stored are rejected.
	 *
	 * @param string $event_id Event ID.
	 * @param string $status   Status.
	 * @dataProvider provide_invalid_records
	 */
	public function test_save_rejects_invalid_records( string $event_id, string $status ): void {
		$this->assertFalse( $this->store->save( $this->make_record( $event_id, $status ) ) );
		$this->assertSame( 0, $this->count_posts() );
	}

	/**
	 * @return array
	 */
	public function provide_invalid_records(): array {
		return [
			'empty event ID' => [ '', WC_Stripe_Event_Store_Interface::STATUS_PENDING ],
			'unknown status' => [ 'evt_1Invalid', 'publish' ],
		];
	}

	/**
	 * get_many() returns known records keyed by the IDs passed in, and omits unknown ones.
	 */
	public function test_get_many_returns_known_records_keyed_by_id(): void {
		$this->store->save( $this->make_record( 'evt_1A', WC_Stripe_Event_Store_Interface::STATUS_PENDING ) );
		$this->store->save( $this->make_record( 'evt_1B', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED ) );

		$records = $this->store->get_many( [ 'evt_1B', 'evt_1Unknown', 'evt_1A' ] );

		$this->assertSame( [ 'evt_1B', 'evt_1A' ], array_keys( $records ) );
		$this->assertSame( 'evt_1A', $records['evt_1A']->id );
		$this->assertSame( [], $this->store->get_many( [] ) );
	}

	/**
	 * The case of an event ID is kept, although post slugs are lowercased.
	 */
	public function test_event_id_case_is_preserved(): void {
		$this->store->save( $this->make_record( 'evt_1MixedCASE', WC_Stripe_Event_Store_Interface::STATUS_PENDING ) );

		$this->assertSame( 'evt_1MixedCASE', $this->store->get( 'evt_1MixedCASE' )->id );
	}

	/**
	 * get_by_status() filters by status and mode, puts fewer attempts first, then older events, and honors the limit.
	 */
	public function test_get_by_status_filters_orders_and_limits(): void {
		$pending = WC_Stripe_Event_Store_Interface::STATUS_PENDING;
		$this->store->save( new WC_Stripe_Event_Record( 'evt_1Retried', $pending, 'charge.succeeded', 1000, false, null, 3 ) );
		$this->store->save( new WC_Stripe_Event_Record( 'evt_1Newer', $pending, 'charge.succeeded', 3000, false ) );
		$this->store->save( new WC_Stripe_Event_Record( 'evt_1Older', $pending, 'charge.succeeded', 2000, false ) );
		$this->store->save( new WC_Stripe_Event_Record( 'evt_1Live', $pending, 'charge.succeeded', 1500, true ) );
		$this->store->save( new WC_Stripe_Event_Record( 'evt_1Done', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, 'charge.succeeded', 1500, false ) );

		$this->assertSame( [ 'evt_1Older', 'evt_1Newer', 'evt_1Retried' ], $this->ids( $this->store->get_by_status( $pending, false, 10 ) ) );
		$this->assertSame( [ 'evt_1Older', 'evt_1Newer' ], $this->ids( $this->store->get_by_status( $pending, false, 2 ) ) );
		$this->assertSame( [ 'evt_1Live' ], $this->ids( $this->store->get_by_status( $pending, true, 10 ) ) );
		$this->assertSame( [], $this->store->get_by_status( 'unknown', false, 10 ) );
	}

	/**
	 * get_by_status() only returns records last saved before $modified_before.
	 */
	public function test_get_by_status_modified_before(): void {
		$processing = WC_Stripe_Event_Store_Interface::STATUS_PROCESSING;
		$this->store->save( $this->make_record( 'evt_1Stale', $processing ) );
		$this->store->save( $this->make_record( 'evt_1Fresh', $processing ) );
		$this->set_post_modified( 'evt_1Stale', time() - 2 * HOUR_IN_SECONDS );

		$this->assertSame( [ 'evt_1Stale' ], $this->ids( $this->store->get_by_status( $processing, false, 10, time() - HOUR_IN_SECONDS ) ) );
	}

	/**
	 * delete_older_than() deletes events created before the cutoff, oldest first, up to the limit.
	 */
	public function test_delete_older_than(): void {
		$now = time();
		$this->store->save( $this->make_record( 'evt_1Oldest', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $now - 40 * DAY_IN_SECONDS ) );
		$this->store->save( $this->make_record( 'evt_1Old', WC_Stripe_Event_Store_Interface::STATUS_PENDING, $now - 35 * DAY_IN_SECONDS ) );
		$this->store->save( $this->make_record( 'evt_1Recent', WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $now - DAY_IN_SECONDS ) );

		$cutoff = $now - 30 * DAY_IN_SECONDS;

		$this->assertSame( 1, $this->store->delete_older_than( $cutoff, 1 ) );
		$this->assertNull( $this->store->get( 'evt_1Oldest' ) );
		$this->assertNotNull( $this->store->get( 'evt_1Old' ) );

		$this->assertSame( 1, $this->store->delete_older_than( $cutoff, 10 ) );
		$this->assertSame( 0, $this->store->delete_older_than( $cutoff, 0 ) );
		$this->assertSame( [ 'evt_1Recent' ], array_keys( $this->store->get_many( [ 'evt_1Oldest', 'evt_1Old', 'evt_1Recent' ] ) ) );
	}

	/**
	 * delete_all() deletes every record, across batches.
	 */
	public function test_delete_all(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->store->save( $this->make_record( 'evt_1All' . $i, WC_Stripe_Event_Store_Interface::STATUS_PROCESSED ) );
		}

		$this->assertSame( 3, $this->store->delete_all() );
		$this->assertSame( 0, $this->count_posts() );
	}

	private function make_record( string $event_id, string $status, ?int $created = null ): WC_Stripe_Event_Record {
		return new WC_Stripe_Event_Record( $event_id, $status, 'charge.succeeded', $created ?? time() - MINUTE_IN_SECONDS, false );
	}

	/**
	 * @param WC_Stripe_Event_Record[] $records Records.
	 * @return string[]
	 */
	private function ids( array $records ): array {
		return wp_list_pluck( $records, 'id' );
	}

	private function count_posts(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", WC_Stripe_Event_Post_Store::POST_TYPE ) );
	}

	/**
	 * Backdates the last save of a record. wp_update_post() always sets post_modified to now, so this writes the row directly.
	 *
	 * @param string $event_id Event ID.
	 * @param int    $time     Timestamp.
	 */
	private function set_post_modified( string $event_id, int $time ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
				'post_modified'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ) ),
			],
			[
				'post_type' => WC_Stripe_Event_Post_Store::POST_TYPE,
				'post_name' => $event_id,
			]
		);
		clean_post_cache( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $event_id ) ) );
	}
}
