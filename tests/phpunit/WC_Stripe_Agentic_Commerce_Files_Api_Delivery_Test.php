<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Files_Api_Delivery
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;

/**
 * Class WC_Stripe_Agentic_Commerce_Files_Api_Delivery_Test
 *
 * Tests the Files API delivery method for Agentic Commerce.
 */
class WC_Stripe_Agentic_Commerce_Files_Api_Delivery_Test extends WP_UnitTestCase {
	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Files_Api_Delivery' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Files_Api_Delivery class not loaded' );
		}
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tearDown();
	}

	/**
	 * Test check_setup returns true when secret key is provided.
	 *
	 * @return void
	 */
	public function test_check_setup_with_key() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$this->assertTrue( $delivery->check_setup() );
	}

	/**
	 * Test check_setup returns false when secret key is empty.
	 *
	 * @return void
	 */
	public function test_check_setup_without_key() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( '' );
		$this->assertFalse( $delivery->check_setup() );
	}

	/**
	 * Test deliver throws when API key is not configured.
	 *
	 * @return void
	 */
	public function test_deliver_throws_without_api_key() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( '' );
		$feed     = $this->create_test_feed();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Stripe API key not configured' );

		$delivery->deliver( $feed );
	}

	/**
	 * Test deliver throws when feed file path is empty.
	 *
	 * @return void
	 */
	public function test_deliver_throws_for_empty_file_path() {
		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
			$this->markTestSkipped( 'FeedInterface not available' );
		}

		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );

		// Create a mock feed that returns null file path.
		$feed = $this->createMock( \Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface::class );
		$feed->method( 'get_file_path' )->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Feed file path is invalid or empty' );

		$delivery->deliver( $feed );
	}

	/**
	 * Test deliver throws when feed file does not exist.
	 *
	 * @return void
	 */
	public function test_deliver_throws_for_missing_file() {
		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
			$this->markTestSkipped( 'FeedInterface not available' );
		}

		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );

		$feed = $this->createMock( \Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface::class );
		$feed->method( 'get_file_path' )->willReturn( '/nonexistent/path/file.csv' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Feed file not found' );

		$delivery->deliver( $feed );
	}

	/**
	 * Test successful deliver returns file_id and import_set_id.
	 *
	 * @return void
	 */
	public function test_deliver_success() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		$call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$call_count ) {
				$call_count++;

				// First call: file upload.
				if ( 1 === $call_count ) {
					$this->assertStringContainsString( 'files.stripe.com', $url );
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode( [ 'id' => 'file_test_abc123' ] ),
					];
				}

				// Second call: create import set.
				$this->assertStringContainsString( 'import_sets', $url );
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'id' => 'is_test_xyz789' ] ),
				];
			},
			10,
			3
		);

		$result = $delivery->deliver( $feed );

		$this->assertEquals( 'file_test_abc123', $result['file_id'] );
		$this->assertEquals( 'is_test_xyz789', $result['import_set_id'] );
		$this->assertEquals( 2, $call_count );
	}

	/**
	 * Test deliver sends correct authorization header.
	 *
	 * @return void
	 */
	public function test_deliver_sends_authorization_header() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_my_secret' );
		$feed     = $this->create_test_feed();

		$captured_headers = [];
		$this->mock_both_api_calls( $captured_headers );

		$delivery->deliver( $feed );

		$this->assertNotEmpty( $captured_headers );
		$this->assertEquals( 'Bearer sk_test_my_secret', $captured_headers[0]['Authorization'] );
	}

	/**
	 * Test deliver sends Stripe-Account header when account_id is provided.
	 *
	 * @return void
	 */
	public function test_deliver_sends_account_header() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123', 'acct_abc123' );
		$feed     = $this->create_test_feed();

		$captured_headers = [];
		$this->mock_both_api_calls( $captured_headers );

		$delivery->deliver( $feed );

		$this->assertNotEmpty( $captured_headers );
		$this->assertArrayHasKey( 'Stripe-Account', $captured_headers[0] );
		$this->assertEquals( 'acct_abc123', $captured_headers[0]['Stripe-Account'] );
	}

	/**
	 * Test deliver does not send Stripe-Account header when account_id is empty.
	 *
	 * @return void
	 */
	public function test_deliver_omits_account_header_when_empty() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		$captured_headers = [];
		$this->mock_both_api_calls( $captured_headers );

		$delivery->deliver( $feed );

		$this->assertNotEmpty( $captured_headers );
		$this->assertArrayNotHasKey( 'Stripe-Account', $captured_headers[0] );
	}

	/**
	 * Test deliver throws on file upload API error.
	 *
	 * @return void
	 */
	public function test_deliver_throws_on_upload_api_error() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode(
						[
							'error' => [ 'message' => 'Invalid API Key provided' ],
						]
					),
				];
			}
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid API Key provided' );

		$delivery->deliver( $feed );
	}

	/**
	 * Test deliver throws on WP_Error from wp_remote_post.
	 *
	 * @return void
	 */
	public function test_deliver_throws_on_wp_error() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection timed out' );
			}
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Connection timed out' );

		$delivery->deliver( $feed );
	}

	/**
	 * Test deliver throws when API response is missing file ID.
	 *
	 * @return void
	 */
	public function test_deliver_throws_on_missing_file_id() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'object' => 'file' ] ), // No 'id' field.
				];
			}
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'missing file ID' );

		$delivery->deliver( $feed );
	}

	/**
	 * Test deliver sends correct file upload purpose.
	 *
	 * @return void
	 */
	public function test_deliver_sends_correct_purpose() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		$captured_body = '';
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_body ) {
				if ( str_contains( $url, 'files.stripe.com' ) ) {
					$captured_body = $args['body'];
				}
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'id' => 'file_test_123' ] ),
				];
			},
			10,
			3
		);

		$result = $delivery->deliver( $feed );

		$this->assertStringContainsString( 'agentic_commerce_import', $captured_body );
		$this->assertArrayHasKey( 'file_id', $result );
	}

	/**
	 * Test deliver sends file content as multipart form data.
	 *
	 * @return void
	 */
	public function test_deliver_sends_multipart_content_type() {
		$delivery = new \WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_123' );
		$feed     = $this->create_test_feed();

		$captured_headers = [];
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_headers ) {
				if ( str_contains( $url, 'files.stripe.com' ) ) {
					$captured_headers = $args['headers'];
				}
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'id' => 'file_test_123' ] ),
				];
			},
			10,
			3
		);

		$result = $delivery->deliver( $feed );

		$this->assertStringContainsString( 'multipart/form-data', $captured_headers['Content-Type'] );
		$this->assertArrayHasKey( 'file_id', $result );
	}

	/**
	 * Mock both API calls (file upload + import set creation) and capture headers.
	 *
	 * @param array $captured_headers Reference to capture request headers from both calls.
	 * @return void
	 */
	private function mock_both_api_calls( array &$captured_headers ): void {
		$call_count = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_headers, &$call_count ) {
				$captured_headers[] = $args['headers'];
				$call_count++;

				if ( 1 === $call_count ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode( [ 'id' => 'file_test_mock' ] ),
					];
				}

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'id' => 'is_test_mock' ] ),
				];
			},
			10,
			2
		);
	}

	/**
	 * Create a test feed with a real temporary CSV file.
	 *
	 * @return \WC_Stripe_Agentic_Commerce_Csv_Feed
	 */
	private function create_test_feed(): \WC_Stripe_Agentic_Commerce_Csv_Feed {
		$feed = new \WC_Stripe_Agentic_Commerce_Csv_Feed( 'test-delivery' );
		$feed->set_columns( [ 'id', 'title', 'price' ] );
		$feed->start();
		$feed->add_entry( [ '1', 'Test Product', '19.99 USD' ] );
		$feed->end();

		return $feed;
	}
}
