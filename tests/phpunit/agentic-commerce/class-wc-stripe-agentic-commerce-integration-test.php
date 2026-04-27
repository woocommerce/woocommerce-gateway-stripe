<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Integration
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Integration_Test
 *
 * Tests the main integration class for Agentic Commerce.
 */
class WC_Stripe_Agentic_Commerce_Integration_Test extends WP_UnitTestCase {
	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce IntegrationInterface not available (requires WooCommerce 10.5.0+)' );
		}

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Integration class not loaded' );
		}
	}

	/**
	 * Test get_id returns correct identifier.
	 *
	 * @return void
	 */
	public function test_get_id() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$this->assertEquals( 'stripe-agentic-commerce', $integration->get_id() );
	}

	/**
	 * Test get_product_feed_query_args returns expected types and status.
	 *
	 * @return void
	 */
	public function test_get_product_feed_query_args() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$args        = $integration->get_product_feed_query_args();

		$this->assertArrayHasKey( 'type', $args );
		$this->assertArrayHasKey( 'status', $args );
		$this->assertContains( 'simple', $args['type'] );
		$this->assertContains( 'variation', $args['type'] );
		$this->assertContains( 'publish', $args['status'] );
	}

	/**
	 * Test query args can be filtered.
	 *
	 * @return void
	 */
	public function test_get_product_feed_query_args_filterable() {
		add_filter(
			'wc_stripe_agentic_commerce_product_query_args',
			function ( $args ) {
				$args['type'] = [ 'simple' ];
				return $args;
			}
		);

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$args        = $integration->get_product_feed_query_args();

		$this->assertEquals( [ 'simple' ], $args['type'] );

		remove_all_filters( 'wc_stripe_agentic_commerce_product_query_args' );
	}

	/**
	 * Test create_feed returns a CSV feed instance.
	 *
	 * @return void
	 */
	public function test_create_feed() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$feed        = $integration->create_feed();

		$this->assertInstanceOf( \WC_Stripe_Agentic_Commerce_Csv_Feed::class, $feed );
	}

	/**
	 * Test get_product_mapper returns a mapper instance.
	 *
	 * @return void
	 */
	public function test_get_product_mapper() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$mapper      = $integration->get_product_mapper();

		$this->assertInstanceOf( \WC_Stripe_Agentic_Commerce_Product_Mapper::class, $mapper );
	}

	/**
	 * Test get_feed_validator returns a validator instance.
	 *
	 * @return void
	 */
	public function test_get_feed_validator() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$validator   = $integration->get_feed_validator();

		$this->assertInstanceOf( \WC_Stripe_Agentic_Commerce_Feed_Validator::class, $validator );
	}

	/**
	 * Test is_enabled returns false by default.
	 *
	 * @return void
	 */
	public function test_is_enabled_default_false() {
		delete_option( 'woocommerce_stripe_settings' );

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$this->assertFalse( $integration->is_enabled() );
	}

	/**
	 * Test is_enabled returns true when filter enables it.
	 *
	 * @return void
	 */
	public function test_is_enabled_when_filter_active() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$this->assertTrue( $integration->is_enabled() );

		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Test register_hooks adds the scheduled action hook.
	 *
	 * @return void
	 */
	public function test_register_hooks() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$integration->register_hooks();

		$this->assertNotFalse(
			has_action( 'wc_stripe_agentic_commerce_sync_feed', [ $integration, 'sync_feed' ] )
		);
	}

	/**
	 * Test sync_feed skips when feature is disabled.
	 *
	 * @return void
	 */
	public function test_sync_feed_skips_when_disabled() {
		delete_option( 'woocommerce_stripe_settings' );

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();

		// Should not throw - just returns early.
		$integration->sync_feed();

		// If we got here without error, the early return worked.
		$this->assertFalse( $integration->is_enabled() );
	}

	/**
	 * Test constants are defined correctly.
	 *
	 * @return void
	 */
	public function test_constants() {
		$this->assertEquals( 'stripe-agentic-commerce', \WC_Stripe_Agentic_Commerce_Integration::ID );
		$this->assertEquals( 'wc_stripe_agentic_commerce_sync_feed', \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );
		$this->assertEquals( 900, \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL ); // 15 * 60
		$this->assertEquals( 'wc_stripe_agentic_commerce_enabled', \WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
	}

	/**
	 * Test is_merchant_enabled returns false when option is not set.
	 *
	 * @return void
	 */
	public function test_is_merchant_enabled_default_false() {
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );

		$this->assertFalse( \WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() );
	}

	/**
	 * Test is_merchant_enabled returns true when option is set to yes.
	 *
	 * @return void
	 */
	public function test_is_merchant_enabled_returns_true_when_set() {
		update_option( \WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );

		$this->assertTrue( \WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
	}

	/**
	 * Test is_merchant_enabled returns false when option is set to no.
	 *
	 * @return void
	 */
	public function test_is_merchant_enabled_returns_false_when_disabled() {
		update_option( \WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );

		$this->assertFalse( \WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
	}

	// -------------------------------------------------------------------------
	// store_sync_result
	// -------------------------------------------------------------------------

	/**
	 * store_sync_result persists an entry in the history option and updates last sync.
	 *
	 * @return void
	 */
	public function test_store_sync_result_persists_entry(): void {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();

		$result = [
			'products'      => 100,
			'status'        => 'succeeded',
			'file_id'       => 'file_abc',
			'import_set_id' => 'impset_xyz',
			'error'         => '',
		];

		$integration->store_sync_result( $result );

		$history   = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );
		$last_sync = get_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );

		$this->assertCount( 1, $history );
		$this->assertEquals( 100, $history[0]['products'] );
		$this->assertEquals( 'succeeded', $history[0]['status'] );
		$this->assertEquals( 'impset_xyz', $history[0]['import_set_id'] );
		$this->assertArrayHasKey( 'timestamp', $history[0] );

		$this->assertEquals( $history[0], $last_sync );
	}

	/**
	 * store_sync_result caps history at SYNC_HISTORY_LIMIT entries.
	 *
	 * @return void
	 */
	public function test_store_sync_result_caps_history_at_limit(): void {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();

		// Pre-fill history at the limit.
		$history = [];
		for ( $i = 0; $i < \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_LIMIT; $i++ ) {
			$history[] = [
				'timestamp'     => time() - ( $i * 60 ),
				'products'      => $i,
				'status'        => 'succeeded',
				'file_id'       => "file_{$i}",
				'import_set_id' => "impset_{$i}",
				'error'         => '',
			];
		}
		update_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		// Add one more entry.
		$integration->store_sync_result(
			[
				'products'      => 999,
				'status'        => 'succeeded',
				'file_id'       => 'file_new',
				'import_set_id' => 'impset_new',
				'error'         => '',
			]
		);

		$stored = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		$this->assertCount( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_LIMIT, $stored );
		// The newest entry should be last.
		$this->assertEquals( 'impset_new', end( $stored )['import_set_id'] );
	}

	/**
	 * store_sync_result records error information.
	 *
	 * @return void
	 */
	public function test_store_sync_result_records_error(): void {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();

		$integration->store_sync_result(
			[
				'products'      => 0,
				'status'        => 'failed',
				'file_id'       => '',
				'import_set_id' => '',
				'error'         => 'Stripe API key not configured',
			]
		);

		$last_sync = get_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );

		$this->assertEquals( 'failed', $last_sync['status'] );
		$this->assertEquals( 'Stripe API key not configured', $last_sync['error'] );
	}

	/**
	 * update_pending_statuses rewrites entries whose stored status is non-terminal.
	 *
	 * The non-terminal set must match the controller's REFRESHABLE_STATUSES
	 * (`pending`, `creating_records`, `unknown`); entries in terminal statuses
	 * must not be mutated.
	 *
	 * @dataProvider provider_update_pending_statuses_rewrites_non_terminal_entries
	 *
	 * @param string $initial_status  Status initially persisted on the entry.
	 * @param string $expected_status Status expected after the update is applied.
	 * @return void
	 */
	public function test_update_pending_statuses_rewrites_non_terminal_entries( string $initial_status, string $expected_status ): void {
		$history = [
			[
				'timestamp'     => time() - 60,
				'products'      => 5,
				'status'        => $initial_status,
				'file_id'       => 'file_a',
				'import_set_id' => 'impset_a',
				'error'         => '',
			],
		];
		update_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		\WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses( [ 'impset_a' => 'succeeded' ] );

		$stored    = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );
		$last_sync = get_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );

		$this->assertEquals( $expected_status, $stored[0]['status'] );

		if ( $initial_status !== $expected_status ) {
			// Terminal transitions also refresh the LAST_SYNC_OPTION pointer.
			$this->assertEquals( $expected_status, $last_sync['status'] );
		}
	}

	/**
	 * Data provider for test_update_pending_statuses_rewrites_non_terminal_entries.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function provider_update_pending_statuses_rewrites_non_terminal_entries(): array {
		return [
			'pending is refreshable'          => [ 'pending', 'succeeded' ],
			'creating_records is refreshable' => [ 'creating_records', 'succeeded' ],
			'unknown is refreshable'          => [ 'unknown', 'succeeded' ],
			'succeeded is terminal'           => [ 'succeeded', 'succeeded' ],
			'failed is terminal'              => [ 'failed', 'failed' ],
		];
	}

	// -------------------------------------------------------------------------
	// get_feed_sync_interval
	// -------------------------------------------------------------------------

	/**
	 * Tests for {@method WC_Stripe_Agentic_Commerce_Integration::get_feed_sync_interval()}.
	 *
	 * @dataProvider provide_get_feed__sync_interval_tests
	 *
	 * @param mixed $invalid_value Value returned by the filter.
	 * @return void
	 */
	public function test_get_feed_sync_interval( $filter_value, int $expected_interval ): void {
		$filter_callback = null;

		if ( null !== $filter_value ) {
			$filter_callback = fn() => $filter_value;
			add_filter( 'wc_stripe_agentic_commerce_feed_sync_interval', $filter_callback );
		}

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();

			$this->assertSame(
				$expected_interval,
				$integration->get_feed_sync_interval()
			);
		} finally {
			if ( null !== $filter_callback ) {
				remove_filter( 'wc_stripe_agentic_commerce_feed_sync_interval', $filter_callback );
			}
		}
	}

	/**
	 * Data provider for {@method test_get_feed_sync_interval()}.
	 *
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public function provide_get_feed__sync_interval_tests(): array {
		return [
			'zero'      => [ 0, \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL ],
			'negative'  => [ -1, \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL ],
			'float'     => [ 1.5, \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL ],
			'string'    => [ 'invalid', \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL ],
			'no filter' => [ null, \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL ],
			'valid 600' => [ 600, 600 ],
			'valid 300' => [ 300, 300 ],
		];
	}

	// -------------------------------------------------------------------------
	// schedule_recurring_feed_sync
	// -------------------------------------------------------------------------

	/**
	 * schedule_recurring_feed_sync returns true and persists SCHEDULED_OPTION when Action Scheduler is available.
	 *
	 * @return void
	 */
	public function test_schedule_recurring_feed_sync_sets_option_and_returns_true(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			$result      = $integration->schedule_recurring_feed_sync();

			$this->assertTrue( $result );
			$this->assertEquals( 'yes', get_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION ) );
		} finally {
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );
		}
	}

	/**
	 * schedule_recurring_feed_sync uses the provided start time when scheduling the action.
	 *
	 * @return void
	 */
	public function test_schedule_recurring_feed_sync_uses_provided_start_time(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );

		$start_time = time() + 7200;

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			$integration->schedule_recurring_feed_sync( $start_time );

			$next = as_next_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );

			$this->assertNotFalse( $next, 'Expected an action to be scheduled.' );
			$this->assertEquals( $start_time, $next, '', 2 );
		} finally {
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );
		}
	}

	/**
	 * schedule_recurring_feed_sync does not add a second action when one is already pending.
	 *
	 * @return void
	 */
	public function test_schedule_recurring_feed_sync_does_not_duplicate_when_already_scheduled(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );

		$first_start  = time() + 1800;
		$second_start = time() + 9000;

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			$integration->schedule_recurring_feed_sync( $first_start );

			$next_after_first = as_next_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );

			// Second call with a different start_time should not displace the first.
			$integration->schedule_recurring_feed_sync( $second_start );

			$next_after_second = as_next_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );

			$this->assertEquals(
				$next_after_first,
				$next_after_second,
				'A second schedule_recurring_feed_sync call must not overwrite an existing scheduled action.'
			);
		} finally {
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );
		}
	}

	// -------------------------------------------------------------------------
	// reschedule_next_feed_sync
	// -------------------------------------------------------------------------

	/**
	 * reschedule_next_feed_sync clears any existing scheduled action and reschedules, returning true.
	 *
	 * @return void
	 */
	public function test_reschedule_next_feed_sync_clears_and_reschedules(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			$integration->schedule_recurring_feed_sync();

			$this->assertTrue(
				as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION ),
				'Pre-condition: action should be scheduled before reschedule.'
			);

			$result = $integration->reschedule_next_feed_sync();

			$this->assertTrue( $result );
			$this->assertTrue(
				as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION ),
				'Action should still be scheduled after reschedule.'
			);
			$this->assertEquals( 'yes', get_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION ) );
		} finally {
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );
		}
	}

	/**
	 * reschedule_next_feed_sync schedules the next run approximately one sync interval in the future.
	 *
	 * @return void
	 */
	public function test_reschedule_next_feed_sync_next_run_is_one_interval_ahead(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );

		try {
			$before      = time();
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			$integration->reschedule_next_feed_sync();

			$next = as_next_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );

			$this->assertNotFalse( $next, 'Expected an action to be scheduled after reschedule.' );

			$expected_floor = $before + \WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL;
			$expected_ceil  = $expected_floor + 5;

			$this->assertGreaterThanOrEqual( $expected_floor, $next );
			$this->assertLessThanOrEqual( $expected_ceil, $next );
		} finally {
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );
		}
	}
}
