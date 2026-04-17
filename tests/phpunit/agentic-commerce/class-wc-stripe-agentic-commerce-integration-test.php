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

		if ( ! class_exists( 'Stripe_Agentic_Commerce_Integration_Dedup_Harness' ) ) {
			require_once __DIR__ . '/class-stripe-agentic-commerce-integration-dedup-harness.php';
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

	/**
	 * Test get_feed_hash returns SHA-256 of file contents.
	 *
	 * @return void
	 */
	public function test_get_feed_hash_matches_sha256_of_file() {
		$tmp = tempnam( sys_get_temp_dir(), 'wc-stripe-feed-' );
		file_put_contents( $tmp, "id,title\n1,Widget\n" );

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertSame( hash_file( 'sha256', $tmp ), $integration->public_get_feed_hash( $tmp ) );

		unlink( $tmp );
	}

	/**
	 * Test get_feed_hash returns empty string when file is missing or unreadable.
	 *
	 * @return void
	 */
	public function test_get_feed_hash_returns_empty_when_file_missing() {
		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertSame( '', $integration->public_get_feed_hash( '' ) );
		$this->assertSame( '', $integration->public_get_feed_hash( '/nonexistent/path/feed.csv' ) );
	}

	/**
	 * Test is_feed_unchanged returns false on first run (no cached record).
	 *
	 * @return void
	 */
	public function test_is_feed_unchanged_returns_false_when_no_cached_upload() {
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertFalse( $integration->public_is_feed_unchanged( 'abc123' ) );
	}

	/**
	 * Test is_feed_unchanged returns true when hash matches a fresh cached upload.
	 *
	 * @return void
	 */
	public function test_is_feed_unchanged_returns_true_when_hash_matches() {
		update_option(
			\WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION,
			[
				'hash'        => 'abc123',
				'uploaded_at' => time(),
				'file_id'     => 'file_test',
			],
			false
		);

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertTrue( $integration->public_is_feed_unchanged( 'abc123' ) );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
	}

	/**
	 * Test is_feed_unchanged returns false when hashes differ.
	 *
	 * @return void
	 */
	public function test_is_feed_unchanged_returns_false_when_hash_differs() {
		update_option(
			\WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION,
			[
				'hash'        => 'abc123',
				'uploaded_at' => time(),
				'file_id'     => 'file_test',
			],
			false
		);

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertFalse( $integration->public_is_feed_unchanged( 'different_hash' ) );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
	}

	/**
	 * Test is_feed_unchanged returns false when the cached record is past the TTL,
	 * forcing a fresh upload as a safety valve.
	 *
	 * @return void
	 */
	public function test_is_feed_unchanged_returns_false_when_cache_expired() {
		update_option(
			\WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION,
			[
				'hash'        => 'abc123',
				'uploaded_at' => time() - ( 2 * WEEK_IN_SECONDS ),
				'file_id'     => 'file_test',
			],
			false
		);

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertFalse( $integration->public_is_feed_unchanged( 'abc123' ) );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
	}

	/**
	 * Test dedup can be disabled via filter.
	 *
	 * @return void
	 */
	public function test_is_feed_unchanged_respects_disable_filter() {
		update_option(
			\WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION,
			[
				'hash'        => 'abc123',
				'uploaded_at' => time(),
				'file_id'     => 'file_test',
			],
			false
		);

		add_filter( 'wc_stripe_agentic_commerce_dedup_enabled', '__return_false' );

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertFalse( $integration->public_is_feed_unchanged( 'abc123' ) );

		remove_filter( 'wc_stripe_agentic_commerce_dedup_enabled', '__return_false' );
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
	}

	/**
	 * Test is_feed_unchanged tolerates a malformed/partial cached record.
	 *
	 * @return void
	 */
	public function test_is_feed_unchanged_returns_false_for_malformed_record() {
		update_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION, 'not_an_array', false );

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$this->assertFalse( $integration->public_is_feed_unchanged( 'abc123' ) );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
	}

	/**
	 * Test remember_feed_upload persists hash, timestamp, and file_id.
	 *
	 * @return void
	 */
	public function test_remember_feed_upload_persists_expected_fields() {
		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );

		$integration = new Stripe_Agentic_Commerce_Integration_Dedup_Harness();
		$integration->public_remember_feed_upload(
			'abc123',
			[
				'file_id'       => 'file_123',
				'import_set_id' => 'imp_456',
				'status'        => 'processed',
			]
		);

		$record = get_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
		$this->assertIsArray( $record );
		$this->assertSame( 'abc123', $record['hash'] );
		$this->assertSame( 'file_123', $record['file_id'] );
		$this->assertSame( 'imp_456', $record['import_set_id'] );
		$this->assertIsInt( $record['uploaded_at'] );
		$this->assertLessThanOrEqual( time(), $record['uploaded_at'] );

		delete_option( \WC_Stripe_Agentic_Commerce_Integration::LAST_UPLOAD_OPTION );
	}
}
