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
	public const ID = 'stripe-agentic-commerce';

	/**
	 * Action Scheduler hook name.
	 *
	 * @var string
	 */
	public const SCHEDULED_ACTION = 'wc_stripe_agentic_commerce_sync_feed';

	/**
	 * Action Scheduler group for adapter-fired one-off resyncs.
	 *
	 * Kept distinct from the recurring `wc-stripe` group so the idempotency
	 * guard in {@see self::schedule_full_resync_now()} can ask "is another
	 * adapter-fired resync already pending?" without matching the recurring
	 * full-feed occurrence — `as_has_scheduled_action()` filters by group,
	 * so a shared group would make the one-off a no-op whenever the cron
	 * tick is queued.
	 *
	 * @var string
	 * @since 10.8.0
	 */
	private const ASYNC_RESYNC_GROUP = 'wc-stripe-agentic-resync';

	/**
	 * Option name to track whether the sync is scheduled.
	 *
	 * @var string
	 * @since 10.5.0
	 */
	public const SCHEDULED_OPTION = 'wc_stripe_agentic_commerce_feed_sync_scheduled';

	/**
	 * Option key for the merchant-facing enabled toggle.
	 *
	 * Distinct from the developer feature flag. This is the value the merchant
	 * controls via the Agentic Commerce settings UI.
	 *
	 * @var string
	 * @since 10.6.0
	 */
	public const ENABLED_OPTION = 'wc_stripe_agentic_commerce_enabled';

	/**
	 * Option key storing the content hash, upload timestamp, and Stripe file id
	 * of the most recent successful full-catalog upload. Used to skip the Stripe
	 * Files API upload when the regenerated catalog is byte-identical to the
	 * previously uploaded one.
	 *
	 * @var string
	 * @since 10.8.0
	 */
	private const LAST_UPLOAD_OPTION = 'wc_stripe_agentic_commerce_last_feed_upload';

	/**
	 * Maximum age (in seconds) before a cached feed upload is considered stale
	 * and the next sync uploads even if the content hash matches. Guards against
	 * Stripe-side file expiration and against bugs in our hashing logic.
	 *
	 * Override via the `wc_stripe_agentic_commerce_feed_cache_ttl` filter.
	 *
	 * @var int
	 * @since 10.8.0
	 */
	private const FEED_CACHE_TTL = WEEK_IN_SECONDS;

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
	public const WEBHOOK_SECRET_OPTION = 'wc_stripe_agentic_commerce_webhook_secret';

	/**
	 * Sync interval in seconds.
	 *
	 * @var int
	 */
	public const SYNC_INTERVAL = 15 * MINUTE_IN_SECONDS;

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
	 * Cached validator instance for the current sync, so the walker (via
	 * {@see ProductWalker::from_integration()}) and the post-walk caller in
	 * {@see self::sync_feed()} share state — specifically, the validator's
	 * accumulated per-product failures. Reset to null at the start of each
	 * sync_feed() call so successive syncs in the same request don't pool
	 * errors across runs.
	 *
	 * @var FeedValidatorInterface|null
	 */
	protected ?FeedValidatorInterface $feed_validator = null;

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

		// Adapter-fired hook for converging Stripe's catalog when the
		// `wc_stripe_agentic_commerce_should_sync_product` filter outcome changes.
		// See the filter docblock for the contract — without this, a previously
		// exported product that becomes excluded would only drop out of Stripe's
		// catalog on the next scheduled full sync.
		add_action( 'wc_stripe_agentic_commerce_schedule_full_resync', [ $this, 'schedule_full_resync_now' ] );

		// WC 10.8+ requires `created_via` to be in an allowlist for `payment_complete()` to run.
		add_filter( 'woocommerce_payment_complete_allowed_created_via_values', [ $this, 'allow_agentic_payment_complete' ] );

		$inventory_tracker = new WC_Stripe_Agentic_Commerce_Inventory_Tracker();
		$inventory_tracker->register_hooks();
	}

	/**
	 * Enqueue an immediate full-feed sync if one is not already pending.
	 *
	 * Idempotent: when a sync is already pending (recurring cron tick or a
	 * previous call within the same request), this is a no-op. Adapters can
	 * call it cheaply on every visibility-setting save without worrying about
	 * stacking Action Scheduler entries.
	 *
	 * @since 10.8.0
	 * @return void
	 */
	public function schedule_full_resync_now(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::SCHEDULED_ACTION, [], self::ASYNC_RESYNC_GROUP ) ) {
			return;
		}

		as_enqueue_async_action( self::SCHEDULED_ACTION, [], self::ASYNC_RESYNC_GROUP );
	}

	/**
	 * Cancel any pending adapter-fired one-off resync.
	 *
	 * A full sync that has just run — e.g. a manual sync from the settings UI —
	 * already produces a complete upload reflecting current visibility, so a
	 * queued {@see self::schedule_full_resync_now()} action would only repeat
	 * that work. It lives in {@see self::ASYNC_RESYNC_GROUP}, which the manual
	 * sync's `wc-stripe`-group reschedule does not touch, so it must be cleared
	 * explicitly. Idempotent: a no-op when nothing is queued.
	 *
	 * @since 10.8.0
	 * @return void
	 */
	public function cancel_pending_full_resync(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::SCHEDULED_ACTION, [], self::ASYNC_RESYNC_GROUP );
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
		as_unschedule_all_actions( self::SCHEDULED_ACTION, [], self::ASYNC_RESYNC_GROUP );
		delete_option( self::SCHEDULED_OPTION );
		delete_option( self::LAST_UPLOAD_OPTION );

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
		if ( null === $this->feed_validator ) {
			$this->feed_validator = new WC_Stripe_Agentic_Commerce_Feed_Validator();
		}
		return $this->feed_validator;
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
	 * @param bool $force_upload When true, bypass the content-hash dedup check and always
	 *                           push the regenerated catalog to Stripe. Used by manual sync
	 *                           from the UI, where the merchant expects every click to land
	 *                           an upload regardless of whether the file changed.
	 * @return bool True on successful delivery, false on early returns or failure.
	 */
	public function sync_feed( bool $force_upload = false ): bool {
		// Drop any validator cached from a previous sync so this run starts
		// with a clean per-product error accumulator.
		$this->feed_validator = null;

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
			$iterated_products = $walker->walk(
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

			// Use the CSV entry count as the authoritative "synced" number — the
			// walker returns the count of products *iterated*, which includes rows
			// the validator dropped before they made it into the feed.
			$total_products = $feed instanceof WC_Stripe_Agentic_Commerce_Csv_Feed
				? $feed->get_entry_count()
				: $iterated_products;

			// Separate the two kinds of dropped row the walker can't distinguish:
			// filter-excluded (a merchant choice) vs. failed validation. Only the
			// latter warns and triggers "Partial success".
			$validator      = $this->get_feed_validator();
			$excluded_count = $validator instanceof WC_Stripe_Agentic_Commerce_Feed_Validator
				? $validator->get_excluded_count()
				: 0;
			$dropped_count  = max( 0, $iterated_products - $total_products );
			$skipped_count  = max( 0, $dropped_count - $excluded_count );

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
					'total_products'    => $total_products,
					'iterated_products' => $iterated_products,
					'skipped_products'  => $skipped_count,
					'excluded_products' => $excluded_count,
					'generation_time'   => round( $generation_time, 2 ) . 's',
					'file_path'         => $file_path,
					'file_size_mb'      => round( $file_size / 1024 / 1024, 2 ),
				]
			);

			if ( $skipped_count > 0 ) {
				$collected       = $validator instanceof WC_Stripe_Agentic_Commerce_Feed_Validator
					? $validator->get_collected_errors()
					: [
						'products'  => [],
						'truncated' => 0,
					];
				$logged_products = $collected['products'] ?? [];
				$truncated       = $collected['truncated'] ?? 0;

				WC_Stripe_Logger::warning(
					sprintf(
						/* translators: 1: number of skipped products, 2: number of iterated products */
						'Agentic Commerce: %1$d of %2$d products were skipped because they failed feed validation.',
						$skipped_count,
						$iterated_products
					),
					[
						'products'  => $logged_products,
						'truncated' => $truncated,
					]
				);
			}

			// Skip upload when the regenerated catalog is byte-identical to the last
			// successfully uploaded one. Hashing still streams the file so memory
			// stays bounded per the feed's streaming contract. Manual sync from the
			// UI passes $force_upload=true so a merchant click always lands an
			// upload regardless of whether the content changed.
			$feed_hash = $this->get_feed_hash( (string) $file_path );
			if ( ! $force_upload && $this->is_feed_unchanged( $feed_hash ) ) {
				WC_Stripe_Logger::info(
					'Agentic Commerce: Upload skipped - feed content unchanged since last successful upload',
					[ 'content_hash' => $feed_hash ]
				);
				if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
				// The feed on Stripe is already in sync, so report success rather
				// than failure — the controller would otherwise treat a no-op as
				// an error and surface it as a 500 to the manual-sync UI.
				return true;
			}

			// Deliver feed to Stripe via Files API.
			$result = $delivery->deliver( $feed );

			$import_set_id = $result['import_set_id'] ?? '';
			$status        = self::normalize_delivery_status( $result, $skipped_count );

			WC_Stripe_Logger::info(
				'Agentic Commerce: Feed delivered to Stripe',
				[
					'file_id'       => $result['file_id'] ?? '',
					'import_set_id' => $import_set_id,
					'status'        => $status,
				]
			);

			// Only record the dedup hash for runs Stripe actually accepted —
			// `deliver()` throws on hard upload failure, but a returned import set
			// with a `failed` status (or a missing ImportSet ID) still means we
			// can't claim "this catalog is on Stripe". Storing the hash in those
			// cases would suppress the next upload that could have recovered.
			if ( ! empty( $feed_hash ) && '' !== $import_set_id && 'failed' !== $status ) {
				$this->remember_feed_upload( $feed_hash, $result );
			}

			// Delete the file to prevent accumulation.
			// Might be removed in favor of a scheduled job to allow debugging.
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			// Persist sync result for dashboard display.
			$this->store_sync_result(
				[
					'products'         => $total_products,
					'status'           => $status,
					'file_id'          => $result['file_id'] ?? '',
					'import_set_id'    => $import_set_id,
					'error'            => '',
					'skipped_products' => $skipped_count,
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
					'products'         => 0,
					'status'           => 'failed',
					'file_id'          => '',
					'import_set_id'    => '',
					'error'            => $e->getMessage(),
					'skipped_products' => 0,
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
	 *     @type int    $products         Number of products synced.
	 *     @type string $status           Sync status (e.g. "succeeded", "failed").
	 *     @type string $file_id          Stripe file ID.
	 *     @type string $import_set_id    Stripe ImportSet ID.
	 *     @type string $error            Error message, if any.
	 *     @type int    $skipped_products Count of products dropped by the local
	 *                                    feed validator before the CSV reached
	 *                                    Stripe. Persisted so the refresh poll
	 *                                    can upgrade a Stripe-reported `succeeded`
	 *                                    to `succeeded_with_errors` once the
	 *                                    ImportSet completes.
	 * }
	 * @return void
	 */
	public function store_sync_result( array $result ): void {
		$history = get_option( self::SYNC_HISTORY_OPTION, [] );

		if ( ! is_array( $history ) ) {
			$history = [];
		}

		$entry = [
			'timestamp'        => time(),
			'products'         => $result['products'] ?? 0,
			'status'           => $result['status'] ?? 'unknown',
			'file_id'          => $result['file_id'] ?? '',
			'import_set_id'    => $result['import_set_id'] ?? '',
			'error'            => $result['error'] ?? '',
			'skipped_products' => isset( $result['skipped_products'] ) ? max( 0, (int) $result['skipped_products'] ) : 0,
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
	 * Resolve the status to persist from an ImportSet creation response.
	 *
	 * When Stripe returns an `import_set_id` but omits a `status` string, the
	 * ImportSet is in-flight and should be recorded as `pending` so the
	 * dashboard's non-terminal poll keeps refreshing until Stripe returns a
	 * terminal state. Falls back to `unknown` only when the delivery failed
	 * outright (no `import_set_id` returned).
	 *
	 * When `$skipped_count` is positive — i.e. the local validator dropped
	 * some products before they reached the CSV — a Stripe-reported
	 * `succeeded` is upgraded to `succeeded_with_errors` so the dashboard
	 * badge ("Partial Success") matches the warning logged for the skips.
	 *
	 * @since 10.7.0
	 * @param array $result        Delivery result from the Files API.
	 * @param int   $skipped_count Count of products dropped by the local validator.
	 * @return string Normalized status string.
	 */
	private static function normalize_delivery_status( array $result, int $skipped_count = 0 ): string {
		$status        = $result['status'] ?? '';
		$import_set_id = $result['import_set_id'] ?? '';

		if ( '' === $status ) {
			$status = '' !== $import_set_id ? 'pending' : 'unknown';
		}

		return self::apply_partial_success_rule( $status, $skipped_count );
	}

	/**
	 * Upgrade `succeeded` to `succeeded_with_errors` when the local validator
	 * dropped products at sync time.
	 *
	 * Centralizes the upgrade so it runs both at initial sync (via
	 * {@see self::normalize_delivery_status()}) and at refresh time (via
	 * {@see self::update_pending_statuses()}). The initial-sync call rarely
	 * sees `succeeded` directly because Stripe processes the ImportSet
	 * asynchronously; the refresh path is where the upgrade typically fires.
	 *
	 * @since 10.7.0
	 * @param string $status        Status reported by Stripe (or normalized fallback).
	 * @param int    $skipped_count Count of products dropped by the local validator
	 *                              for the corresponding sync.
	 * @return string Status with the partial-success upgrade applied if applicable.
	 */
	private static function apply_partial_success_rule( string $status, int $skipped_count ): string {
		if ( 'succeeded' === $status && $skipped_count > 0 ) {
			return 'succeeded_with_errors';
		}

		return $status;
	}

	/**
	 * Apply status updates to non-terminal history entries by import_set_id.
	 *
	 * Re-reads the current history at write time and applies the updates to
	 * matching entries whose stored status is non-terminal (`queued`,
	 * `validating_records`, `pending`, `creating_records`, or `unknown`), matching
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

		$non_terminal_statuses = [ 'queued', 'validating', 'validating_records', 'pending', 'creating_records', 'unknown' ];

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

			$skipped_count = isset( $entry['skipped_products'] ) ? (int) $entry['skipped_products'] : 0;
			$new_status    = self::apply_partial_success_rule( $status_updates[ $import_set_id ], $skipped_count );

			if ( ( $entry['status'] ?? '' ) === $new_status ) {
				continue;
			}

			$entry['status'] = $new_status;
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
	 * Compute the content hash of a generated feed file.
	 *
	 * Uses `hash_file` so PHP streams the file in chunks — the full catalog
	 * is never buffered in memory, preserving the streaming feed contract.
	 *
	 * @since 10.8.0
	 * @param string $file_path Absolute path to the generated feed file.
	 * @return string SHA-256 hex digest, or an empty string if the file cannot be hashed.
	 */
	protected function get_feed_hash( string $file_path ): string {
		if ( empty( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		$hash = hash_file( 'sha256', $file_path );
		return false === $hash ? '' : $hash;
	}

	/**
	 * Decide whether an upload can be skipped because the generated feed matches
	 * the last successfully uploaded one.
	 *
	 * Returns false when dedup is disabled via filter, when the cached record is
	 * missing/malformed, or when the cached upload has exceeded the cache TTL.
	 *
	 * @since 10.8.0
	 * @param string $current_hash Hash of the feed that was just generated.
	 * @return bool True if the upload should be skipped.
	 */
	protected function is_feed_unchanged( string $current_hash ): bool {
		if ( empty( $current_hash ) ) {
			return false;
		}

		/**
		 * Filter whether the unchanged-feed deduplication is enabled.
		 *
		 * Set to false to force every sync cycle to upload to the Stripe Files API,
		 * regardless of whether the feed content has changed.
		 *
		 * @since 10.8.0
		 * @param bool $enabled Default true.
		 */
		if ( true !== apply_filters( 'wc_stripe_agentic_commerce_feed_dedupe_enabled', true ) ) {
			return false;
		}

		$last = get_option( self::LAST_UPLOAD_OPTION, [] );
		if ( ! is_array( $last ) || empty( $last['hash'] ) || ! is_string( $last['hash'] ) ) {
			return false;
		}

		/**
		 * Filter the max age of the cached upload record before dedup is bypassed.
		 *
		 * Defaults to one week. Applied as a safety valve so a stale or lost Stripe
		 * file id still gets refreshed on a predictable cadence.
		 *
		 * @since 10.8.0
		 * @param int $ttl_seconds Default self::FEED_CACHE_TTL.
		 */
		if ( ! isset( $last['uploaded_at'] ) || ! is_numeric( $last['uploaded_at'] ) ) {
			return false;
		}
		$uploaded_at = (int) $last['uploaded_at'];
		if ( $uploaded_at <= 0 ) {
			return false;
		}
		$max_age = (int) apply_filters( 'wc_stripe_agentic_commerce_feed_cache_ttl', self::FEED_CACHE_TTL );
		if ( $max_age > 0 && ( time() - $uploaded_at ) > $max_age ) {
			return false;
		}

		return hash_equals( $last['hash'], $current_hash );
	}

	/**
	 * Record the hash, timestamp, and Stripe file id of a successful upload.
	 *
	 * @since 10.8.0
	 * @param string $hash   SHA-256 hex digest of the uploaded feed content.
	 * @param array  $result Delivery result array returned by the Files API delivery method.
	 * @return void
	 */
	protected function remember_feed_upload( string $hash, array $result ): void {
		update_option(
			self::LAST_UPLOAD_OPTION,
			[
				'hash'          => $hash,
				'uploaded_at'   => time(),
				'file_id'       => is_string( $result['file_id'] ?? null ) ? $result['file_id'] : '',
				'import_set_id' => is_string( $result['import_set_id'] ?? null ) ? $result['import_set_id'] : '',
			],
			false
		);
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
