<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Files_Api_Delivery
 *
 * @package WooCommerce\Stripe\Tests
 */

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;

// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Class WC_Stripe_Agentic_Commerce_Files_Api_Delivery_Test
 *
 * Tests the Stripe Files API delivery implementation.
 */
class WC_Stripe_Agentic_Commerce_Files_Api_Delivery_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Stripe_Agentic_Commerce_Files_Api_Delivery
	 */
	private $sut;

	/**
	 * Temporary file path for test CSV.
	 *
	 * @var string|null
	 */
	private ?string $temp_file = null;

	/**
	 * Setup test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce FeedInterface not available (requires WooCommerce 10.5.0+)' );
		}

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Files_Api_Delivery' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Files_Api_Delivery class not loaded' );
		}

		$this->sut = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_fake_key_123' );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( $this->temp_file && file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}

		remove_all_filters( 'wc_stripe_agentic_commerce_files_api_pre_request' );
		remove_all_filters( 'pre_http_request' );

		parent::tearDown();
	}

	/**
	 * Create a temporary CSV file for testing.
	 *
	 * @return string File path.
	 */
	private function create_temp_csv(): string {
		$base            = tempnam( sys_get_temp_dir(), 'stripe_test_feed_' );
		$this->temp_file = $base . '.csv';
		wp_delete_file( $base ); // Remove the file created by tempnam().
		file_put_contents( $this->temp_file, "id,title,description\n1,\"Test Product\",\"A test product\"\n" );
		return $this->temp_file;
	}

	/**
	 * Create a mock FeedInterface with the given file path.
	 *
	 * @param string|null $file_path File path the mock should return.
	 * @return FeedInterface
	 */
	private function create_mock_feed( ?string $file_path ): FeedInterface {
		$mock = $this->createMock( FeedInterface::class );
		$mock->expects( $this->once() )
			->method( 'get_file_path' )
			->willReturn( $file_path );
		return $mock;
	}

	// ---- check_setup tests ----

	public function test_check_setup_returns_true_with_secret_key() {
		$this->assertTrue( $this->sut->check_setup() );
	}

	public function test_check_setup_returns_false_with_empty_key() {
		$delivery = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( '' );
		$this->assertFalse( $delivery->check_setup() );
	}

	// ---- deliver() validation tests ----

	public function test_deliver_throws_exception_for_null_file_path() {
		$feed = $this->create_mock_feed( null );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Feed file does not exist' );
		$this->sut->deliver( $feed );
	}

	public function test_deliver_throws_exception_for_empty_file_path() {
		$feed = $this->create_mock_feed( '' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Feed file does not exist' );
		$this->sut->deliver( $feed );
	}

	public function test_deliver_throws_exception_for_missing_file() {
		$feed = $this->create_mock_feed( '/nonexistent/path/feed.csv' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Feed file does not exist' );
		$this->sut->deliver( $feed );
	}

	// ---- deliver() success flow via filter mocks ----

	public function test_deliver_returns_file_id_and_import_set_id_on_success() {
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		// Mock the Files API upload via the pre_request filter.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_abc123' ];
			}
		);

		// Mock the ImportSet creation via pre_http_request.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( str_contains( $url, 'import_sets' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'is_test_xyz789',
								'status' => 'pending',
							]
						),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->sut->deliver( $feed );

		$this->assertEquals( 'file_test_abc123', $result['file_id'] );
		$this->assertEquals( 'is_test_xyz789', $result['import_set_id'] );
		$this->assertEquals( 'pending', $result['status'] );
	}

	public function test_deliver_throws_when_files_api_returns_no_id() {
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		// Files API returns response without an id.
		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'object' => 'file' ]; // No 'id' field.
			}
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'did not return a file ID' );
		$this->sut->deliver( $feed );
	}

	// ---- ImportSet creation error tests ----

	public function test_deliver_throws_on_import_set_http_error() {
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_abc123' ];
			}
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( str_contains( $url, 'import_sets' ) ) {
					return [
						'response' => [ 'code' => 400 ],
						'body'     => wp_json_encode( [ 'error' => [ 'message' => 'Invalid file' ] ] ),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'ImportSet API returned HTTP 400' );
		$this->sut->deliver( $feed );
	}

	public function test_deliver_throws_on_import_set_wp_error() {
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_abc123' ];
			}
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( str_contains( $url, 'import_sets' ) ) {
					return new \WP_Error( 'http_request_failed', 'Connection timed out' );
				}
				return $preempt;
			},
			10,
			3
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'ImportSet creation failed: Connection timed out' );
		$this->sut->deliver( $feed );
	}

	// ---- ImportSet request parameter tests ----

	public function test_import_set_request_sends_correct_headers_and_body() {
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_abc123' ];
			}
		);

		$captured_args = null;
		$captured_url  = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$captured_args, &$captured_url ) {
				if ( str_contains( $url, 'import_sets' ) ) {
					$captured_args = $parsed_args;
					$captured_url  = $url;

					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'is_123',
								'status' => 'pending',
							]
						),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$this->sut->deliver( $feed );

		$this->assertEquals( 'https://api.stripe.com/v1/data_management/import_sets', $captured_url );
		$this->assertEquals( 'file_test_abc123', $captured_args['body']['file'] );
		$this->assertEquals( 'product_catalog_feed', $captured_args['body']['standard_data_format'] );
		$this->assertStringContainsString( 'Bearer sk_test_fake_key_123', $captured_args['headers']['Authorization'] );
		$this->assertEquals( '2025-09-30.clover;udap_beta=v1', $captured_args['headers']['Stripe-Version'] );
	}

	public function test_import_set_request_includes_stripe_account_header() {
		$delivery = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( 'sk_test_key', 'acct_123' );
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function () {
				return [ 'id' => 'file_test_abc123' ];
			}
		);

		$captured_args = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( &$captured_args ) {
				if ( str_contains( $url, 'import_sets' ) ) {
					$captured_args = $parsed_args;
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'is_123',
								'status' => 'pending',
							]
						),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$delivery->deliver( $feed );

		$this->assertEquals( 'acct_123', $captured_args['headers']['Stripe-Account'] );
	}

	// ---- get_import_set tests ----

	public function test_get_import_set_returns_full_response() {
		$import_set_data = [
			'id'     => 'impset_test_123',
			'status' => 'succeeded_with_errors',
			'result' => [
				'errors'          => [
					'file'      => 'file_err_123',
					'row_count' => 5,
				],
				'objects_created' => 16,
				'rows_processed'  => 21,
				'successes'       => [ 'row_count' => 16 ],
			],
		];

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $import_set_data ) {
				if ( str_contains( $url, 'import_sets/impset_test_123' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode( $import_set_data ),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->sut->get_import_set( 'impset_test_123' );

		$this->assertEquals( 'impset_test_123', $result['id'] );
		$this->assertEquals( 'succeeded_with_errors', $result['status'] );
		$this->assertEquals( 21, $result['result']['rows_processed'] );
		$this->assertEquals( 5, $result['result']['errors']['row_count'] );
		$this->assertEquals( 'file_err_123', $result['result']['errors']['file'] );
	}

	public function test_get_import_set_throws_on_http_error() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( str_contains( $url, 'import_sets/' ) ) {
					return [
						'response' => [ 'code' => 404 ],
						'body'     => wp_json_encode( [ 'error' => 'not found' ] ),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'ImportSet status API returned HTTP 404' );
		$this->sut->get_import_set( 'impset_nonexistent' );
	}

	public function test_get_import_set_throws_on_wp_error() {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'DNS resolution failed' );
			}
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'ImportSet status check failed: DNS resolution failed' );
		$this->sut->get_import_set( 'impset_test_123' );
	}

	public function test_get_import_set_throws_on_invalid_id_format() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid ImportSet ID format.' );
		$this->sut->get_import_set( 'invalid-id' );
	}

	// ---- Files API pre_request filter tests ----

	public function test_files_api_pre_request_filter_short_circuits_upload() {
		$csv_path = $this->create_temp_csv();
		$feed     = $this->create_mock_feed( $csv_path );

		$filter_called = false;

		add_filter(
			'wc_stripe_agentic_commerce_files_api_pre_request',
			function ( $pre, $path ) use ( &$filter_called, $csv_path ) {
				$filter_called = true;
				$this->assertNull( $pre );
				$this->assertEquals( $csv_path, $path );
				return [ 'id' => 'file_from_filter' ];
			},
			10,
			2
		);

		// Mock ImportSet creation.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( str_contains( $url, 'import_sets' ) ) {
					return [
						'response' => [ 'code' => 200 ],
						'body'     => wp_json_encode(
							[
								'id'     => 'is_123',
								'status' => 'pending',
							]
						),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->sut->deliver( $feed );

		$this->assertTrue( $filter_called );
		$this->assertEquals( 'file_from_filter', $result['file_id'] );
	}

	// ---- Constants tests ----

	public function test_class_constants_are_set() {
		$this->assertEquals( 'https://files.stripe.com/v1/files', WC_Stripe_Agentic_Commerce_Files_Api_Delivery::FILES_API_ENDPOINT );
		$this->assertEquals( 'https://api.stripe.com/v1/data_management/import_sets', WC_Stripe_Agentic_Commerce_Files_Api_Delivery::IMPORT_SETS_ENDPOINT );
		$this->assertEquals( '2025-09-30.clover;udap_beta=v1', WC_Stripe_Agentic_Commerce_Files_Api_Delivery::API_VERSION );
	}
}
