<?php
/**
 * Stripe Agentic Commerce Inventory Tracker
 *
 * Tracks WooCommerce stock changes and batches them into incremental inventory
 * feed updates sent to Stripe via the inventory_feed ImportSet format.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;

/**
 * Tracks product stock changes and syncs incremental inventory updates to Stripe.
 *
 * Stock changes are batched in a WordPress option and uploaded as an inventory_feed
 * one minute after the first change, reducing API load compared to full catalog syncs.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Inventory_Tracker {

	/**
	 * Option key used to store pending inventory updates.
	 *
	 * @var string
	 */
	const PENDING_UPDATES_OPTION = 'wc_stripe_agentic_pending_inventory';

	/**
	 * Option key used to store pending product deletions.
	 *
	 * @var string
	 */
	const PENDING_DELETIONS_OPTION = 'wc_stripe_agentic_pending_deletions';

	/**
	 * Action Scheduler hook name for inventory sync.
	 *
	 * @var string
	 */
	const SCHEDULED_ACTION = 'wc_stripe_agentic_commerce_sync_inventory';

	/**
	 * Action Scheduler hook name for deletion sync.
	 *
	 * @var string
	 */
	const DELETION_SCHEDULED_ACTION = 'wc_stripe_agentic_commerce_sync_deletions';

	/**
	 * Maximum number of pending updates before falling back to full catalog sync.
	 *
	 * @var int
	 */
	const MAX_PENDING_UPDATES = 1000;

	/**
	 * Delay in seconds before processing a batch of stock changes.
	 *
	 * @var int
	 */
	const BATCH_DELAY_SECONDS = 60;

	/**
	 * Register WordPress hooks.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_product_set_stock', [ $this, 'track_stock_change' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this, 'track_stock_change' ] );
		add_action( self::SCHEDULED_ACTION, [ $this, 'sync_inventory' ] );
		add_action( 'woocommerce_before_delete_product', [ $this, 'track_product_deletion' ] );
		add_action( 'woocommerce_trash_product', [ $this, 'track_product_deletion' ] );
		add_action( self::DELETION_SCHEDULED_ACTION, [ $this, 'sync_deletions' ] );
	}

	/**
	 * Track a stock quantity change for a product.
	 *
	 * Stores the change in the pending updates option and schedules a sync
	 * 60 seconds later if one is not already scheduled. Multiple changes
	 * within that window are batched into a single upload.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product The product whose stock changed.
	 * @return void
	 */
	public function track_stock_change( \WC_Product $product ): void {
		$pending = get_option( self::PENDING_UPDATES_OPTION, [] );

		// Once we hit the threshold, stop accumulating — sync_inventory() will
		// clear the queue and allow the regular full catalog sync to catch up.
		if ( count( $pending ) >= self::MAX_PENDING_UPDATES ) {
			return;
		}

		$pending[ $product->get_id() ] = [
			'sku_id'    => $product->get_id(),
			'quantity'  => $product->get_stock_quantity(),
			'timestamp' => time(),
		];

		update_option( self::PENDING_UPDATES_OPTION, $pending, false );

		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::SCHEDULED_ACTION ) ) {
			as_schedule_single_action( time() + self::BATCH_DELAY_SECONDS, self::SCHEDULED_ACTION, [], 'wc-stripe' );
		}
	}

	/**
	 * Track a product deletion (permanent delete or trash).
	 *
	 * Stores the product ID in the pending deletions option and schedules a sync
	 * 60 seconds later if one is not already scheduled. The product is also
	 * removed from any pending inventory updates since the stock quantity is
	 * no longer relevant once the product is removed.
	 *
	 * @since 10.6.0
	 * @param int $product_id The ID of the product being deleted or trashed.
	 * @return void
	 */
	public function track_product_deletion( int $product_id ): void {
		// Remove from pending inventory updates — stock quantity is irrelevant for deleted products.
		$pending_inventory = get_option( self::PENDING_UPDATES_OPTION, [] );
		if ( isset( $pending_inventory[ $product_id ] ) ) {
			unset( $pending_inventory[ $product_id ] );
			update_option( self::PENDING_UPDATES_OPTION, $pending_inventory, false );
		}

		$pending = get_option( self::PENDING_DELETIONS_OPTION, [] );

		if ( count( $pending ) >= self::MAX_PENDING_UPDATES ) {
			return;
		}

		$pending[ $product_id ] = [
			'id'        => $product_id,
			'timestamp' => time(),
		];

		update_option( self::PENDING_DELETIONS_OPTION, $pending, false );

		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::DELETION_SCHEDULED_ACTION ) ) {
			as_schedule_single_action( time() + self::BATCH_DELAY_SECONDS, self::DELETION_SCHEDULED_ACTION, [], 'wc-stripe' );
		}
	}

	/**
	 * Generate an inventory feed CSV from pending stock updates.
	 *
	 * Returns a finalized CSV feed containing only SKU ID and quantity columns,
	 * or null if there are no pending updates.
	 *
	 * @since 10.5.0
	 * @return WC_Stripe_Agentic_Commerce_Csv_Feed|null Finalized feed, or null if nothing to sync.
	 */
	public function generate_inventory_feed(): ?WC_Stripe_Agentic_Commerce_Csv_Feed {
		$pending = get_option( self::PENDING_UPDATES_OPTION, [] );

		if ( empty( $pending ) ) {
			return null;
		}

		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'stripe-inventory-feed' );
		$feed->set_columns( [ 'sku_id', 'inventory_quantity' ] );
		$feed->start();

		foreach ( $pending as $update ) {
			$feed->add_entry(
				[
					'sku_id'             => $update['sku_id'],
					'inventory_quantity' => $update['quantity'],
				]
			);
		}

		$feed->end();

		return $feed;
	}

	/**
	 * Execute inventory sync process.
	 *
	 * Called by Action Scheduler one minute after the first tracked stock change.
	 * Generates a minimal inventory CSV and uploads it to Stripe as an inventory_feed.
	 *
	 * On success, pending updates are cleared. On failure they are retained so
	 * the next scheduled sync can retry.
	 *
	 * If the number of pending updates exceeds MAX_PENDING_UPDATES, the queue is
	 * cleared and the regular full catalog sync will handle the backlog on its
	 * next run.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function sync_inventory(): void {
		if ( ! WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Inventory sync skipped - feature not enabled' );
			return;
		}

		$pending = get_option( self::PENDING_UPDATES_OPTION, [] );

		if ( empty( $pending ) ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Inventory sync skipped - no pending updates' );
			return;
		}

		// Too many pending updates — fall back to full catalog sync on its next scheduled run.
		if ( count( $pending ) >= self::MAX_PENDING_UPDATES ) {
			WC_Stripe_Logger::info(
				'Agentic Commerce: Inventory sync - pending update threshold exceeded, deferring to full catalog sync',
				[ 'pending_count' => count( $pending ) ]
			);
			delete_option( self::PENDING_UPDATES_OPTION );
			return;
		}

		$delivery = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( $this->get_secret_key() );

		if ( ! $delivery->check_setup() ) {
			WC_Stripe_Logger::error( 'Agentic Commerce: Inventory sync skipped - Stripe API key not configured' );
			return;
		}

		try {
			$feed = $this->generate_inventory_feed();

			if ( null === $feed ) {
				return;
			}

			$result = $delivery->deliver_inventory_feed( $feed );

			WC_Stripe_Logger::info(
				'Agentic Commerce: Inventory feed uploaded',
				[
					'updates'       => count( $pending ),
					'import_set_id' => $result['import_set_id'] ?? null,
					'status'        => $result['status'] ?? 'unknown',
				]
			);

			// Clear pending updates on success.
			delete_option( self::PENDING_UPDATES_OPTION );

			// Clean up the temporary file.
			$file_path = $feed->get_file_path();
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Agentic Commerce: Inventory sync failed',
				[
					'error' => $e->getMessage(),
					'code'  => $e->getCode(),
				]
			);
			// Do not clear pending updates on failure — next scheduled action will retry.
		}
	}

	/**
	 * Generate a deletion feed CSV from pending product deletions.
	 *
	 * Returns a finalized CSV feed containing only the product ID and a delete
	 * flag, or null if there are no pending deletions.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_Agentic_Commerce_Csv_Feed|null Finalized feed, or null if nothing to sync.
	 */
	public function generate_deletion_feed(): ?WC_Stripe_Agentic_Commerce_Csv_Feed {
		$pending = get_option( self::PENDING_DELETIONS_OPTION, [] );

		if ( empty( $pending ) ) {
			return null;
		}

		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'stripe-deletion-feed' );
		$feed->set_columns( [ 'id', 'delete' ] );
		$feed->start();

		foreach ( $pending as $deletion ) {
			$feed->add_entry(
				[
					'id'     => $deletion['id'],
					'delete' => true,
				]
			);
		}

		$feed->end();

		return $feed;
	}

	/**
	 * Execute deletion sync process.
	 *
	 * Called by Action Scheduler one minute after the first tracked deletion.
	 * Generates a minimal product catalog CSV (id + delete: true) and uploads
	 * it to Stripe as a product_catalog_feed ImportSet.
	 *
	 * On success, pending deletions are cleared. On failure they are retained so
	 * the next scheduled sync can retry.
	 *
	 * If the number of pending deletions exceeds MAX_PENDING_UPDATES, the queue
	 * is cleared and the regular full catalog sync will handle the backlog on its
	 * next run.
	 *
	 * @since 10.6.0
	 * @return void
	 */
	public function sync_deletions(): void {
		if ( ! WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Deletion sync skipped - feature not enabled' );
			return;
		}

		$pending = get_option( self::PENDING_DELETIONS_OPTION, [] );

		if ( empty( $pending ) ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Deletion sync skipped - no pending deletions' );
			return;
		}

		// Too many pending deletions — fall back to full catalog sync on its next scheduled run.
		if ( count( $pending ) >= self::MAX_PENDING_UPDATES ) {
			WC_Stripe_Logger::info(
				'Agentic Commerce: Deletion sync - pending deletion threshold exceeded, deferring to full catalog sync',
				[ 'pending_count' => count( $pending ) ]
			);
			delete_option( self::PENDING_DELETIONS_OPTION );
			return;
		}

		$delivery = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( $this->get_secret_key() );

		if ( ! $delivery->check_setup() ) {
			WC_Stripe_Logger::error( 'Agentic Commerce: Deletion sync skipped - Stripe API key not configured' );
			return;
		}

		try {
			$feed = $this->generate_deletion_feed();

			if ( null === $feed ) {
				return;
			}

			$result = $delivery->deliver( $feed );

			WC_Stripe_Logger::info(
				'Agentic Commerce: Deletion feed uploaded',
				[
					'deletions'     => count( $pending ),
					'import_set_id' => $result['import_set_id'] ?? null,
					'status'        => $result['status'] ?? 'unknown',
				]
			);

			// Clear pending deletions on success.
			delete_option( self::PENDING_DELETIONS_OPTION );

			// Clean up the temporary file.
			$file_path = $feed->get_file_path();
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Agentic Commerce: Deletion sync failed',
				[
					'error' => $e->getMessage(),
					'code'  => $e->getCode(),
				]
			);
			// Do not clear pending deletions on failure — next scheduled action will retry.
		}
	}

	/**
	 * Get Stripe secret key from plugin settings.
	 *
	 * @since 10.5.0
	 * @return string Stripe secret key.
	 */
	private function get_secret_key(): string {
		$settings  = WC_Stripe_Helper::get_stripe_settings();
		$test_mode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];

		if ( $test_mode ) {
			return $settings['test_secret_key'] ?? '';
		}

		return $settings['secret_key'] ?? '';
	}
}
