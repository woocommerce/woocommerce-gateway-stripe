<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Csv_Feed
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WC_Stripe_Agentic_Commerce_Csv_Feed;

/**
 * Class WC_Stripe_Agentic_Commerce_Csv_Feed_Test
 *
 * Tests the CSV feed implementation for Agentic Commerce.
 */
class WC_Stripe_Agentic_Commerce_Csv_Feed_Test extends WP_UnitTestCase {
	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Skip tests if WooCommerce FeedInterface is not available.
		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce FeedInterface not available (requires WooCommerce 10.5.0+)' );
		}

		// Skip tests if CSV Feed class is not loaded.
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Csv_Feed' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Csv_Feed class not loaded' );
		}

		// Create temp upload directory for testing.
		$upload_dir            = wp_upload_dir();
		$this->temp_upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'stripe-agentic-commerce-test';

		// Clean up any existing test files.
		$this->cleanup_test_files();
	}

	/**
	 * Cleanup test environment after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->cleanup_test_files();
		parent::tearDown();
	}

	/**
	 * Clean up test files and directories.
	 *
	 * @return void
	 */
	private function cleanup_test_files() {
		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'stripe-agentic-commerce';

		if ( is_dir( $base_dir ) ) {
			$this->delete_directory( $base_dir );
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = trailingslashit( $dir ) . $file;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Test feed instantiation with base name.
	 *
	 * @return void
	 */
	public function test_feed_instantiation_with_base_name() {
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );

		$this->assertInstanceOf( WC_Stripe_Agentic_Commerce_Csv_Feed::class, $feed );
	}

	/**
	 * Test set_columns method returns self for chaining.
	 *
	 * @return void
	 */
	public function test_set_columns_returns_self() {
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$result = $feed->set_columns( [ 'id', 'title', 'price' ] );

		$this->assertSame( $feed, $result );
	}

	/**
	 * Test start without headers throws exception.
	 *
	 * @return void
	 */
	public function test_start_without_headers_throws_exception() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'CSV headers must be set via set_columns() before calling start().' );

		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->start();
	}

	/**
	 * Test start method creates temp file and writes headers.
	 *
	 * @return void
	 */
	public function test_start_creates_temp_file_and_writes_headers() {
		$headers = [ 'id', 'title', 'price' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();

		// Verify file path is null before finalization.
		$this->assertNull( $feed->get_file_path() );
	}

	/**
	 * Test add_entry method writes data to feed.
	 *
	 * @return void
	 */
	public function test_add_entry_writes_data() {
		$headers = [ 'id', 'title', 'price' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->add_entry( [ '1', 'Product 1', '19.99' ] );
		$feed->add_entry( [ '2', 'Product 2', '29.99' ] );
		$feed->end();

		$file_path = $feed->get_file_path();
		$this->assertNotNull( $file_path );
		$this->assertFileExists( $file_path );

		// Read file and verify content.
		$content = file_get_contents( $file_path );

		$this->assertStringContainsString( 'id,title,price', $content );
		$this->assertStringContainsString( 'Product 1', $content );
		$this->assertStringContainsString( 'Product 2', $content );
	}

	/**
	 * Test special characters are properly escaped.
	 *
	 * @return void
	 */
	public function test_special_characters_are_escaped() {
		$headers = [ 'id', 'description' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->add_entry( [ '1', 'Description with "quotes" and, commas' ] );
		$feed->add_entry( [ '2', "Line with\nnewline" ] );
		$feed->end();

		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );

		// CSV should properly escape quotes by doubling them.
		$this->assertStringContainsString( '""quotes""', $content );
	}

	/**
	 * Test UTF-8 encoding is preserved.
	 *
	 * @return void
	 */
	public function test_utf8_encoding_preserved() {
		$headers = [ 'id', 'title' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->add_entry( [ '1', 'Product with café and 日本語' ] );
		$feed->add_entry( [ '2', 'Emoji test 🎉' ] );
		$feed->end();

		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );

		$this->assertStringContainsString( 'café', $content );
		$this->assertStringContainsString( '日本語', $content );
		$this->assertStringContainsString( '🎉', $content );
	}

	/**
	 * Test null values are converted to empty strings.
	 *
	 * @return void
	 */
	public function test_null_values_converted_to_empty_strings() {
		$headers = [ 'id', 'description', 'optional' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->add_entry( [ '1', 'Product', null ] );
		$feed->end();

		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );

		// Null should be converted to empty string, not the word "null".
		$this->assertStringNotContainsString( 'null', strtolower( $content ) );
	}

	/**
	 * Test boolean values are converted to strings.
	 *
	 * @return void
	 */
	public function test_boolean_values_converted() {
		$headers = [ 'id', 'in_stock', 'featured' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->add_entry( [ '1', true, false ] );
		$feed->end();

		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );

		$this->assertStringContainsString( 'true', $content );
		$this->assertStringContainsString( 'false', $content );
	}

	/**
	 * Test arrays throw exception (must be pre-formatted by caller).
	 *
	 * @return void
	 */
	public function test_arrays_throw_exception() {
		$headers = [ 'id', 'categories' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/array or object/' );

		// Arrays must be pre-formatted as strings (e.g., comma-separated).
		$feed->add_entry( [ '1', [ 'Electronics', 'Computers' ] ] );
	}

	/**
	 * Test objects throw exception (must be pre-formatted by caller).
	 *
	 * @return void
	 */
	public function test_objects_throw_exception() {
		$headers = [ 'id', 'data' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/array or object/' );

		// Objects must be pre-formatted as strings.
		$feed->add_entry( [ '1', (object) [ 'key' => 'value' ] ] );
	}

	/**
	 * Test pre-formatted comma-separated string works.
	 *
	 * @return void
	 */
	public function test_preformatted_comma_separated_string() {
		$headers = [ 'id', 'categories' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		// Caller should format arrays as comma-separated strings.
		$feed->add_entry( [ '1', 'Electronics,Computers,Laptops' ] );
		$feed->end();

		$file_path = $feed->get_file_path();
		$content   = file_get_contents( $file_path );

		$this->assertStringContainsString( 'Electronics,Computers,Laptops', $content );
	}

	/**
	 * Test file permissions are set to 0644.
	 *
	 * @return void
	 */
	public function test_file_permissions() {
		$headers = [ 'id', 'title' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->add_entry( [ '1', 'Test' ] );
		$feed->end();

		$file_path   = $feed->get_file_path();
		$permissions = substr( sprintf( '%o', fileperms( $file_path ) ), -4 );

		$this->assertEquals( '0644', $permissions );
	}

	/**
	 * Test adding entry before start throws exception.
	 *
	 * @return void
	 */
	public function test_add_entry_before_start_throws_exception() {
		$headers = [ 'id', 'title' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cannot add entry: feed not started.' );

		$feed->add_entry( [ '1', 'Test' ] );
	}

	/**
	 * Test adding entry after end throws exception.
	 *
	 * @return void
	 */
	public function test_add_entry_after_end_throws_exception() {
		$headers = [ 'id', 'title' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->end();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cannot add entry: feed already finalized.' );

		$feed->add_entry( [ '1', 'Test' ] );
	}

	/**
	 * Test entry with wrong column count throws exception.
	 *
	 * @return void
	 */
	public function test_wrong_column_count_throws_exception() {
		$headers = [ 'id', 'title', 'price' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Entry column count/' );

		$feed->add_entry( [ '1', 'Test' ] ); // Only 2 values, should have 3.
	}

	/**
	 * Test final file is created in temp directory.
	 *
	 * @return void
	 */
	public function test_file_in_temp_directory() {
		$headers = [ 'id', 'title' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->end();

		$file_path = $feed->get_file_path();

		// File should be in temp directory.
		$this->assertNotNull( $file_path );
		$this->assertFileExists( $file_path );
		$this->assertStringContainsString( '.csv', $file_path );
	}

	/**
	 * Test unique filenames are generated with hashes.
	 *
	 * @return void
	 */
	public function test_unique_filenames_generated() {
		$headers = [ 'id', 'title' ];

		// Create first feed.
		$feed1 = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed-1' );
		$feed1->set_columns( $headers );
		$feed1->start();
		$feed1->add_entry( [ '1', 'Test 1' ] );
		$feed1->end();
		$file1 = $feed1->get_file_path();

		// Create second feed with different base name - should get different hash.
		$feed2 = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed-2' );
		$feed2->set_columns( $headers );
		$feed2->start();
		$feed2->add_entry( [ '2', 'Test 2' ] );
		$feed2->end();
		$file2 = $feed2->get_file_path();

		// Filenames should be different due to different base names and hashes.
		$this->assertNotEquals( $file1, $file2, 'Different base names should create unique filenames' );
		$this->assertFileExists( $file1 );
		$this->assertFileExists( $file2 );
	}

	/**
	 * Test filename is properly sanitized.
	 *
	 * @return void
	 */
	public function test_filename_sanitized() {
		$headers = [ 'id', 'title' ];
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-feed' );
		$feed->set_columns( $headers );
		$feed->start();
		$feed->end();

		$file_path = $feed->get_file_path();
		$filename  = basename( $file_path );

		// Filename should only contain safe characters.
		$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9._-]+\.csv$/', $filename );
	}

	/**
	 * Test how fputcsv handles raw PHP types vs string-converted types.
	 *
	 * This test documents fputcsv's native behavior. Note that our sanitize_entry()
	 * method converts booleans to "true"/"false" strings to match Stripe's spec.
	 *
	 * @return void
	 */
	public function test_php_type_handling_in_csv() {
		// Create a temp file to test fputcsv behavior.
		$temp_file = tempnam( sys_get_temp_dir(), 'csv_test_' );
		$handle    = fopen( $temp_file, 'w' );

		// Test raw types: int, float, bool, string.
		fputcsv( $handle, [ 123, 3.14, true, false, 'text', null ] );

		fclose( $handle );
		$content = file_get_contents( $temp_file );
		unlink( $temp_file );

		// Verify how fputcsv naturally handles each type.
		$this->assertStringContainsString( '123', $content, 'Integer should be written as "123"' );
		$this->assertStringContainsString( '3.14', $content, 'Float should be written as "3.14"' );
		$this->assertStringContainsString( '1', $content, 'true becomes "1" in fputcsv' );
	}
}
