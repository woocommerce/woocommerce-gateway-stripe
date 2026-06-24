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
	 * Resolved value of the private LAST_UPLOAD_OPTION constant on the integration
	 * class, cached so tests can read/write the dedup record without exposing the
	 * constant publicly just for test access.
	 *
	 * @var string
	 */
	private string $last_upload_option;

	/**
	 * Resolved value of the protected OPTION_NAME constant on the product filter,
	 * cached so include-injection tests can seed input state directly without
	 * exposing the constant publicly.
	 *
	 * @var string
	 */
	private string $product_filter_option;

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

		$this->last_upload_option = WC_Stripe_Test_Helper::get_class_const_value( \WC_Stripe_Agentic_Commerce_Integration::class, 'LAST_UPLOAD_OPTION', 'string' );

		$this->product_filter_option = WC_Stripe_Test_Helper::get_class_const_value( \WC_Stripe_Agentic_Commerce_Product_Filter::class, 'OPTION_NAME', 'string' );
	}

	/**
	 * Reset cross-test state. Runs after every test (including failed ones)
	 * so assertion failures don't leak dedup state into the next case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( $this->last_upload_option );
		delete_option( $this->product_filter_option );
		remove_all_filters( 'wc_stripe_agentic_commerce_feed_dedupe_enabled' );
		remove_all_filters( 'wc_stripe_agentic_commerce_product_filter' );
		remove_all_filters( 'wc_stripe_agentic_commerce_product_query_args' );
		parent::tearDown();
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
	}

	/**
	 * No product-filter option set means no `include` key — the walker should
	 * iterate every published simple/variation product as it did before the
	 * filter abstraction landed.
	 *
	 * @return void
	 */
	public function test_get_product_feed_query_args_omits_include_when_filter_not_configured() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$args        = $integration->get_product_feed_query_args();

		$this->assertArrayNotHasKey( 'include', $args );
	}

	public function test_query_args_filter_runs(): void {
		update_option(
			$this->product_filter_option,
			[
				'category_ids' => [ 999999999 ],
			]
		);

		$observed_tax_query = null;
		add_filter(
			'wc_stripe_agentic_commerce_product_query_args',
			function ( $args ) use ( &$observed_tax_query ) {
				$observed_tax_query = $args['tax_query'] ?? null;
				$args['include']    = [ 42 ];
				return $args;
			}
		);

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$args        = $integration->get_product_feed_query_args();

		$this->assertIsArray( $observed_tax_query, 'A taxonomy query should be present in the query args.' );
		$this->assertSame( [ 42 ], $args['include'], 'Filter overrides the "include" query argument.' );
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
	 * The walker (via ProductWalker::from_integration) and the post-walk
	 * caller both go through get_feed_validator(); they must end up with
	 * the same instance so the validator's accumulated per-product errors
	 * are observable from the caller after the walk.
	 *
	 * @return void
	 */
	public function test_get_feed_validator_returns_same_instance_within_sync() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();

		$first  = $integration->get_feed_validator();
		$second = $integration->get_feed_validator();

		$this->assertSame( $first, $second );
	}

	/**
	 * Each sync_feed() run must start with a clean validator — otherwise a
	 * previous sync's accumulated errors would leak into the current one.
	 * Verifying via the early-return path (feature disabled) keeps the test
	 * isolated from the rest of the sync pipeline.
	 *
	 * @return void
	 */
	public function test_sync_feed_resets_cached_validator() {
		delete_option( 'woocommerce_stripe_settings' );

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$before      = $integration->get_feed_validator();

		$integration->sync_feed();

		$after = $integration->get_feed_validator();

		$this->assertNotSame( $before, $after );
	}

	/**
	 * An excluded product must stay out of the feed without counting as a
	 * validation skip: `skipped_products` (which drives the "Partial success"
	 * badge) must persist as 0 for a pure exclusion.
	 *
	 * @return void
	 */
	public function test_sync_feed_does_not_count_excluded_product_as_validation_skip() {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce product/Action Scheduler not available.' );
		}

		update_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME, 'yes' );
		// Secret key lives in settings (test mode); check_setup() gates on it.
		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']        = 'yes';
		$settings['test_secret_key'] = 'sk_test_fake';
		update_option( 'woocommerce_stripe_settings', $settings );

		// Give the kept product a category so it yields a valid row — a bare
		// product fails the feed's category requirement.
		$term   = wp_insert_term( 'Stripe Test Cat ' . uniqid(), 'product_cat' );
		$cat_id = is_wp_error( $term ) ? 0 : (int) $term['term_id'];

		$kept = new WC_Product_Simple();
		$kept->set_name( 'Kept Product' );
		$kept->set_regular_price( '10.00' );
		$kept->set_status( 'publish' );
		if ( $cat_id ) {
			$kept->set_category_ids( [ $cat_id ] );
		}
		$kept->save();

		$excluded = new WC_Product_Simple();
		$excluded->set_name( 'Excluded Product' );
		$excluded->set_regular_price( '20.00' );
		$excluded->set_status( 'publish' );
		$excluded->save();

		$excluded_id = $excluded->get_id();
		$filter      = static fn( $sync, $candidate ) => $candidate->get_id() !== $excluded_id;
		add_filter( 'woocommerce_agentic_commerce_should_sync_product', $filter, 10, 2 );

		// Scope the walk to these two products so other tests' leftovers don't
		// skew the counts.
		$kept_id = $kept->get_id();
		$scope   = static function ( $args ) use ( $kept_id, $excluded_id ) {
			$args['include'] = [ $kept_id, $excluded_id ];
			return $args;
		};
		add_filter( 'wc_stripe_agentic_commerce_product_query_args', $scope );

		$files_stub = fn() => [ 'id' => 'file_stub' ];
		add_filter( 'wc_stripe_agentic_commerce_files_api_pre_request', $files_stub, 10, 2 );

		$http_stub = fn() => [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'headers'  => [],
			'body'     => wp_json_encode(
				[
					'id'     => 'impset_stub',
					'status' => 'pending',
				]
			),
		];
		add_filter( 'pre_http_request', $http_stub, 10, 3 );

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			// force_upload so the store_sync_result path always runs regardless of dedup.
			$result = $integration->sync_feed( true );

			$this->assertTrue( $result, 'Sync should succeed — the kept product yields a valid feed row.' );

			$last_sync = \WC_Stripe_Agentic_Commerce_Integration::get_last_sync();
			$this->assertSame( 0, (int) $last_sync['skipped_products'], 'An excluded product must not be counted as a validation skip.' );
			$this->assertSame( 1, (int) $last_sync['products'], 'Only the kept product belongs in the feed; the excluded one is dropped.' );
			$this->assertNotSame( 'succeeded_with_errors', $last_sync['status'], 'A pure exclusion must not flip the sync to Partial success.' );
		} finally {
			remove_filter( 'woocommerce_agentic_commerce_should_sync_product', $filter, 10 );
			remove_filter( 'wc_stripe_agentic_commerce_product_query_args', $scope );
			remove_filter( 'wc_stripe_agentic_commerce_files_api_pre_request', $files_stub, 10 );
			remove_filter( 'pre_http_request', $http_stub, 10 );
			delete_option( WC_Stripe_Feature_Flags::AGENTIC_COMMERCE_FEATURE_FLAG_NAME );
			delete_option( 'woocommerce_stripe_settings' );
			$kept->delete( true );
			$excluded->delete( true );
			if ( $cat_id ) {
				wp_delete_term( $cat_id, 'product_cat' );
			}
		}
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
		$this->assertNotFalse(
			has_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $integration, 'schedule_full_resync_now' ] )
		);
		$this->assertNotFalse(
			has_filter( 'woocommerce_payment_complete_allowed_created_via_values', [ $integration, 'allow_agentic_payment_complete' ] )
		);

		$allowed = apply_filters( 'woocommerce_payment_complete_allowed_created_via_values', [], null );
		$this->assertContains( \WC_Stripe_Agentic_Commerce_Order_Mapper::CREATED_VIA, $allowed );

		remove_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $integration, 'schedule_full_resync_now' ] );
		remove_filter( 'woocommerce_payment_complete_allowed_created_via_values', [ $integration, 'allow_agentic_payment_complete' ] );
	}

	/**
	 * Adapter-fired resync action must enqueue an async Action Scheduler job
	 * the first time it fires, and become a no-op while that job is still
	 * pending so repeated visibility-setting saves don't stack queue entries.
	 */
	public function test_schedule_full_resync_now_enqueues_once_when_idle() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$integration->register_hooks();

		do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );
		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
			'First firing must enqueue an async sync in the resync group.'
		);

		// Second firing while the first job is still pending must be a no-op —
		// Action Scheduler would otherwise stack duplicate entries.
		$pending_before = as_get_scheduled_actions(
			[
				'hook'   => \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
				'group'  => 'wc-stripe-agentic-resync',
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );
		$pending_after = as_get_scheduled_actions(
			[
				'hook'   => \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
				'group'  => 'wc-stripe-agentic-resync',
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		$this->assertSame(
			count( $pending_before ),
			count( $pending_after ),
			'Repeated calls while a sync is pending must not stack queue entries.'
		);

		remove_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $integration, 'schedule_full_resync_now' ] );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );
	}

	/**
	 * Adapter-fired resync action must still enqueue a one-off async sync when
	 * the recurring full-feed occurrence is already pending — they live in
	 * separate Action Scheduler groups so the idempotency guard does not match
	 * across them. Without group scoping, a merchant changing a visibility
	 * setting between cron ticks would never trigger convergence on Stripe.
	 */
	public function test_schedule_full_resync_now_still_enqueues_when_recurring_is_pending() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );

		// Pre-schedule the recurring occurrence in the wc-stripe group, mirroring
		// what activate() does on a freshly-installed site.
		as_schedule_recurring_action(
			time() + HOUR_IN_SECONDS,
			\WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL,
			\WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
			[],
			'wc-stripe'
		);
		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' ),
			'Sanity: the recurring schedule must be pending before the adapter fires.'
		);

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$integration->register_hooks();

		do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );

		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
			'Adapter-fired one-off must land in the resync group even when the recurring occurrence is pending.'
		);

		// Firing again while the one-off is pending still must not stack
		// duplicates — the in-group idempotency guard handles that.
		$pending_before = as_get_scheduled_actions(
			[
				'hook'   => \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
				'group'  => 'wc-stripe-agentic-resync',
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		do_action( 'wc_stripe_agentic_commerce_schedule_full_resync' );
		$pending_after = as_get_scheduled_actions(
			[
				'hook'   => \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
				'group'  => 'wc-stripe-agentic-resync',
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		$this->assertSame(
			count( $pending_before ),
			count( $pending_after ),
			'Repeated calls while the resync is pending must not stack queue entries.'
		);

		remove_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $integration, 'schedule_full_resync_now' ] );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );
	}

	/**
	 * `deactivate()` must clear pending occurrences from BOTH groups. The
	 * recurring full-feed schedule lives in `wc-stripe`; the adapter-fired
	 * one-off resync lives in `wc-stripe-agentic-resync`. A deactivate that
	 * only cleared one group would leave orphaned actions firing against a
	 * disabled integration.
	 */
	public function test_deactivate_clears_both_scheduled_action_groups() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );

		// Seed both groups: the recurring occurrence (as activate() would) and
		// the one-off resync (as the adapter-fired action would).
		as_schedule_recurring_action(
			time() + HOUR_IN_SECONDS,
			\WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL,
			\WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
			[],
			'wc-stripe'
		);
		as_enqueue_async_action(
			\WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
			[],
			'wc-stripe-agentic-resync'
		);
		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' ),
			'Sanity: recurring action must be pending before deactivate().'
		);
		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
			'Sanity: one-off resync must be pending before deactivate().'
		);

		( new \WC_Stripe_Agentic_Commerce_Integration() )->deactivate();

		$this->assertFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' ),
			'deactivate() must clear the recurring action from the wc-stripe group.'
		);
		$this->assertFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
			'deactivate() must clear the one-off resync from the wc-stripe-agentic-resync group.'
		);
	}

	/**
	 * `cancel_pending_full_resync()` must drop a queued adapter-fired one-off
	 * from the `wc-stripe-agentic-resync` group while leaving the recurring
	 * full-feed occurrence in the `wc-stripe` group untouched. A manual sync
	 * calls this so the redundant one-off does not fire right after, but it must
	 * not collaterally cancel the recurring schedule the manual sync just reset.
	 */
	public function test_cancel_pending_full_resync_clears_only_the_resync_group() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );

		// Seed both groups: the recurring occurrence and the adapter-fired one-off.
		as_schedule_recurring_action(
			time() + HOUR_IN_SECONDS,
			\WC_Stripe_Agentic_Commerce_Integration::SYNC_INTERVAL,
			\WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
			[],
			'wc-stripe'
		);
		as_enqueue_async_action(
			\WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION,
			[],
			'wc-stripe-agentic-resync'
		);
		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
			'Sanity: one-off resync must be pending before the cancel.'
		);

		( new \WC_Stripe_Agentic_Commerce_Integration() )->cancel_pending_full_resync();

		$this->assertFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
			'cancel_pending_full_resync() must clear the one-off resync.'
		);
		$this->assertNotFalse(
			as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' ),
			'The recurring wc-stripe occurrence must survive — only the resync group is cleared.'
		);

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
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

	/**
	 * Test get_feed_hash returns SHA-256 of file contents.
	 *
	 * @return void
	 */
	public function test_get_feed_hash_matches_sha256_of_file() {
		$tmp = tempnam( sys_get_temp_dir(), 'wc-stripe-feed-' );
		$this->assertNotFalse( $tmp, 'tempnam() returned false; cannot run the test.' );
		file_put_contents( $tmp, "id,title\n1,Widget\n" );

		try {
			$integration = new \WC_Stripe_Agentic_Commerce_Integration();
			$get_hash    = new \ReflectionMethod( \WC_Stripe_Agentic_Commerce_Integration::class, 'get_feed_hash' );
			$get_hash->setAccessible( true );

			$this->assertSame( hash_file( 'sha256', $tmp ), $get_hash->invoke( $integration, $tmp ) );
		} finally {
			unlink( $tmp );
		}
	}

	/**
	 * Test get_feed_hash returns empty string when file is missing or unreadable.
	 *
	 * @return void
	 */
	public function test_get_feed_hash_returns_empty_when_file_missing() {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$get_hash    = new \ReflectionMethod( \WC_Stripe_Agentic_Commerce_Integration::class, 'get_feed_hash' );
		$get_hash->setAccessible( true );

		$this->assertSame( '', $get_hash->invoke( $integration, '' ) );
		$this->assertSame( '', $get_hash->invoke( $integration, '/nonexistent/path/feed.csv' ) );
	}

	/**
	 * Lock in the dedup contract: `is_feed_unchanged` only short-circuits when
	 * we have a well-formed, fresh, hash-matching record AND the kill-switch
	 * filter is on. Every other shape (missing record, mismatching hash,
	 * expired record, malformed record, filter disabled) has to fall through
	 * so we re-upload.
	 *
	 * @dataProvider provide_is_feed_unchanged_scenarios
	 *
	 * @param array|string|null $cached_record  Value to write to the dedup option, or null to leave it unset.
	 * @param string            $candidate_hash Hash to compare against the cached record.
	 * @param bool              $filter_enabled Whether the dedup kill-switch filter is on (true) or off (false).
	 * @param bool              $expected       Expected return from `is_feed_unchanged`.
	 */
	public function test_is_feed_unchanged_scenarios( $cached_record, string $candidate_hash, bool $filter_enabled, bool $expected ) {
		if ( null !== $cached_record ) {
			update_option( $this->last_upload_option, $cached_record, false );
		}
		if ( ! $filter_enabled ) {
			add_filter( 'wc_stripe_agentic_commerce_feed_dedupe_enabled', '__return_false' );
		}

		$integration  = new \WC_Stripe_Agentic_Commerce_Integration();
		$is_unchanged = new \ReflectionMethod( \WC_Stripe_Agentic_Commerce_Integration::class, 'is_feed_unchanged' );
		$is_unchanged->setAccessible( true );

		$this->assertSame( $expected, $is_unchanged->invoke( $integration, $candidate_hash ) );
	}

	public function provide_is_feed_unchanged_scenarios(): array {
		$fresh_record = [
			'hash'        => 'abc123',
			'uploaded_at' => time(),
			'file_id'     => 'file_test',
		];

		return [
			'no cached record falls through'              => [ null, 'abc123', true, false ],
			'fresh hash match short-circuits'             => [ $fresh_record, 'abc123', true, true ],
			'hash mismatch falls through'                 => [ $fresh_record, 'different_hash', true, false ],
			'expired record forces fresh upload'          => [
				[
					'hash'        => 'abc123',
					'uploaded_at' => time() - ( 2 * WEEK_IN_SECONDS ),
					'file_id'     => 'file_test',
				],
				'abc123',
				true,
				false,
			],
			'kill-switch filter forces fresh upload'      => [ $fresh_record, 'abc123', false, false ],
			'malformed cached record is tolerated'        => [ 'not_an_array', 'abc123', true, false ],
			'missing uploaded_at forces fresh upload'     => [
				[
					'hash'    => 'abc123',
					'file_id' => 'file_test',
				],
				'abc123',
				true,
				false,
			],
			'non-numeric uploaded_at forces fresh upload' => [
				[
					'hash'        => 'abc123',
					'uploaded_at' => 'not-a-timestamp',
					'file_id'     => 'file_test',
				],
				'abc123',
				true,
				false,
			],
			'zero uploaded_at forces fresh upload'        => [
				[
					'hash'        => 'abc123',
					'uploaded_at' => 0,
					'file_id'     => 'file_test',
				],
				'abc123',
				true,
				false,
			],
		];
	}

	/**
	 * Test remember_feed_upload persists hash, timestamp, and file_id.
	 *
	 * @return void
	 */
	public function test_remember_feed_upload_persists_expected_fields() {
		delete_option( $this->last_upload_option );

		$integration = new \WC_Stripe_Agentic_Commerce_Integration();
		$remember    = new \ReflectionMethod( \WC_Stripe_Agentic_Commerce_Integration::class, 'remember_feed_upload' );
		$remember->setAccessible( true );

		$remember->invoke(
			$integration,
			'abc123',
			[
				'file_id'       => 'file_123',
				'import_set_id' => 'imp_456',
				'status'        => 'processed',
			]
		);

		$record = get_option( $this->last_upload_option );
		$this->assertIsArray( $record );
		$this->assertSame( 'abc123', $record['hash'] );
		$this->assertSame( 'file_123', $record['file_id'] );
		$this->assertSame( 'imp_456', $record['import_set_id'] );
		$this->assertIsInt( $record['uploaded_at'] );
		$this->assertLessThanOrEqual( time(), $record['uploaded_at'] );

		delete_option( $this->last_upload_option );
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
			'products'         => 100,
			'status'           => 'succeeded',
			'file_id'          => 'file_abc',
			'import_set_id'    => 'impset_xyz',
			'error'            => '',
			'skipped_products' => 3,
		];

		$integration->store_sync_result( $result );

		$history   = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );
		$last_sync = get_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );

		$this->assertCount( 1, $history );
		$this->assertEquals( 100, $history[0]['products'] );
		$this->assertEquals( 'succeeded', $history[0]['status'] );
		$this->assertEquals( 'impset_xyz', $history[0]['import_set_id'] );
		$this->assertSame( 3, $history[0]['skipped_products'] );
		$this->assertArrayHasKey( 'timestamp', $history[0] );

		$this->assertEquals( $history[0], $last_sync );
	}

	/**
	 * store_sync_result defaults skipped_products to 0 when the caller omits
	 * the field, so older entries written before the field existed survive
	 * the refresh-time upgrade rule without breaking the partial-success path.
	 *
	 * @return void
	 */
	public function test_store_sync_result_defaults_skipped_products_to_zero(): void {
		$integration = new \WC_Stripe_Agentic_Commerce_Integration();

		$integration->store_sync_result(
			[
				'products'      => 1,
				'status'        => 'pending',
				'file_id'       => 'file_a',
				'import_set_id' => 'impset_a',
				'error'         => '',
			]
		);

		$history = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		$this->assertSame( 0, $history[0]['skipped_products'] );
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
	 * normalize_delivery_status() must:
	 *   - Pass through whatever Stripe returns when present.
	 *   - Default to `pending` when an import_set_id was returned but no status,
	 *     so the dashboard's lazy refresh keeps polling instead of getting
	 *     stuck on "Unknown".
	 *   - Fall back to `unknown` only when delivery failed outright (no
	 *     import_set_id returned).
	 *   - Upgrade a Stripe-reported `succeeded` to `succeeded_with_errors`
	 *     whenever the local validator dropped products, so the
	 *     "Partial Success" badge matches the warning log.
	 *
	 * @dataProvider provider_normalize_delivery_status
	 *
	 * @param array  $result        Simulated delivery result from the Files API.
	 * @param int    $skipped_count Local-validator drops to fold into the result.
	 * @param string $expected      Normalized status that should be persisted.
	 * @return void
	 */
	public function test_normalize_delivery_status( array $result, int $skipped_count, string $expected ): void {
		$method = ( new \ReflectionClass( \WC_Stripe_Agentic_Commerce_Integration::class ) )
			->getMethod( 'normalize_delivery_status' );
		$method->setAccessible( true );

		$this->assertSame(
			$expected,
			$method->invoke( null, $result, $skipped_count )
		);
	}

	/**
	 * @return array<string, array{0: array, 1: int, 2: string}>
	 */
	public function provider_normalize_delivery_status(): array {
		return [
			'explicit status wins'                     => [
				[
					'status'        => 'succeeded',
					'import_set_id' => 'impset_ok',
				],
				0,
				'succeeded',
			],
			'created without status is pending'        => [
				[
					'status'        => '',
					'import_set_id' => 'impset_new',
				],
				0,
				'pending',
			],
			'missing status key is pending'            => [
				[ 'import_set_id' => 'impset_new' ],
				0,
				'pending',
			],
			'no import_set_id falls to unknown'        => [
				[
					'status'        => '',
					'import_set_id' => '',
				],
				0,
				'unknown',
			],
			'empty result is unknown'                  => [ [], 0, 'unknown' ],
			'succeeded with skips upgrades to partial' => [
				[
					'status'        => 'succeeded',
					'import_set_id' => 'impset_ok',
				],
				2,
				'succeeded_with_errors',
			],
			'failed status preserved despite skips'    => [
				[
					'status'        => 'failed',
					'import_set_id' => 'impset_ok',
				],
				1,
				'failed',
			],
			'pending preserved despite skips'          => [
				[ 'import_set_id' => 'impset_new' ],
				3,
				'pending',
			],
		];
	}

	/**
	 * update_pending_statuses rewrites entries whose stored status is non-terminal.
	 *
	 * The non-terminal set must match the controller's REFRESHABLE_STATUSES
	 * (`queued`, `validating`, `validating_records`, `pending`, `creating_records`, `unknown`);
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
			'queued is refreshable'             => [ 'queued', 'succeeded' ],
			'validating is refreshable'         => [ 'validating', 'succeeded' ],
			'validating_records is refreshable' => [ 'validating_records', 'succeeded' ],
			'pending is refreshable'            => [ 'pending', 'succeeded' ],
			'creating_records is refreshable'   => [ 'creating_records', 'succeeded' ],
			'unknown is refreshable'            => [ 'unknown', 'succeeded' ],
			'succeeded is terminal'             => [ 'succeeded', 'succeeded' ],
			'failed is terminal'                => [ 'failed', 'failed' ],
			'pending_archive is terminal'       => [ 'pending_archive', 'pending_archive' ],
			'archived is terminal'              => [ 'archived', 'archived' ],
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
			$this->assertEqualsWithDelta( $start_time, $next, 2 );
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
	 * reschedule_next_feed_sync() must recreate the recurring schedule even when
	 * a one-off resync action is pending. The two actions share a hook name but
	 * live in different groups -- we need to ignore the action in the other group.
	 */
	public function test_reschedule_next_feed_sync_schedules_recurring_action_when_async_resync_is_pending(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_unschedule_all_actions' ) || ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'did_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
		as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION );

		try {
			as_enqueue_async_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );

			$this->assertNotFalse(
				as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' ),
				'Sanity: the one-off resync must be pending before rescheduling.'
			);
			$this->assertFalse(
				as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' ),
				'Sanity: no recurring action should exist before rescheduling.'
			);

			$result = ( new \WC_Stripe_Agentic_Commerce_Integration() )->reschedule_next_feed_sync();

			$this->assertTrue( $result );
			$this->assertNotFalse(
				as_has_scheduled_action( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' ),
				'The recurring wc-stripe schedule must be created even when an async resync is pending.'
			);
			$this->assertEquals( 'yes', get_option( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION ) );
		} finally {
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe' );
			as_unschedule_all_actions( \WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION, [], 'wc-stripe-agentic-resync' );
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

	// -------------------------------------------------------------------------
	// update_pending_statuses
	// -------------------------------------------------------------------------

	/**
	 * When the dashboard's refresh poll observes Stripe's terminal `succeeded`
	 * for an entry whose local validator dropped products at sync time, the
	 * stored status must upgrade to `succeeded_with_errors` so the
	 * "Partial Success" badge matches the warning logged for the skips.
	 * Stripe never reports `succeeded` synchronously at ImportSet creation,
	 * so this upgrade has to fire on the refresh path — not just inside
	 * normalize_delivery_status() at initial sync.
	 *
	 * @return void
	 */
	public function test_update_pending_statuses_upgrades_succeeded_to_partial_when_validator_dropped_products(): void {
		$history = [
			[
				'timestamp'        => time() - 60,
				'products'         => 7,
				'status'           => 'pending',
				'file_id'          => 'file_a',
				'import_set_id'    => 'impset_a',
				'error'            => '',
				'skipped_products' => 1,
			],
		];
		update_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		\WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses( [ 'impset_a' => 'succeeded' ] );

		$stored    = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );
		$last_sync = get_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_SYNC_OPTION, [] );

		$this->assertSame( 'succeeded_with_errors', $stored[0]['status'] );
		$this->assertSame( 'succeeded_with_errors', $last_sync['status'] );
	}

	/**
	 * Entries with skipped_products = 0 must store Stripe's `succeeded`
	 * verbatim — the upgrade only applies when the local validator actually
	 * dropped something.
	 *
	 * @return void
	 */
	public function test_update_pending_statuses_keeps_succeeded_when_no_skips_recorded(): void {
		$history = [
			[
				'timestamp'        => time() - 60,
				'products'         => 8,
				'status'           => 'pending',
				'file_id'          => 'file_a',
				'import_set_id'    => 'impset_a',
				'error'            => '',
				'skipped_products' => 0,
			],
		];
		update_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		\WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses( [ 'impset_a' => 'succeeded' ] );

		$stored = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		$this->assertSame( 'succeeded', $stored[0]['status'] );
	}

	/**
	 * Entries persisted before skipped_products existed must not crash the
	 * upgrade rule — missing field is treated as zero skips, so Stripe's
	 * `succeeded` flows through unchanged.
	 *
	 * @return void
	 */
	public function test_update_pending_statuses_treats_missing_skipped_products_as_zero(): void {
		$history = [
			[
				'timestamp'     => time() - 60,
				'products'      => 5,
				'status'        => 'pending',
				'file_id'       => 'file_legacy',
				'import_set_id' => 'impset_legacy',
				'error'         => '',
			],
		];
		update_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		\WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses( [ 'impset_legacy' => 'succeeded' ] );

		$stored = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		$this->assertSame( 'succeeded', $stored[0]['status'] );
	}

	/**
	 * The upgrade rule must not interfere with non-`succeeded` transitions:
	 * `failed` reported by Stripe stays `failed` even when the local validator
	 * dropped products.
	 *
	 * @return void
	 */
	public function test_update_pending_statuses_does_not_upgrade_failed_status(): void {
		$history = [
			[
				'timestamp'        => time() - 60,
				'products'         => 7,
				'status'           => 'pending',
				'file_id'          => 'file_a',
				'import_set_id'    => 'impset_a',
				'error'            => '',
				'skipped_products' => 2,
			],
		];
		update_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, $history );

		\WC_Stripe_Agentic_Commerce_Integration::update_pending_statuses( [ 'impset_a' => 'failed' ] );

		$stored = get_option( \WC_Stripe_Agentic_Commerce_Integration::SYNC_HISTORY_OPTION, [] );

		$this->assertSame( 'failed', $stored[0]['status'] );
	}
}
