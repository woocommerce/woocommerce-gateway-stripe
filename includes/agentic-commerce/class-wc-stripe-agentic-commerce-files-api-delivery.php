<?php
/**
 * Stripe Files API Delivery Method
 *
 * Handles uploading product feed CSV files to Stripe Files API and creating ImportSets.
 *
 * @package WooCommerce_Stripe
 * @since 10.4.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\FileDeliveryInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;

/**
 * Stripe Files API Delivery Method
 *
 * Uploads CSV product feeds to Stripe Files API and creates ImportSets for processing.
 *
 * @since 10.4.0
 */
class WC_Stripe_Agentic_Commerce_Files_Api_Delivery implements FileDeliveryInterface {

	/**
	 * Stripe secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Stripe account ID (optional).
	 *
	 * @var string
	 */
	private string $account_id;

	/**
	 * Initialize delivery method.
	 *
	 * @since 10.4.0
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
	 * @since 10.4.0
	 * @return bool True if configured, false otherwise.
	 */
	public function check_setup(): bool {
		return ! empty( $this->secret_key );
	}

	/**
	 * Upload feed to Stripe Files API and create ImportSet.
	 *
	 * @since 10.4.0
	 * @param FeedInterface $feed The feed to deliver.
	 * @return array Response with file_id and import_set_id.
	 * @throws Exception If upload fails.
	 */
	public function deliver( FeedInterface $feed ): array {
		if ( ! $this->check_setup() ) {
			throw new Exception(
				esc_html__( 'Stripe API key not configured. Cannot upload feed.', 'woocommerce-gateway-stripe' )
			);
		}

		$file_path = $feed->get_file_path();

		if ( empty( $file_path ) || ! is_string( $file_path ) ) {
			throw new Exception(
				esc_html__( 'Feed file path is invalid or empty.', 'woocommerce-gateway-stripe' )
			);
		}

		if ( ! file_exists( $file_path ) ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: file path */
						__( 'Feed file not found: %s', 'woocommerce-gateway-stripe' ),
						$file_path
					)
				)
			);
		}

		// Step 1: Upload file to Stripe Files API.
		$file_id = $this->upload_file( $file_path );

		// Step 2: Create ImportSet with the file.
		$import_set_id = $this->create_import_set( $file_id );

		return [
			'file_id'       => $file_id,
			'import_set_id' => $import_set_id,
		];
	}

	/**
	 * Upload file to Stripe Files API.
	 *
	 * @since 10.4.0
	 * @param string $file_path Path to file to upload.
	 * @return string File ID from Stripe.
	 * @throws Exception If upload fails.
	 */
	private function upload_file( string $file_path ): string {
		$boundary = wp_generate_password( 24, false );

		// Prepare multipart form data.
		$file_contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$filename      = basename( $file_path );

		$body  = '';
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
		$body .= "agentic_commerce_import\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
		$body .= "Content-Type: text/csv\r\n\r\n";
		$body .= $file_contents . "\r\n";
		$body .= "--{$boundary}--\r\n";

		$headers = [
			'Authorization'  => 'Bearer ' . $this->secret_key,
			'Content-Type'   => 'multipart/form-data; boundary=' . $boundary,
			'Content-Length' => strlen( $body ),
		];

		if ( ! empty( $this->account_id ) ) {
			$headers['Stripe-Account'] = $this->account_id;
		}

		$response = wp_remote_post(
			'https://files.stripe.com/v1/files',
			[
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: error message */
						__( 'Failed to upload file to Stripe: %s', 'woocommerce-gateway-stripe' ),
						$response->get_error_message()
					)
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$error_message = $response_body['error']['message'] ?? __( 'Unknown error', 'woocommerce-gateway-stripe' );
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: 1: HTTP status code, 2: error message */
						__( 'Stripe API error (%1$d): %2$s', 'woocommerce-gateway-stripe' ),
						$response_code,
						$error_message
					)
				)
			);
		}

		if ( empty( $response_body['id'] ) ) {
			throw new Exception(
				esc_html__( 'Stripe API response missing file ID', 'woocommerce-gateway-stripe' )
			);
		}

		return $response_body['id'];
	}

	/**
	 * Create ImportSet with uploaded file.
	 *
	 * @since 10.4.0
	 * @param string $file_id Stripe file ID.
	 * @return string ImportSet ID from Stripe.
	 * @throws Exception If creation fails.
	 */
	private function create_import_set( string $file_id ): string {
		$headers = [
			'Authorization' => 'Bearer ' . $this->secret_key,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		];

		if ( ! empty( $this->account_id ) ) {
			$headers['Stripe-Account'] = $this->account_id;
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/agentic_commerce/import_sets',
			[
				'headers' => $headers,
				'body'    => [
					'file' => $file_id,
				],
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: error message */
						__( 'Failed to create Stripe ImportSet: %s', 'woocommerce-gateway-stripe' ),
						$response->get_error_message()
					)
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$error_message = $response_body['error']['message'] ?? __( 'Unknown error', 'woocommerce-gateway-stripe' );
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: 1: HTTP status code, 2: error message */
						__( 'Stripe API error (%1$d): %2$s', 'woocommerce-gateway-stripe' ),
						$response_code,
						$error_message
					)
				)
			);
		}

		if ( empty( $response_body['id'] ) ) {
			throw new Exception(
				esc_html__( 'Stripe API response missing ImportSet ID', 'woocommerce-gateway-stripe' )
			);
		}

		return $response_body['id'];
	}
}
