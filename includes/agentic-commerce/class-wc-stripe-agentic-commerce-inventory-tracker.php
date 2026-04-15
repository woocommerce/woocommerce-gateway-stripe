<?php
/**
 * Stripe Agentic Commerce Inventory Tracker
 *
 * Tracks WooCommerce stock changes and batches them into incremental inventory
 * feed updates sent to Stripe via the inventory_feed ImportSet format.
 *
 * @package WooCommerce_Stripe
 * @since 10.6.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks product stock changes and syncs incremental inventory updates to Stripe.
 *
 * Stock changes are batched in a WordPress option and uploaded as an inventory_feed
 * one minute after the first change, reducing API load compared to full catalog syncs.
 *
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Commerce_Inventory_Tracker {

	/**
	 * Option key used to store pending inventory updates.
	 *
	 * @var string
	 */
	const PENDING_UPDATES_OPTION = 'wc_stripe_agentic_pending_inventory';

	/**
	 * Option key used to store pending product archives.
	 *
	 * @var string
	 */
	const PENDING_ARCHIVES_OPTION = 'wc_stripe_agentic_pending_archives';

	/**
	 * Action Scheduler hook name for inventory sync.
	 *
	 * @var string
	 */
	const SCHEDULED_ACTION = 'wc_stripe_agentic_commerce_sync_inventory';

	/**
	 * Action Scheduler hook name for archive sync.
	 *
	 * @var string
	 */
	const ARCHIVE_SCHEDULED_ACTION = 'wc_stripe_agentic_commerce_sync_archives';

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
	 * @since 10.6.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_product_set_stock', [ $this, 'track_stock_change' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this, 'track_stock_change' ] );
		add_action( self::SCHEDULED_ACTION, [ $this, 'sync_inventory' ] );
		// Use WordPress-level hooks rather than WooCommerce data-store hooks
		// (woocommerce_before_delete_product / woocommerce_trash_product) because
		// the data-store hooks only fire through the REST API or programmatic
		// $product->delete() calls, not through the WordPress admin UI.
		add_action( 'before_delete_post', [ $this, 'maybe_track_product_archive' ] );
		add_action( 'wp_trash_post', [ $this, 'maybe_track_product_archive' ] );
		add_action( 'untrash_post', [ $this, 'maybe_cancel_pending_archive' ] );
		add_action( self::ARCHIVE_SCHEDULED_ACTION, [ $this, 'sync_archives' ] );
	}

	/**
	 * Track a stock quantity change for a product.
	 *
	 * Stores the change in the pending updates option and schedules a sync
	 * 60 seconds later if one is not already scheduled. Multiple changes
	 * within that window are batched into a single upload.
	 *
	 * @since 10.6.0
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

		// Use the WooCommerce product ID as sku_id. get_sku() is not used because SKUs
		// are optional in WooCommerce and may be empty or non-unique, whereas product IDs
		// are guaranteed to be unique and always present.
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
	 * Route a WordPress post deletion or trash event to track_product_archive() if it is a product.
	 *
	 * Hooked to before_delete_post and wp_trash_post so that product removals via the
	 * WordPress admin UI are captured in addition to programmatic / REST API deletions.
	 * Loads the full WC_Product so that all required feed fields can be captured before
	 * the product is permanently deleted.
	 *
	 * @since 10.6.0
	 * @param int $post_id The ID of the post being deleted or trashed.
	 * @return void
	 */
	public function maybe_track_product_archive( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), [ 'product', 'product_variation' ], true ) ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		$this->track_product_archive( $product );
	}

	/**
	 * Remove a product from the pending archives queue when it is restored from trash.
	 *
	 * If a merchant trashes a product and then restores it before the 60-second
	 * batch window elapses, this prevents the product from being synced to Stripe
	 * as out_of_stock.
	 *
	 * @since 10.6.0
	 * @param int $post_id The ID of the post being restored.
	 * @return void
	 */
	public function maybe_cancel_pending_archive( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), [ 'product', 'product_variation' ], true ) ) {
			return;
		}

		$pending = get_option( self::PENDING_ARCHIVES_OPTION, [] );
		if ( isset( $pending[ $post_id ] ) ) {
			unset( $pending[ $post_id ] );
			if ( empty( $pending ) ) {
				delete_option( self::PENDING_ARCHIVES_OPTION );
			} else {
				update_option( self::PENDING_ARCHIVES_OPTION, $pending, false );
			}
		}
	}

	/**
	 * Track a product deletion (permanent delete or trash) for archiving on Stripe.
	 *
	 * Uses the product mapper to capture all required feed fields (title, description,
	 * link, price, image, etc.) before the product is permanently deleted, then stores
	 * the full mapped row in the pending archives option. This is necessary because
	 * Stripe's product_catalog_feed ImportSet requires all mandatory fields even when
	 * the purpose is to mark a product as out_of_stock.
	 *
	 * Schedules a sync 60 seconds later if one is not already scheduled. The product
	 * is also removed from any pending inventory updates since the stock quantity is
	 * no longer relevant once the product is removed.
	 *
	 * @since 10.6.0
	 * @param \WC_Product $product The product being deleted or trashed.
	 * @return void
	 */
	public function track_product_archive( \WC_Product $product ): void {
		$product_id = $product->get_id();

		// Remove from pending inventory updates — stock quantity is irrelevant for archived products.
		$pending_inventory = get_option( self::PENDING_UPDATES_OPTION, [] );
		if ( isset( $pending_inventory[ $product_id ] ) ) {
			unset( $pending_inventory[ $product_id ] );
			update_option( self::PENDING_UPDATES_OPTION, $pending_inventory, false );
		}

		$pending = get_option( self::PENDING_ARCHIVES_OPTION, [] );

		if ( count( $pending ) >= self::MAX_PENDING_UPDATES ) {
			return;
		}

		// Capture full product data now, before the product is deleted. The mapper
		// populates all required fields (title, description, link, price, image_link,
		// mpn/gtin, product_category, inventory) that Stripe requires.
		$mapper = new WC_Stripe_Agentic_Commerce_Product_Mapper();
		try {
			$row = $mapper->map_product( $product );
		} catch ( \RuntimeException $e ) {
			// If mapping fails (e.g. missing parent for variation), store minimal data.
			// The sync will still attempt delivery with the fields we have.
			$row = [ 'id' => (string) $product_id ];
		}

		// Force availability to out_of_stock regardless of current stock status.
		$row['availability'] = 'out_of_stock';

		$row['timestamp'] = time();

		$pending[ $product_id ] = $row;

		update_option( self::PENDING_ARCHIVES_OPTION, $pending, false );

		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::ARCHIVE_SCHEDULED_ACTION ) ) {
			as_schedule_single_action( time() + self::BATCH_DELAY_SECONDS, self::ARCHIVE_SCHEDULED_ACTION, [], 'wc-stripe' );
		}
	}

	/**
	 * Generate an inventory feed CSV from pending stock updates.
	 *
	 * Returns a finalized CSV feed containing only SKU ID and quantity columns,
	 * or null if there are no pending updates.
	 *
	 * @since 10.6.0
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
	 * @since 10.6.0
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

		$delivery = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( WC_Stripe_API::get_secret_key() );

		if ( ! $delivery->check_setup() ) {
			WC_Stripe_Logger::error( 'Agentic Commerce: Inventory sync skipped - Stripe API key not configured' );
			return;
		}

		$feed = null;
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

			// Remove only the entries that were included in this sync, preserving any
			// new stock changes that arrived while the upload was in progress.
			$current_pending = get_option( self::PENDING_UPDATES_OPTION, [] );
			$remaining       = array_diff_key( $current_pending, $pending );
			if ( empty( $remaining ) ) {
				delete_option( self::PENDING_UPDATES_OPTION );
			} else {
				update_option( self::PENDING_UPDATES_OPTION, $remaining, false );
			}

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

			// Clean up the temporary file even on failure.
			// $feed is non-null here: if generate_inventory_feed() returned null we
			// would have returned early before any exception could be thrown.
			$file_path = $feed->get_file_path();
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			// Reschedule a retry in case no new stock changes arrive to trigger a fresh sync.
			if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::SCHEDULED_ACTION ) ) {
				as_schedule_single_action( time() + self::BATCH_DELAY_SECONDS, self::SCHEDULED_ACTION, [], 'wc-stripe' );
			}
		}
	}

	/**
	 * Generate an archive feed CSV from pending product archives.
	 *
	 * Returns a finalized CSV feed containing all product catalog fields with
	 * availability set to out_of_stock. The full field set is required because
	 * Stripe's product_catalog_feed ImportSet validates all mandatory columns
	 * (title, description, link, price, image_link, mpn/gtin, product_category,
	 * inventory_quantity/inventory_not_tracked).
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_Agentic_Commerce_Csv_Feed|null Finalized feed, or null if nothing to sync.
	 */
	public function generate_archive_feed(): ?WC_Stripe_Agentic_Commerce_Csv_Feed {
		$pending = get_option( self::PENDING_ARCHIVES_OPTION, [] );

		if ( empty( $pending ) ) {
			return null;
		}

		$columns = WC_Stripe_Agentic_Commerce_Feed_Schema::get_csv_headers();
		$feed    = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'stripe-archive-feed' );
		$feed->set_columns( $columns );
		$feed->start();

		foreach ( $pending as $archive ) {
			$entry = [];
			foreach ( $columns as $column ) {
				$entry[ $column ] = $archive[ $column ] ?? null;
			}
			// Ensure availability is always out_of_stock.
			$entry['availability'] = 'out_of_stock';
			$feed->add_entry( $entry );
		}

		$feed->end();

		return $feed;
	}

	/**
	 * Execute archive sync process.
	 *
	 * Called by Action Scheduler one minute after the first tracked product deletion
	 * or trash event. Generates a minimal product catalog CSV (id + availability:
	 * out_of_stock) and uploads it to Stripe as a product_catalog_feed ImportSet,
	 * marking each product as unavailable without permanently removing it.
	 *
	 * On success, pending archives are cleared. On failure they are retained so
	 * the next scheduled sync can retry.
	 *
	 * If the number of pending archives exceeds MAX_PENDING_UPDATES, the queue
	 * is cleared and the regular full catalog sync will handle the backlog on its
	 * next run.
	 *
	 * @since 10.6.0
	 * @return void
	 */
	public function sync_archives(): void {
		if ( ! WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Archive sync skipped - feature not enabled' );
			return;
		}

		$pending = get_option( self::PENDING_ARCHIVES_OPTION, [] );

		if ( empty( $pending ) ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Archive sync skipped - no pending archives' );
			return;
		}

		// Too many pending archives — fall back to full catalog sync on its next scheduled run.
		if ( count( $pending ) >= self::MAX_PENDING_UPDATES ) {
			WC_Stripe_Logger::info(
				'Agentic Commerce: Archive sync - pending archive threshold exceeded, deferring to full catalog sync',
				[ 'pending_count' => count( $pending ) ]
			);
			delete_option( self::PENDING_ARCHIVES_OPTION );
			return;
		}

		$delivery = new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( WC_Stripe_API::get_secret_key() );

		if ( ! $delivery->check_setup() ) {
			WC_Stripe_Logger::error( 'Agentic Commerce: Archive sync skipped - Stripe API key not configured' );
			return;
		}

		$feed = null;
		try {
			$feed = $this->generate_archive_feed();

			if ( null === $feed ) {
				return;
			}

			$result = $delivery->deliver( $feed );

			WC_Stripe_Logger::info(
				'Agentic Commerce: Archive feed uploaded',
				[
					'archives'      => count( $pending ),
					'import_set_id' => $result['import_set_id'] ?? null,
					'status'        => $result['status'] ?? 'unknown',
				]
			);

			// Remove only the entries that were included in this sync, preserving any
			// new archive events that arrived while the upload was in progress.
			$current_pending = get_option( self::PENDING_ARCHIVES_OPTION, [] );
			$remaining       = array_diff_key( $current_pending, $pending );
			if ( empty( $remaining ) ) {
				delete_option( self::PENDING_ARCHIVES_OPTION );
			} else {
				update_option( self::PENDING_ARCHIVES_OPTION, $remaining, false );
			}

			// Clean up the temporary file.
			$file_path = $feed->get_file_path();
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Agentic Commerce: Archive sync failed',
				[
					'error' => $e->getMessage(),
					'code'  => $e->getCode(),
				]
			);

			// Clean up the temporary file even on failure.
			// $feed is non-null here: if generate_archive_feed() returned null we
			// would have returned early before any exception could be thrown.
			$file_path = $feed->get_file_path();
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			// Reschedule a retry in case no new archive events arrive to trigger a fresh sync.
			if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::ARCHIVE_SCHEDULED_ACTION ) ) {
				as_schedule_single_action( time() + self::BATCH_DELAY_SECONDS, self::ARCHIVE_SCHEDULED_ACTION, [], 'wc-stripe' );
			}
		}
	}
}
