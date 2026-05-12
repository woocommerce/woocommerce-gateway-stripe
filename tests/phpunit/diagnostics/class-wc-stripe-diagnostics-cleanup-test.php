<?php

/**
 * Tests for WC_Stripe_Diagnostics_Cleanup.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Cleanup_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * @var WC_Stripe_Diagnostics_Cleanup
	 */
	private $cleanup;

	/**
	 * Reset state and build a fresh cleanup runner before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->store   = new WC_Stripe_Diagnostics_Trace_Store();
		$this->cleanup = new WC_Stripe_Diagnostics_Cleanup( $this->store );
		$this->clear_state();
	}

	/**
	 * Wipe traces written during the test so the next case starts clean.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->clear_state();
		parent::tear_down();
	}

	/**
	 * Drop every trace from the underlying store.
	 *
	 * @return void
	 */
	private function clear_state() {
		$this->store->delete_all();
	}

	/**
	 * Force the stored `created_at` / `updated_at` timestamps of a trace by
	 * rewriting its on-disk JSON. The store doesn't expose a setter for these
	 * fields (they're recorded once at create-time and on append), so the
	 * test needs to reach into the file directly to simulate aging.
	 *
	 * @param string $id         Session id of the trace to mutate.
	 * @param int    $created_at New `created_at` epoch seconds.
	 * @param int    $updated_at New `updated_at` epoch seconds.
	 * @return void
	 */
	private function set_times( string $id, int $created_at, int $updated_at ): void {
		$trace               = $this->store->get( $id );
		$trace['created_at'] = $created_at;
		$trace['updated_at'] = $updated_at;
		file_put_contents( $this->store->get_trace_path( $id ), wp_json_encode( $trace ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Traces older than the 7-day TTL are deleted; fresh traces are kept.
	 *
	 * @return void
	 */
	public function test_daily_cleanup_deletes_traces_older_than_7_days() {
		$this->store->create( 'old' );
		$this->store->create( 'fresh' );
		$this->set_times( 'old', time() - ( 8 * DAY_IN_SECONDS ), time() - ( 8 * DAY_IN_SECONDS ) );
		$this->set_times( 'fresh', time() - HOUR_IN_SECONDS, time() - HOUR_IN_SECONDS );

		$deleted = $this->cleanup->run_daily_cleanup();

		$this->assertSame( 1, $deleted );
		$this->assertNull( $this->store->get( 'old' ) );
		$this->assertNotNull( $this->store->get( 'fresh' ) );
	}

	/**
	 * A trace whose `created_at` sits inside the 7-day window — even by
	 * just a minute — is left alone by the daily sweep.
	 *
	 * @return void
	 */
	public function test_daily_cleanup_keeps_trace_exactly_at_edge() {
		$this->store->create( 'edge' );
		// 7 days minus a buffer: still within window.
		$this->set_times( 'edge', time() - ( 7 * DAY_IN_SECONDS - 60 ), time() - HOUR_IN_SECONDS );

		$deleted = $this->cleanup->run_daily_cleanup();

		$this->assertSame( 0, $deleted );
		$this->assertNotNull( $this->store->get( 'edge' ) );
	}
}
