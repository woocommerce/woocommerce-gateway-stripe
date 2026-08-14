<?php

/**
 * Tests for WC_Stripe_Diagnostics_Trace_Store.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Trace_Store_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	public function set_up() {
		parent::set_up();
		$this->store = new WC_Stripe_Diagnostics_Trace_Store();
		$this->store->delete_all();
	}

	public function tear_down() {
		$this->store->delete_all();
		parent::tear_down();
	}

	public function test_create_and_get_round_trip() {
		$this->assertTrue( $this->store->create( 'abc123', [ 'source' => 'checkout' ] ) );
		$trace = $this->store->get( 'abc123' );
		$this->assertSame( 'abc123', $trace['id'] );
		$this->assertSame( WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING, $trace['status'] );
		$this->assertSame( 'checkout', $trace['meta']['source'] );
		$this->assertSame( [], $trace['events'] );
	}

	public function test_create_is_idempotent_for_same_id() {
		$this->assertTrue( $this->store->create( 'same' ) );
		$this->assertFalse( $this->store->create( 'same' ) );
		$this->assertCount( 1, $this->store->get_all_ids() );
	}

	public function test_append_event_adds_to_trace() {
		$this->store->create( 'trace1' );
		$this->assertTrue( $this->store->append_event( 'trace1', [ 'kind' => 'paymentMethodCreated' ] ) );
		$trace = $this->store->get( 'trace1' );
		$this->assertCount( 1, $trace['events'] );
		$this->assertSame( 'paymentMethodCreated', $trace['events'][0]['kind'] );
	}

	public function test_append_event_drops_past_event_cap() {
		$this->store->create( 'capped' );
		for ( $i = 0; $i < WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_EVENTS_PER_TRACE', 'int' ); $i++ ) {
			$this->assertTrue( $this->store->append_event( 'capped', [ 'n' => $i ] ) );
		}
		$this->assertFalse( $this->store->append_event( 'capped', [ 'n' => 'overflow' ] ) );
		$trace = $this->store->get( 'capped' );
		$this->assertCount( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_EVENTS_PER_TRACE', 'int' ), $trace['events'] );
	}

	public function test_append_event_drops_past_size_cap() {
		$this->store->create( 'big' );
		$big_event = [ 'payload' => str_repeat( 'x', WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_TRACE_BYTES', 'int' ) + 1 ) ];
		$this->assertFalse( $this->store->append_event( 'big', $big_event ) );
		$trace = $this->store->get( 'big' );
		$this->assertCount( 0, $trace['events'] );
	}

	public function test_set_status_promotes_and_rejects_unknown_status() {
		$this->store->create( 'promote' );
		$this->assertTrue( $this->store->set_status( 'promote', WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED ) );
		$this->assertSame(
			WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED,
			$this->store->get( 'promote' )['status']
		);
		$this->assertFalse( $this->store->set_status( 'promote', 'nonsense' ) );
	}

	/**
	 * STATUS_FAILED was added alongside the outcome promoter; covered here
	 * to lock in that set_status accepts it (the existing
	 * test_set_status_promotes_and_rejects_unknown_status only exercises
	 * COMPLETED).
	 */
	public function test_set_status_accepts_failed() {
		$this->store->create( 'failable' );
		$this->assertTrue( $this->store->set_status( 'failable', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED ) );
		$this->assertSame(
			WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED,
			$this->store->get( 'failable' )['status']
		);
	}

	/**
	 * Powers the merchant-facing breakdown row. Single fixture covers both
	 * "groups by status" and "zero-counts surface as keys" — UI relies on
	 * the keys always being present so it can omit empty buckets cleanly.
	 */
	public function test_count_by_status_groups_traces_and_includes_zero_buckets() {
		$this->store->create( 'p1' );
		$this->store->create( 'p2' );
		$this->store->create( 'f1' );
		$this->store->set_status( 'f1', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED );
		$this->store->create( 'c1' );
		$this->store->set_status( 'c1', WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED );
		// Note: no abandoned trace created — must still show as 0, not missing.

		$counts = $this->store->count_by_status();

		$this->assertSame( 2, $counts[ WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING ] );
		$this->assertSame( 1, $counts[ WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED ] );
		$this->assertSame( 1, $counts[ WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED ] );
		$this->assertSame( 0, $counts[ WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED ] );
	}

	/**
	 * Filter behavior of get_by_status: single status, multi-status, empty
	 * result, and silent drop of unknown values. Each row's key documents
	 * the case under test so failures point at the specific scenario.
	 *
	 * @dataProvider get_by_status_filter_matrix
	 */
	public function test_get_by_status_filter_behavior( array $statuses, array $expected_ids ) {
		// Shared fixture: one of each terminal status.
		$this->store->create( 'p' );
		$this->store->create( 'f1' );
		$this->store->set_status( 'f1', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED );
		$this->store->create( 'f2' );
		$this->store->set_status( 'f2', WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED );
		$this->store->create( 'a' );
		$this->store->set_status( 'a', WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED );

		$ids = array_column( $this->store->get_by_status( $statuses ), 'id' );
		sort( $ids );
		$this->assertSame( $expected_ids, $ids );
	}

	public function get_by_status_filter_matrix(): array {
		$failed    = WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
		$abandoned = WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED;
		$completed = WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED;

		return [
			'single status returns only matching traces' => [ [ $failed ], [ 'f1', 'f2' ] ],
			'multiple statuses union-match'              => [ [ $failed, $abandoned ], [ 'a', 'f1', 'f2' ] ],
			'no matches returns empty list'              => [ [ $completed ], [] ],
			'unknown status values are dropped'          => [ [ 'nonsense', $failed ], [ 'f1', 'f2' ] ],
			'all-unknown status drops to empty filter'   => [ [ 'nonsense' ], [] ],
		];
	}

	public function test_fifo_eviction_at_trace_cap() {
		$cap      = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_TRACES', 'int' );
		$overflow = 3;
		for ( $i = 0; $i < $cap + $overflow; $i++ ) {
			$this->store->create( 'id' . $i );
		}
		$ids = $this->store->get_all_ids();
		$this->assertCount( $cap, $ids );
		// First $overflow ids should have been evicted.
		$this->assertSame( 'id' . $overflow, $ids[0] );
		$this->assertNull( $this->store->get( 'id0' ) );
	}

	public function test_delete_removes_trace_and_index_entry() {
		$this->store->create( 'gone' );
		$this->assertTrue( $this->store->delete( 'gone' ) );
		$this->assertNull( $this->store->get( 'gone' ) );
		$this->assertNotContains( 'gone', $this->store->get_all_ids() );
	}

	public function test_delete_all_returns_removed_count_and_empties_store() {
		$this->store->create( 'a' );
		$this->store->create( 'b' );
		$this->store->create( 'c' );

		$this->assertSame( 3, $this->store->delete_all() );
		$this->assertSame( 0, $this->store->count() );
		$this->assertSame( [], $this->store->get_all_ids() );

		// Empty store: nothing to delete, count is zero.
		$this->assertSame( 0, $this->store->delete_all() );
	}

	public function test_sanitize_id_strips_unsafe_chars() {
		$this->assertSame( 'abc_123-XYZ', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( 'abc_123-XYZ' ) );
		$this->assertSame( 'abc', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( 'a/b;c' ) );
		$this->assertSame( '', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( '@#$' ) );
		$this->assertSame( '', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( null ) );
	}

	public function test_get_trace_path_returns_file_on_disk_for_existing_trace() {
		$this->store->create( 'on-disk', [ 'source' => 'checkout' ] );
		$path = $this->store->get_trace_path( 'on-disk' );
		$this->assertNotNull( $path );
		$this->assertFileExists( $path );
		$decoded = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 'on-disk', $decoded['id'] );
		$this->assertSame( 'checkout', $decoded['meta']['source'] );
	}

	public function test_get_trace_path_returns_null_for_invalid_id() {
		$this->assertNull( $this->store->get_trace_path( '@#$' ) );
	}

	public function test_is_full_reflects_trace_count() {
		$this->assertFalse( $this->store->is_full() );
		for ( $i = 0; $i < WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Diagnostics_Trace_Store::class, 'MAX_TRACES', 'int' ); $i++ ) {
			$this->store->create( 'f' . $i );
		}
		$this->assertTrue( $this->store->is_full() );
	}

	/**
	 * `set_order_id` is first-writer-wins so a webhook arriving after the
	 * checkout flow can't clobber the order id captured during the API
	 * request flow. Both writes return true (the second is a no-op success).
	 */
	public function test_set_order_id_is_first_writer_wins() {
		$this->store->create( 'sess' );

		$this->assertTrue( $this->store->set_order_id( 'sess', 100 ) );
		$this->assertSame( 100, $this->store->get( 'sess' )['meta']['order_id'] );

		$this->assertTrue( $this->store->set_order_id( 'sess', 200 ) );
		$this->assertSame( 100, $this->store->get( 'sess' )['meta']['order_id'] );
	}

	public function test_set_order_id_rejects_invalid_inputs() {
		$this->store->create( 'sess' );

		$this->assertFalse( $this->store->set_order_id( 'sess', 0 ) );
		$this->assertFalse( $this->store->set_order_id( 'sess', -5 ) );
		$this->assertFalse( $this->store->set_order_id( '@@@', 100 ) );

		$trace = $this->store->get( 'sess' );
		$this->assertArrayNotHasKey( 'order_id', $trace['meta'] ?? [] );
	}
}
