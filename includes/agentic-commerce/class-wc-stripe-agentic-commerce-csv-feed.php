<?php
/**
 * Agentic Commerce CSV Feed Generator
 *
 * Streaming CSV feed implementation for large product catalogs.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;
use Automattic\WooCommerce\Internal\Utilities\FilesystemUtil;

// This file works directly with local files for streaming CSV writes. That's fine.
// phpcs:disable WordPress.WP.AlternativeFunctions

/**
 * Streaming CSV feed generator for Stripe's Agentic Commerce.
 *
 * Implements WooCommerce's Product Catalog FeedInterface. Handles large catalogs (100k+ products)
 * via streaming writes to temporary files. See README.md for detailed documentation.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Csv_Feed implements FeedInterface {
	/**
	 * The base name of the feed file.
	 *
	 * @var string
	 */
	private $base_name;

	/**
	 * CSV column headers.
	 *
	 * @var array
	 */
	private $headers = [];

	/**
	 * File handle for streaming writes.
	 *
	 * @var resource|false|null
	 */
	private $file_handle = null;

	/**
	 * The name of the feed file, no directory.
	 *
	 * @var string|null
	 */
	private $file_name = null;

	/**
	 * Path to CSV file.
	 *
	 * @var string|null
	 */
	private $file_path = null;

	/**
	 * Number of entries written to feed.
	 *
	 * @var int
	 */
	private $entry_count = 0;

	/**
	 * Whether feed generation has started.
	 *
	 * @var bool
	 */
	private $started = false;

	/**
	 * Whether feed has been finalized.
	 *
	 * @var bool
	 */
	private $finalized = false;

	/**
	 * Constructor.
	 *
	 * @since 10.5.0
	 * @param string $base_name The base name of the feed file.
	 */
	public function __construct( string $base_name ) {
		$this->base_name = sanitize_file_name( $base_name );
	}

	/**
	 * Set CSV column headers.
	 *
	 * @since 10.5.0
	 * @param array $headers CSV column headers.
	 * @return self
	 */
	public function set_columns( array $headers ): self {
		foreach ( $headers as $index => $header ) {
			if ( ! is_string( $header ) || '' === trim( $header ) ) {
				throw new \Exception(
					sprintf(
						/* translators: %d: column index */
						__( 'CSV header at index %d must be a non-empty string.', 'woocommerce-gateway-stripe' ),
						$index
					)
				);
			}
		}

		$this->headers = $headers;
		return $this;
	}

	/**
	 * Get the upload directory for the feed.
	 *
	 * Creates directory with security files if needed.
	 * Pattern: wp-content/uploads/stripe-product-feeds/
	 *
	 * @since 10.5.0
	 * @return array {
	 *     The upload directory for the feed. Both fields end with the right trailing slash.
	 *
	 *     @type string $path The path to the upload directory.
	 *     @type string $url The URL to the upload directory.
	 * }
	 * @throws Exception If the upload directory cannot be created.
	 */
	private function get_upload_dir(): array {
		// Only generate everything once.
		static $prepared;
		if ( isset( $prepared ) ) {
			return $prepared;
		}

		$upload_dir     = wp_upload_dir( null, true );
		$directory_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'stripe-product-feeds' . DIRECTORY_SEPARATOR;

		// Try to create the directory if it does not exist.
		if ( ! is_dir( $directory_path ) ) {
			FilesystemUtil::mkdir_p_not_indexable( $directory_path );
		}

		// mkdir_p_not_indexable() returns void, we have to check again.
		if ( ! is_dir( $directory_path ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: directory path */
					__( 'Unable to create feed directory: %s', 'woocommerce-gateway-stripe' ),
					$directory_path
				)
			);
		}

		$directory_url = $upload_dir['baseurl'] . '/stripe-product-feeds/';

		// Follow the format returned by wp_upload_dir().
		$prepared = [
			'path' => $directory_path,
			'url'  => $directory_url,
		];
		return $prepared;
	}

	/**
	 * Start feed generation.
	 *
	 * Creates file in temp directory, opens file handle, writes UTF-8 BOM and headers.
	 *
	 * @since 10.5.0
	 * @throws Exception If feed generation cannot be started.
	 * @return void
	 */
	public function start(): void {
		if ( $this->started ) {
			WC_Stripe_Logger::error( 'Feed generation already started.' );
			throw new Exception( __( 'Feed generation already started.', 'woocommerce-gateway-stripe' ) );
		}

		if ( empty( $this->headers ) ) {
			throw new Exception( __( 'CSV headers must be set via set_columns() before calling start().', 'woocommerce-gateway-stripe' ) );
		}

		try {
			// Generate filename with date and unique hash.
			$current_time    = time();
			$hash_data       = $this->base_name . gmdate( 'r', $current_time );
			$this->file_name = sprintf(
				'%s-%s-%s.csv',
				$this->base_name,
				gmdate( 'Y-m-d', $current_time ),
				wp_hash( $hash_data )
			);

			// Start by trying to use temp directory (faster, fewer permission issues).
			$this->file_path   = get_temp_dir() . DIRECTORY_SEPARATOR . $this->file_name;
			$this->file_handle = fopen( $this->file_path, 'w' );

			if ( false === $this->file_handle ) {
				// Fall back to uploads directory.
				$upload_dir        = $this->get_upload_dir();
				$this->file_path   = $upload_dir['path'] . $this->file_name;
				$this->file_handle = fopen( $this->file_path, 'w' );
			}

			if ( false === $this->file_handle ) {
				throw new Exception(
					sprintf(
						/* translators: %s: file path */
						__( 'Failed to open file for writing: %s', 'woocommerce-gateway-stripe' ),
						$this->file_path
					)
				);
			}

			// Write header row.
			fputcsv( $this->file_handle, $this->headers, ',', '"', '\\' );

			$this->started     = true;
			$this->entry_count = 0;

			WC_Stripe_Logger::info( "Feed generation started: {$this->file_name}" );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Error starting feed generation: ' . $e->getMessage() );
			$this->cleanup();
			throw $e;
		}
	}

	/**
	 * Add entry to feed.
	 *
	 * Sanitizes data, writes to file via fputcsv, and logs progress.
	 *
	 * @since 10.5.0
	 * @param array $entry Entry data (should match header count).
	 * @throws Exception If entry cannot be added.
	 * @return void
	 */
	public function add_entry( array $entry ): void {
		if ( ! $this->started ) {
			WC_Stripe_Logger::error( 'Cannot add entry: feed not started.' );
			throw new Exception( __( 'Cannot add entry: feed not started.', 'woocommerce-gateway-stripe' ) );
		}

		if ( $this->finalized ) {
			WC_Stripe_Logger::error( 'Cannot add entry: feed already finalized.' );
			throw new Exception( __( 'Cannot add entry: feed already finalized.', 'woocommerce-gateway-stripe' ) );
		}

		if ( count( $entry ) !== count( $this->headers ) ) {
			$message = sprintf(
				'Entry column count (%d) does not match headers count (%d).',
				count( $entry ),
				count( $this->headers )
			);
			WC_Stripe_Logger::error( $message );
			throw new Exception( $message );
		}

		try {
			// Sanitize and encode entry data.
			$sanitized_entry = $this->sanitize_entry( $entry );

			// Ensure file handle is valid (should always be true after start() check above).
			if ( ! is_resource( $this->file_handle ) ) {
				throw new Exception( __( 'Feeds must be started before adding entries.', 'woocommerce-gateway-stripe' ) );
			}

			// Write to file (fputcsv handles escaping automatically).
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			if ( false === fputcsv( $this->file_handle, $sanitized_entry, ',', '"', '\\' ) ) {
				throw new Exception( __( 'Failed to write entry to CSV.', 'woocommerce-gateway-stripe' ) );
			}

			++$this->entry_count;
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Error adding entry to feed: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Sanitize entry data for CSV output.
	 *
	 * Handles scalar values (strings, numbers, booleans, null) and ensures proper formatting
	 * for Stripe's product catalog specification.
	 *
	 * Note: Arrays and objects are not supported. Callers must format complex data themselves
	 * according to Stripe's specification (e.g., "url1,url2,url3" for multiple values,
	 * "US:CA:Express:1-2:12.99 USD" for structured data, "15.00 USD" for prices).
	 *
	 * @since 10.5.0
	 * @param array $entry Raw entry data (must contain only scalar values or null).
	 * @throws Exception If entry contains arrays or objects.
	 * @return array Sanitized entry data.
	 */
	private function sanitize_entry( array $entry ) {
		return array_map( [ $this, 'sanitize_value' ], $entry, array_keys( $entry ) );
	}

	/**
	 * Sanitize a single value for CSV output.
	 *
	 * @since 10.5.0
	 * @param mixed $value The value to sanitize.
	 * @param int   $index The column index (for error messages).
	 * @throws Exception If value is not a scalar or null.
	 * @return string The sanitized value.
	 */
	private function sanitize_value( $value, int $index ): string {
		// Null values - Stripe spec: leave blank for optional fields.
		if ( is_null( $value ) ) {
			return '';
		}

		// Boolean values - Stripe spec: must be literal "true" or "false".
		// Without this, PHP's fputcsv would convert true->1 and false->0.
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		// Reject non-scalar values - caller must format these.
		if ( ! is_scalar( $value ) ) {
			throw new Exception(
				sprintf(
					/* translators: %d: column index */
					__( 'CSV entry at index %d contains an array or object. Please format complex data as strings before passing to add_entry().', 'woocommerce-gateway-stripe' ),
					$index
				)
			);
		}

		// All other scalars (int, float, string) - cast to string.
		return (string) $value;
	}

	/**
	 * Finalize feed generation.
	 *
	 * Closes file handle and marks feed as complete.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function end(): void {
		if ( ! is_resource( $this->file_handle ) || null === $this->file_path ) {
			return;
		}

		// Close the file handle.
		fclose( $this->file_handle );

		// Indicate that we have a complete file.
		$this->finalized = true;

		// Get file size for logging.
		$file_size = filesize( $this->file_path );
		if ( false !== $file_size ) {
			$file_size_human = size_format( $file_size );

			WC_Stripe_Logger::info(
				sprintf(
					'Feed generation completed: %d entries, %s - %s',
					$this->entry_count,
					$file_size_human,
					basename( $this->file_path )
				)
			);
		}
	}

	/**
	 * Get file system path to finalized feed.
	 *
	 * @since 10.5.0
	 * @return string|null Absolute file path, or null if not finalized.
	 */
	public function get_file_path(): ?string {
		if ( ! $this->finalized ) {
			return null;
		}

		return $this->file_path;
	}

	/**
	 * Get public URL to finalized feed.
	 *
	 * Moves file from temp directory to uploads directory if needed.
	 *
	 * @since 10.5.0
	 * @throws Exception Stripe's feeds can only be pushed, the URL is not available.
	 * @return string|null Public URL, or null if not finalized.
	 */
	public function get_file_url(): ?string {
		throw new Exception( __( 'Stripe\'s feeds will always be pushed, the URL is not needed.', 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Clean up temporary files and resources.
	 *
	 * Used for cleanup upon errors and unfinished feeds.
	 *
	 * @since 10.5.0
	 */
	private function cleanup(): void {
		// Close file handle if still open.
		if ( is_resource( $this->file_handle ) ) {
			fclose( $this->file_handle );
			$this->file_handle = null;
		}

		// Delete file if exists and not finalized.
		if ( $this->file_path && file_exists( $this->file_path ) && ! $this->finalized ) {
			unlink( $this->file_path );
			WC_Stripe_Logger::info( 'Cleaned up temp file: ' . basename( $this->file_path ) );
		}
	}
}
