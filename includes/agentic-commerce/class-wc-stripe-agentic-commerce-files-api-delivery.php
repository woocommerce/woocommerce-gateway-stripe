<?php
/**
 * Stripe Files API Delivery Method
 *
 * Handles uploading product feed CSV files to Stripe Files API and creating ImportSets.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;

// This file uses cURL for multipart file uploads to Stripe's Files API.
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Stripe Files API Delivery Method
 *
 * Uploads CSV product feeds to Stripe's Files API, then creates an ImportSet
 * via the Data Management API to trigger processing.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Files_Api_Delivery {

	/**
	 * Stripe Files API endpoint.
	 *
	 * @var string
	 */
	const FILES_API_ENDPOINT = 'https://files.stripe.com/v1/files';

	/**
	 * Stripe Data Management API endpoint.
	 *
	 * @var string
	 */
	const IMPORT_SETS_ENDPOINT = 'https://api.stripe.com/v1/data_management/import_sets';

	/**
	 * Stripe Files API content endpoint.
	 *
	 * @var string
	 */
	const FILES_CONTENT_ENDPOINT = 'https://files.stripe.com/v1/files/';

	/**
	 * Stripe API version for Data Management (preview).
	 * Remove udap_beta=v1 suffix once Data Management API is out of beta.
	 *
	 * @var string
	 */
	const API_VERSION = '2025-09-30.clover;udap_beta=v1';

	/**
	 * Stripe secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Stripe account ID (optional, for connected accounts).
	 *
	 * @var string
	 */
	private string $account_id;

	/**
	 * Initialize delivery method.
	 *
	 * @since 10.5.0
	 * @param string $secret_key Stripe secret key.
	 * @param string $account_id Stripe account ID (optional).
	 */
	public function __construct( string $secret_key, string $account_id = '' ) {
		$this->secret_key = $secret_key;
		$this->account_id = $account_id;
	}

	/**
	 * Check if delivery method is properly configured.
	 *
	 * @since 10.5.0
	 * @return bool True if configured, false otherwise.
	 */
	public function check_setup(): bool {
		return ! empty( $this->secret_key );
	}

	/**
	 * Upload feed to Stripe Files API and create ImportSet.
	 *
	 * @since 10.5.0
	 * @param FeedInterface $feed The feed to deliver.
	 * @return array {
	 *     Response with file and import set details.
	 *
	 *     @type string $file_id       Stripe file ID (e.g. "file_...").
	 *     @type string $import_set_id ImportSet ID.
	 *     @type string $status        ImportSet status.
	 * }
	 * @throws Exception If upload or ImportSet creation fails.
	 */
	public function deliver( FeedInterface $feed ): array {
		$file_path = $feed->get_file_path();

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			throw new Exception(
				esc_html__( 'Feed file does not exist or path is empty.', 'woocommerce-gateway-stripe' )
			);
		}

		// Step 1: Upload CSV to Stripe Files API.
		$file_response = $this->upload_to_files_api( $file_path );
		$file_id       = $file_response['id'] ?? '';

		if ( empty( $file_id ) ) {
			throw new Exception(
				esc_html__( 'Stripe Files API did not return a file ID.', 'woocommerce-gateway-stripe' )
			);
		}

		WC_Stripe_Logger::info(
			'Agentic Commerce: File uploaded to Stripe',
			[ 'file_id' => $file_id ]
		);

		// Step 2: Create ImportSet to trigger processing.
		$import_set = $this->create_import_set( $file_id, 'product_catalog_feed' );

		$import_set_id = $import_set['id'] ?? '';
		$status        = $import_set['status'] ?? 'unknown';

		WC_Stripe_Logger::info(
			'Agentic Commerce: ImportSet created',
			[
				'import_set_id' => $import_set_id,
				'status'        => $status,
			]
		);

		return [
			'file_id'       => $file_id,
			'import_set_id' => $import_set_id,
			'status'        => $status,
		];
	}

	/**
	 * Upload a file to Stripe's Files API using cURL multipart/form-data.
	 *
	 * @since 10.5.0
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array Decoded JSON response from Stripe.
	 * @throws Exception If the upload fails.
	 */
	private function upload_to_files_api( string $file_path ): array {
		/**
		 * Allows the Files API request to be short-circuited (e.g. for testing).
		 *
		 * @since 10.5.0
		 * @param array|null $pre       Short-circuit response or null.
		 * @param string     $file_path The path to the feed file.
		 * @return array|null
		 */
		$pre = apply_filters( 'wc_stripe_agentic_commerce_files_api_pre_request', null, $file_path );
		if ( ! is_null( $pre ) ) {
			return $pre;
		}

		$file_size = filesize( $file_path );
		if ( false === $file_size ) {
			throw new Exception(
				esc_html__( 'Unable to determine feed file size.', 'woocommerce-gateway-stripe' )
			);
		}

		// Dynamic timeout: ~100 KB/s assumed transfer rate, minimum 10 seconds.
		$timeout = (int) max( 10, $file_size / MB_IN_BYTES * 10 );

		$headers = [
			'Authorization: Bearer ' . $this->secret_key,
		];

		if ( ! empty( $this->account_id ) ) {
			$headers[] = 'Stripe-Account: ' . $this->account_id;
		}

		if ( ! function_exists( 'curl_init' ) ) {
			throw new Exception(
				esc_html__( 'The cURL PHP extension is required for Stripe file uploads.', 'woocommerce-gateway-stripe' )
			);
		}

		$curl_handle = curl_init( self::FILES_API_ENDPOINT );
		curl_setopt_array(
			$curl_handle,
			[
				CURLOPT_POST           => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POSTFIELDS     => [
					'purpose' => 'data_management_manual_upload',
					'file'    => new CURLFile( $file_path, 'text/csv' ),
				],
			]
		);

		try {
			$response = curl_exec( $curl_handle );
			if ( false === $response ) {
				throw new Exception( 'cURL error: ' . curl_error( $curl_handle ) );
			}

			$http_code = curl_getinfo( $curl_handle, CURLINFO_HTTP_CODE );
			if ( $http_code < 200 || $http_code > 299 ) {
				throw new Exception(
					sprintf(
						'Stripe Files API returned HTTP %d: %s',
						$http_code,
						$this->parse_stripe_error( (string) $response )
					)
				);
			}
		} finally {
			// Note: Intelephense falsely reports curl_close is deprecated.
			curl_close( $curl_handle );
		}

		$data = json_decode( (string) $response, true );
		if ( ! is_array( $data ) ) {
			throw new Exception(
				esc_html__( 'Invalid JSON response from Stripe Files API.', 'woocommerce-gateway-stripe' )
			);
		}

		return $data;
	}

	/**
	 * Create an ImportSet via the Data Management API.
	 *
	 * @since 10.5.0
	 * @param string $file_id            Stripe file ID from the Files API upload.
	 * @param string $standard_data_format Format type (e.g. "product_catalog_feed").
	 * @return array Decoded JSON response from Stripe.
	 * @throws Exception If the request fails.
	 */
	private function create_import_set( string $file_id, string $standard_data_format ): array {
		$response = wp_remote_post(
			self::IMPORT_SETS_ENDPOINT,
			[
				'headers' => $this->get_common_headers(),
				'body'    => [
					'file'                 => $file_id,
					'standard_data_format' => $standard_data_format,
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'ImportSet creation failed: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );

		if ( $http_code < 200 || $http_code > 299 ) {
			throw new Exception(
				sprintf(
					'Stripe ImportSet API returned HTTP %d: %s',
					$http_code,
					$this->parse_stripe_error( $body )
				)
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			throw new Exception(
				esc_html__( 'Invalid JSON response from Stripe ImportSet API.', 'woocommerce-gateway-stripe' )
			);
		}

		return $data;
	}

	/**
	 * Get the full ImportSet object including status, result, and errors.
	 *
	 * @since 10.5.0
	 * @param string $import_set_id ImportSet ID.
	 * @return array {
	 *     Full ImportSet response from Stripe.
	 *
	 *     @type string $id            ImportSet ID.
	 *     @type string $status        pending, failed, succeeded, succeeded_with_errors, pending_archive, archived.
	 *     @type string $failed_reason Failure reason if status is "failed".
	 *     @type array  $result {
	 *         @type array $errors             Error file details (file ID + row_count).
	 *         @type int   $objects_created     Number of objects created.
	 *         @type int   $rows_processed      Number of rows processed.
	 *         @type array $skipped_by_filter   Skipped rows details.
	 *         @type array $skipped_duplicates  Duplicate rows details.
	 *         @type array $successes           Success details (row_count).
	 *     }
	 * }
	 * @throws Exception If the request fails.
	 */
	public function get_import_set( string $import_set_id ): array {
		if ( ! preg_match( '/^impset_[a-zA-Z0-9_]+$/', $import_set_id ) ) {
			throw new Exception( 'Invalid ImportSet ID format.' );
		}

		$response = wp_remote_get(
			self::IMPORT_SETS_ENDPOINT . '/' . $import_set_id,
			[
				'headers' => $this->get_common_headers(),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'ImportSet status check failed: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );

		if ( $http_code < 200 || $http_code > 299 ) {
			throw new Exception(
				sprintf(
					'Stripe ImportSet status API returned HTTP %d: %s',
					$http_code,
					$this->parse_stripe_error( $body )
				)
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			throw new Exception(
				esc_html__( 'Invalid JSON response from Stripe ImportSet API.', 'woocommerce-gateway-stripe' )
			);
		}

		return $data;
	}

	/**
	 * Get the error report for an ImportSet.
	 *
	 * Downloads and parses the error CSV from Stripe when an ImportSet
	 * has status "succeeded_with_errors" or "failed".
	 *
	 * @since 10.5.0
	 * @param array $import_set Full ImportSet response from get_import_set().
	 * @return array {
	 *     Parsed error report.
	 *
	 *     @type int    $error_count Number of errored rows.
	 *     @type array  $headers     CSV column headers.
	 *     @type array  $rows        Array of associative arrays (header => value) for each error row.
	 * }
	 * @throws Exception If no error file is available or download fails.
	 */
	public function get_error_report( array $import_set ): array {
		$error_file_id = $import_set['result']['errors']['file'] ?? '';

		if ( ! empty( $error_file_id ) && ! preg_match( '/^file_[a-zA-Z0-9_]+$/', $error_file_id ) ) {
			throw new Exception( 'Invalid error file ID format.' );
		}

		if ( empty( $error_file_id ) ) {
			throw new Exception(
				esc_html__( 'No error report file available for this ImportSet.', 'woocommerce-gateway-stripe' )
			);
		}

		$response = wp_remote_get(
			self::FILES_CONTENT_ENDPOINT . $error_file_id . '/contents',
			[
				'headers' => $this->get_common_headers(),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Error report download failed: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );

		if ( $http_code < 200 || $http_code > 299 ) {
			throw new Exception(
				sprintf(
					'Stripe Files content API returned HTTP %d: %s',
					$http_code,
					$this->parse_stripe_error( $body )
				)
			);
		}

		// Parse CSV content.
		$lines = array_filter( explode( "\n", str_replace( "\r\n", "\n", $body ) ), static fn( $s ) => '' !== $s );
		if ( empty( $lines ) ) {
			return [
				'error_count' => 0,
				'headers'     => [],
				'rows'        => [],
			];
		}

		$headers = str_getcsv( array_shift( $lines ) );
		$rows    = [];

		foreach ( $lines as $line ) {
			$values = str_getcsv( $line );
			if ( count( $values ) === count( $headers ) ) {
				$rows[] = array_combine( $headers, $values );
			}
		}

		return [
			'error_count' => count( $rows ),
			'headers'     => $headers,
			'rows'        => $rows,
		];
	}

	/**
	 * Extract error message from a Stripe API response body.
	 *
	 * Parses the JSON error object and returns only the error message,
	 * avoiding leaking raw response data into logs or exceptions.
	 *
	 * @param string $body Raw response body.
	 * @return string Extracted error message or 'Unknown error'.
	 */
	private function parse_stripe_error( string $body ): string {
		$data = json_decode( $body, true );
		if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
			return $data['error']['message'];
		}

		return 'Unknown error';
	}

	/**
	 * Get common headers for Data Management API requests.
	 *
	 * @since 10.5.0
	 * @return array Headers array for wp_remote_* functions.
	 */

	private function get_common_headers(): array {
		$headers = [
			'Authorization'  => 'Bearer ' . $this->secret_key,
			'Stripe-Version' => self::API_VERSION,
			'Content-Type'   => 'application/x-www-form-urlencoded',
		];

		if ( ! empty( $this->account_id ) ) {
			$headers['Stripe-Account'] = $this->account_id;
		}

		return $headers;
	}
}
