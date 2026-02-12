<?php
/**
 * WP-CLI commands for Agentic Commerce.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductWalker;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\WalkerProgress;

// CLI output is not rendered in a browser.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Manage the Stripe Agentic Commerce product feed.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_CLI extends WP_CLI_Command {

	/**
	 * Generate the product feed and optionally push it to Stripe.
	 *
	 * ## OPTIONS
	 *
	 * [--push]
	 * : Deliver the feed to Stripe after generation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp stripe agentic-commerce sync
	 *     wp stripe agentic-commerce sync --push
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function sync( $args, $assoc_args ): void {
		$push        = ! empty( $assoc_args['push'] );
		$integration = new WC_Stripe_Agentic_Commerce_Integration();

		// Check delivery setup before generating if pushing.
		$delivery = null;
		if ( $push ) {
			$delivery = $integration->get_push_delivery_method();
			if ( ! $delivery->check_setup() ) {
				WP_CLI::error( 'Stripe API key is not configured. Cannot push feed.' );
			}
		}

		WP_CLI::log( 'Generating product feed...' );
		$start_time = microtime( true );

		try {
			$feed   = $integration->create_feed();
			$walker = ProductWalker::from_integration( $integration, $feed );

			$total = $walker->walk(
				function ( WalkerProgress $progress ) {
					WP_CLI::log(
						sprintf(
							'  Batch %d/%d: %d/%d products',
							$progress->processed_batches,
							$progress->total_batch_count,
							$progress->processed_items,
							$progress->total_count
						)
					);
				}
			);
		} catch ( Exception $e ) {
			WP_CLI::error( 'Feed generation failed: ' . $e->getMessage() );
			return; // Never reached — error() exits.
		}

		$duration  = round( microtime( true ) - $start_time, 2 );
		$file_path = $feed->get_file_path();
		$file_size = ! empty( $file_path ) && file_exists( $file_path ) ? (int) filesize( $file_path ) : 0;

		WP_CLI::success( 'Feed generated.' );
		WP_CLI::log( sprintf( '  Products: %d', $total ) );
		WP_CLI::log( sprintf( '  File:     %s', $file_path ) );
		WP_CLI::log( sprintf( '  Size:     %s', size_format( $file_size ) ) );
		WP_CLI::log( sprintf( '  Time:     %ss', $duration ) );

		if ( ! $push ) {
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Delivering feed to Stripe...' );

		try {
			$result = $delivery->deliver( $feed );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Delivery failed: ' . $e->getMessage() );
			return; // Never reached — error() exits.
		}

		WP_CLI::success( 'Feed delivered to Stripe.' );
		WP_CLI::log( sprintf( '  File ID:       %s', $result['file_id'] ) );
		WP_CLI::log( sprintf( '  ImportSet ID:  %s', $result['import_set_id'] ) );
		WP_CLI::log( sprintf( '  Status:        %s', $result['status'] ) );
	}

	/**
	 * Check the status of an ImportSet.
	 *
	 * ## OPTIONS
	 *
	 * <import_set_id>
	 * : The ImportSet ID (e.g. impset_test_...).
	 *
	 * ## EXAMPLES
	 *
	 *     wp stripe agentic-commerce status impset_test_abc123
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ): void {
		$import_set_id = $args[0];
		$delivery      = $this->get_delivery();

		try {
			$data = $delivery->get_import_set( $import_set_id );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
			return; // Never reached — error() exits.
		}

		WP_CLI::log( sprintf( 'ImportSet: %s', $data['id'] ?? $import_set_id ) );
		WP_CLI::log( sprintf( 'Status:    %s', $data['status'] ?? 'unknown' ) );

		if ( ! empty( $data['failed_reason'] ) ) {
			WP_CLI::log( sprintf( 'Reason:    %s', $data['failed_reason'] ) );
		}

		$result = $data['result'] ?? [];
		if ( ! empty( $result ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Results:' );
			WP_CLI::log( sprintf( '  Rows processed:    %d', $result['rows_processed'] ?? 0 ) );
			WP_CLI::log( sprintf( '  Objects created:   %d', $result['objects_created'] ?? 0 ) );
			WP_CLI::log( sprintf( '  Successes:         %d', $result['successes']['row_count'] ?? 0 ) );
			WP_CLI::log( sprintf( '  Errors:            %d', $result['errors']['row_count'] ?? 0 ) );
			WP_CLI::log( sprintf( '  Skipped (filter):  %d', $result['skipped_by_filter']['row_count'] ?? 0 ) );
			WP_CLI::log( sprintf( '  Skipped (dupes):   %d', $result['skipped_duplicates']['row_count'] ?? 0 ) );

			if ( ! empty( $result['errors']['file'] ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( sprintf( 'Error report file: %s', $result['errors']['file'] ) );
				WP_CLI::log( 'Run: wp stripe agentic-commerce errors ' . $import_set_id );
			}
		}
	}

	/**
	 * Fetch and display the error report for an ImportSet.
	 *
	 * ## OPTIONS
	 *
	 * <import_set_id>
	 * : The ImportSet ID (e.g. impset_test_...).
	 *
	 * [--limit=<number>]
	 * : Maximum number of error rows to display.
	 * ---
	 * default: 10
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp stripe agentic-commerce errors impset_test_abc123
	 *     wp stripe agentic-commerce errors impset_test_abc123 --limit=50
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function errors( $args, $assoc_args ): void {
		$import_set_id = $args[0];
		$limit         = (int) ( $assoc_args['limit'] ?? 10 );
		$delivery      = $this->get_delivery();

		try {
			$import_set = $delivery->get_import_set( $import_set_id );
			$report     = $delivery->get_error_report( $import_set );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
			return; // Never reached — error() exits.
		}

		if ( 0 === $report['error_count'] ) {
			WP_CLI::success( 'No errors found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Total errors: %d', $report['error_count'] ) );

		$rows_to_show = array_slice( $report['rows'], 0, $limit );

		if ( $report['error_count'] > $limit ) {
			WP_CLI::log( sprintf( 'Showing first %d rows (use --limit to see more).', $limit ) );
		}

		WP_CLI::log( '' );

		// Display each error row with only non-empty fields.
		foreach ( $rows_to_show as $i => $row ) {
			WP_CLI::log( sprintf( '--- Row %d ---', $i + 1 ) );
			foreach ( $row as $col => $val ) {
				if ( '' !== $val && null !== $val ) {
					WP_CLI::log( sprintf( '  %s: %s', $col, $val ) );
				}
			}
			WP_CLI::log( '' );
		}
	}

	/**
	 * Get a configured delivery instance.
	 *
	 * @return WC_Stripe_Agentic_Commerce_Files_Api_Delivery
	 */
	private function get_delivery(): WC_Stripe_Agentic_Commerce_Files_Api_Delivery {
		$integration = new WC_Stripe_Agentic_Commerce_Integration();
		$delivery    = $integration->get_push_delivery_method();

		if ( ! $delivery->check_setup() ) {
			WP_CLI::error( 'Stripe API key is not configured.' );
		}

		return $delivery;
	}
}
