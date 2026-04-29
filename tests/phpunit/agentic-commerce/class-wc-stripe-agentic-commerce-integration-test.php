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
	 * (`queued`, `validating`, `pending`, `creating_records`, `unknown`);
	 * entries in terminal statuses must not be mutated.
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
			'queued is refreshable'           => [ 'queued', 'succeeded' ],
			'validating is refreshable'       => [ 'validating', 'succeeded' ],
			'pending is refreshable'          => [ 'pending', 'succeeded' ],
			'creating_records is refreshable' => [ 'creating_records', 'succeeded' ],
			'unknown is refreshable'          => [ 'unknown', 'succeeded' ],
			'succeeded is terminal'           => [ 'succeeded', 'succeeded' ],
			'failed is terminal'              => [ 'failed', 'failed' ],
		];
	}
}
