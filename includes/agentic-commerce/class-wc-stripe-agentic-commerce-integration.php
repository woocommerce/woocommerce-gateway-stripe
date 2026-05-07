<?php
/**
 * Stripe Agentic Commerce Integration
 *
 * Main integration class that ties together CSV feed, product mapper, validator,
 * and Stripe Files API delivery. Registers with WooCommerce's product feed system
 * and sets up automated synchronization via Action Scheduler.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductMapperInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedValidatorInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductWalker;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\WalkerProgress;

/**
 * Stripe Agentic Commerce Product Feed Integration
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Integration implements IntegrationInterface {

	/**
	 * Integration ID.
	 *
	 * @var string
	 */
	const ID = 'stripe-agentic-commerce';

	/**
	 * Action Scheduler hook name.
	 *
	 * @var string
	 */
	const SCHEDULED_ACTION = 'wc_stripe_agentic_commerce_sync_feed';

	/**
	 * Option name to track whether the sync is scheduled.
	 *
	 * @var string
	 * @since 10.5.0
	 */
	const SCHEDULED_OPTION = 'wc_stripe_agentic_commerce_feed_sync_scheduled';

	/**
	 * Option key for the merchant-facing enabled toggle.
	 *
	 * Distinct from the developer feature flag. This is the value the merchant
	 * controls via the Agentic Commerce settings UI.
	 *
	 * @var string
	 * @since 10.6.0
	 */
	const ENABLED_OPTION = 'wc_stripe_agentic_commerce_enabled';

	/**
	 * Option key for the Agentic Commerce webhook secret.
	 *
	 * Lives on the integration class (not the REST controller) because this
	 * value is read on every webhook delivery via the
	 * `woocommerce_api_wc_stripe` hook, which does not trigger
	 * `rest_api_init`. Keeping the const here ensures it is always reachable
	 * — the integration class is in the Composer autoload classmap — even
	 * when the REST controller has not been instantiated.
	 *
	 * @var string
	 * @since 10.7.0
	 */
	const WEBHOOK_SECRET_OPTION = 'wc_stripe_agentic_commerce_webhook_secret';

	/**
	 * Sync interval in seconds.
	 *
	 * @var int
	 */
	const SYNC_INTERVAL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Option key for the last sync result.
	 *
	 * @internal Not part of the public API. Use {@see self::get_last_sync()}
	 *           rather than reading the underlying option directly.
	 * @var string
	 * @since 10.7.0
	 */
	public const LAST_SYNC_OPTION = 'wc_stripe_agentic_last_sync';

	/**
	 * Option key for the sync history.
	 *
	 * @internal Not part of the public API. Use {@see self::get_sync_history()}
	 *           rather than reading the underlying option directly.
	 * @var string
	 * @since 10.7.0
	 */
	public const SYNC_HISTORY_OPTION = 'wc_stripe_agentic_sync_history';

	/**
	 * Default maximum number of sync history entries to retain.
	 *
	 * Filterable via `wc_stripe_agentic_commerce_sync_history_limit`.
	 *
	 * @var int
	 * @since 10.7.0
	 */
	public const SYNC_HISTORY_LIMIT = 50;

	/**
	 * Get integration ID.
	 *
	 * @since 10.5.0
	 * @return string Integration identifier.
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::SCHEDULED_ACTION, [ $this, 'sync_feed' ] ); // @phpstan-ignore return.void (sync_feed returns bool for manual callers; WP ignores the return value when invoked via action hook)

		// WC 10.8+ requires `created_via` to be in an allowlist for `payment_complete()` to run.
		add_filter( 'woocommerce_payment_complete_allowed_created_via_values', [ $this, 'allow_agentic_payment_complete' ] );

		$inventory_tracker = new WC_Stripe_Agentic_Commerce_Inventory_Tracker();
		$inventory_tracker->register_hooks();
	}

	/**
	 * Adds the agentic `created_via` value to the WooCommerce allowlist so that
	 * `WC_Order::payment_complete()` (WC 10.8+) does not block agentic orders.
	 *
	 * @param array $allowed Existing allowlist passed by the filter.
	 * @return array
	 */
	public function allow_agentic_payment_complete( $allowed ): array {
		if ( ! is_array( $allowed ) ) {
			$allowed = [];
		}
		$allowed[] = WC_Stripe_Agentic_Commerce_Order_Mapper::CREATED_VIA;
		return $allowed;
	}

	/**
	 * Activate integration - schedule recurring sync.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function activate(): void {
		if ( ! did_action( 'action_scheduler_init' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			// Action Scheduler not ready yet — defer to when it initializes.
			add_action( 'action_scheduler_init', [ $this, 'activate' ] );
			return;
		}

		if ( ! as_has_scheduled_action( self::SCHEDULED_ACTION ) ) {
			as_schedule_recurring_action(
				time(),
				self::SYNC_INTERVAL,
				self::SCHEDULED_ACTION,
				[],
				'wc-stripe'
			);

			WC_Stripe_Logger::info( 'Agentic Commerce: Scheduled recurring feed sync every ' . ( self::SYNC_INTERVAL / MINUTE_IN_SECONDS ) . ' minutes' );
		}

		update_option( self::SCHEDULED_OPTION, 'yes', true );
	}

	/**
	 * Deactivate integration - cancel scheduled sync.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function deactivate(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::SCHEDULED_ACTION, [], 'wc-stripe' );
		delete_option( self::SCHEDULED_OPTION );

		WC_Stripe_Logger::info( 'Agentic Commerce: Canceled all scheduled feed syncs' );
	}

	/**
	 * Get product feed query arguments.
	 *
	 * @since 10.5.0
	 * @return array WP_Query arguments for product selection.
	 */
	public function get_product_feed_query_args(): array {
		/**
		 * Filter product feed query arguments.
		 *
		 * @since 10.5.0
		 * @param array $args WP_Query arguments.
		 */
		return apply_filters(
			'wc_stripe_agentic_commerce_product_query_args',
			[
				'type'   => [ 'simple', 'variation' ],
				'status' => [ 'publish' ],
			]
		);
	}

	/**
	 * Create feed instance.
	 *
	 * @since 10.5.0
	 * @return FeedInterface CSV feed instance.
	 */
	public function create_feed(): FeedInterface {
		$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( self::ID );
		$feed->set_columns( WC_Stripe_Agentic_Commerce_Feed_Schema::get_csv_headers() );
		return $feed;
	}

	/**
	 * Get product mapper instance.
	 *
	 * @since 10.5.0
	 * @return ProductMapperInterface Product mapper instance.
	 */
	public function get_product_mapper(): ProductMapperInterface {
		return new WC_Stripe_Agentic_Commerce_Product_Mapper();
	}

	/**
	 * Get feed validator instance.
	 *
	 * @since 10.5.0
	 * @return FeedValidatorInterface Feed validator instance.
	 */
	public function get_feed_validator(): FeedValidatorInterface {
		return new WC_Stripe_Agentic_Commerce_Feed_Validator();
	}

	/**
	 * Get push delivery method for uploading to Stripe.
	 *
	 * @since 10.5.0
	 * @return WC_Stripe_Agentic_Commerce_Files_Api_Delivery Stripe Files API delivery method.
	 */
	public function get_push_delivery_method(): WC_Stripe_Agentic_Commerce_Files_Api_Delivery {
		return new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( $this->get_secret_key() );
	}

	/**
	 * Check if integration is enabled.
	 *
	 * @since 10.5.0
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return WC_Stripe_Feature_Flags::is_agentic_commerce_enabled();
	}

	/**
	 * Check if the merchant has enabled Agentic Commerce via the settings UI.
	 *
	 * This is distinct from the developer feature flag. Both must be true for the
	 * integration to be fully active.
	 *
	 * @since 10.6.0
	 * @return bool True if the merchant has enabled the feature.
	 */
	public static function is_merchant_enabled(): bool {
		return 'yes' === get_option( self::ENABLED_OPTION, 'no' );
	}

	/**
	 * Execute feed sync process.
	 *
	 * Generates product feed using ProductWalker.
	 *
	 * @since 10.5.0
	 * @return bool True on successful delivery, false on early returns or failure.
	 */
	public function sync_feed(): bool {
		if ( ! $this->is_enabled() ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Sync skipped - feature not enabled' );
			return false;
		}

		// Check delivery setup before generating the feed.
		$delivery = $this->get_push_delivery_method();

		if ( ! $delivery->check_setup() ) {
			WC_Stripe_Logger::error( 'Agentic Commerce: Sync skipped - Stripe API key not configured' );
			return false;
		}

		WC_Stripe_Logger::info( 'Agentic Commerce: Starting feed sync' );

		$start_time = microtime( true );

		try {
			// Create feed and walker.
			$feed   = $this->create_feed();
			$walker = ProductWalker::from_integration( $this, $feed );

			// Walk through products and generate feed.
			$total_products = $walker->walk(
				function ( WalkerProgress $progress ) {
					WC_Stripe_Logger::info(
						'Agentic Commerce: Feed generation progress',
						[
							'batch'           => $progress->processed_batches,
							'total_batches'   => $progress->total_batch_count,
							'processed_items' => $progress->processed_items,
							'total_items'     => $progress->total_count,
						]
					);
				}
			);

			if ( 0 === $total_products ) {
				WC_Stripe_Logger::info( 'Agentic Commerce: Sync skipped - no products to sync' );
				$file_path = $feed->get_file_path();
				if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
				return false;
			}

			$generation_time = microtime( true ) - $start_time;

			// Get feed file info.
			$file_path = $feed->get_file_path();
			$file_size = 0;

			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				$file_size = (int) filesize( $file_path );
			}

			WC_Stripe_Logger::info(
				'Agentic Commerce: Feed generated successfully',
				[
					'total_products'  => $total_products,
					'generation_time' => round( $generation_time, 2 ) . 's',
					'file_path'       => $file_path,
					'file_size_mb'    => round( $file_size / 1024 / 1024, 2 ),
				]
			);

			// Deliver feed to Stripe via Files API.
			$result = $delivery->deliver( $feed );

			WC_Stripe_Logger::info(
				'Agentic Commerce: Feed delivered to Stripe',
				[
					'file_id'       => $result['file_id'] ?? '',
					'import_set_id' => $result['import_set_id'] ?? '',
					'status'        => $result['status'] ?? 'unknown',
				]
			);

			// Delete the file to prevent accumulation.
			// Might be removed in favor of a scheduled job to allow debugging.
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			// Persist sync result for dashboard display.
			$this->store_sync_result(
				[
					'products'      => $total_products,
					'status'        => $result['status'] ?? 'unknown',
					'file_id'       => $result['file_id'] ?? '',
					'import_set_id' => $result['import_set_id'] ?? '',
					'error'         => '',
				]
			);

			return true;
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error(
				'Agentic Commerce: Feed generation failed',
				[
					'error' => $e->getMessage(),
					'code'  => $e->getCode(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
				]
			);

			// Persist failure for dashboard display.
			$this->store_sync_result(
				[
					'products'      => 0,
					'status'        => 'failed',
					'file_id'       => '',
					'import_set_id' => '',
					'error'         => $e->getMessage(),
				]
			);

			return false;
		}
	}

	/**
	 * Persist a sync result to the history option and update the last-sync snapshot.
	 *
	 * @since 10.7.0
	 * @param array $result {
	 *     Sync result data.
	 *
	 *     @type int    $products      Number of products synced.
	 *     @type string $status        Sync status (e.g. "succeeded", "failed").
	 *     @type string $file_id       Stripe file ID.
	 *     @type string $import_set_id Stripe ImportSet ID.
	 *     @type string $error         Error message, if any.
	 * }
	 * @return void
	 */
	public function store_sync_result( array $result ): void {
		$history = get_option( self::SYNC_HISTORY_OPTION, [] );

		if ( ! is_array( $history ) ) {
			$history = [];
		}

		$entry = [
			'timestamp'     => time(),
			'products'      => $result['products'] ?? 0,
			'status'        => $result['status'] ?? 'unknown',
			'file_id'       => $result['file_id'] ?? '',
			'import_set_id' => $result['import_set_id'] ?? '',
			'error'         => $result['error'] ?? '',
		];

		$history[] = $entry;

		/**
		 * Filter the maximum number of sync history entries to retain.
		 *
		 * @since 10.7.0
		 * @param int $limit Default history limit.
		 */
		$limit   = (int) apply_filters( 'wc_stripe_agentic_commerce_sync_history_limit', self::SYNC_HISTORY_LIMIT );
		$limit   = max( 10, min( 50, $limit ) );
		$history = array_slice( $history, -$limit );

		update_option( self::SYNC_HISTORY_OPTION, $history, false );
		update_option( self::LAST_SYNC_OPTION, end( $history ), false );
	}

	/**
	 * Get the last sync result as stored by {@see self::store_sync_result()}.
	 *
	 * Supported API for reading the last sync snapshot. External callers should
	 * use this getter rather than reading the underlying option directly.
	 *
	 * @since 10.7.0
	 * @return array Normalized sync entry, or an empty array when no sync has run.
	 */
	public static function get_last_sync(): array {
		$last_sync = get_option( self::LAST_SYNC_OPTION, [] );
		return is_array( $last_sync ) ? $last_sync : [];
	}

	/**
	 * Get the sync history.
	 *
	 * Supported API for reading the sync history. Returned entries are in
	 * insertion order (oldest first). Non-array entries from corrupted data are
	 * filtered out.
	 *
	 * @since 10.7.0
	 * @return array<int, array> List of sync entries.
	 */
	public static function get_sync_history(): array {
		$history = get_option( self::SYNC_HISTORY_OPTION, [] );
		if ( ! is_array( $history ) ) {
			return [];
		}
		return array_values( array_filter( $history, 'is_array' ) );
	}

	/**
	 * Apply status updates to non-terminal history entries by import_set_id.
	 *
	 * Re-reads the current history at write time and applies the updates to
	 * matching entries whose stored status is non-terminal (`queued`,
	 * `validating`, `pending`, `creating_records`, or `unknown`), matching
	 * the controller's
	 * {@see WC_REST_Stripe_Agentic_Commerce_Controller::REFRESHABLE_STATUSES}.
	 * This preserves any entries appended concurrently by
	 * {@see self::store_sync_result()} between read and write (for example
	 * during a Stripe API round-trip in the dashboard refresh flow).
	 *
	 * @since 10.7.0
	 * @param array<string, string> $status_updates Map of import_set_id to new status.
	 * @return void
	 */
	public static function update_pending_statuses( array $status_updates ): void {
		if ( empty( $status_updates ) ) {
			return;
		}

		$non_terminal_statuses = [ 'queued', 'validating', 'pending', 'creating_records', 'unknown' ];

		$history = self::get_sync_history();
		$changed = false;

		foreach ( $history as &$entry ) {
			if ( ! in_array( $entry['status'] ?? '', $non_terminal_statuses, true ) ) {
				continue;
			}

			$import_set_id = $entry['import_set_id'] ?? '';
			if ( '' === $import_set_id || ! isset( $status_updates[ $import_set_id ] ) ) {
				continue;
			}

			$entry['status'] = $status_updates[ $import_set_id ];
			$changed         = true;
		}
		unset( $entry );

		if ( ! $changed ) {
			return;
		}

		update_option( self::SYNC_HISTORY_OPTION, $history, false );

		$last = end( $history );
		if ( is_array( $last ) ) {
			update_option( self::LAST_SYNC_OPTION, $last, false );
		}
	}

	/**
	 * Get Stripe secret key from settings.
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
