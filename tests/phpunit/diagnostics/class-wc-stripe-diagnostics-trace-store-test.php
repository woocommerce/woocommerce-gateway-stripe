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
		$this->clear_all_traces();
	}

	public function tear_down() {
		$this->clear_all_traces();
		parent::tear_down();
	}

	private function clear_all_traces() {
		foreach ( $this->store->get_all_ids() as $id ) {
			$this->store->delete( $id );
		}
		delete_option( WC_Stripe_Diagnostics_Trace_Store::INDEX_OPTION );
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
		for ( $i = 0; $i < WC_Stripe_Diagnostics_Trace_Store::MAX_EVENTS_PER_TRACE; $i++ ) {
			$this->assertTrue( $this->store->append_event( 'capped', [ 'n' => $i ] ) );
		}
		$this->assertFalse( $this->store->append_event( 'capped', [ 'n' => 'overflow' ] ) );
		$trace = $this->store->get( 'capped' );
		$this->assertCount( WC_Stripe_Diagnostics_Trace_Store::MAX_EVENTS_PER_TRACE, $trace['events'] );
	}

	public function test_append_event_drops_past_size_cap() {
		$this->store->create( 'big' );
		$big_event = [ 'payload' => str_repeat( 'x', WC_Stripe_Diagnostics_Trace_Store::MAX_TRACE_BYTES + 1 ) ];
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

	public function test_fifo_eviction_at_trace_cap() {
		$cap      = WC_Stripe_Diagnostics_Trace_Store::MAX_TRACES;
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

	public function test_sanitize_id_strips_unsafe_chars() {
		$this->assertSame( 'abc_123-XYZ', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( 'abc_123-XYZ' ) );
		$this->assertSame( 'abc', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( 'a/b;c' ) );
		$this->assertSame( '', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( '@#$' ) );
		$this->assertSame( '', WC_Stripe_Diagnostics_Trace_Store::sanitize_id( null ) );
	}

	public function test_is_full_reflects_trace_count() {
		$this->assertFalse( $this->store->is_full() );
		for ( $i = 0; $i < WC_Stripe_Diagnostics_Trace_Store::MAX_TRACES; $i++ ) {
			$this->store->create( 'f' . $i );
		}
		$this->assertTrue( $this->store->is_full() );
	}
}
