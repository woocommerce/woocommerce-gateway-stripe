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
	 * Sync interval in seconds.
	 *
	 * @var int
	 */
	const SYNC_INTERVAL = 15 * MINUTE_IN_SECONDS;

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
		add_action( self::SCHEDULED_ACTION, [ $this, 'sync_feed' ] );
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
	 * Execute feed sync process.
	 *
	 * Generates product feed using ProductWalker.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function sync_feed(): void {
		if ( ! $this->is_enabled() ) {
			WC_Stripe_Logger::info( 'Agentic Commerce: Sync skipped - feature not enabled' );
			return;
		}

		// Check delivery setup before generating the feed.
		$delivery = $this->get_push_delivery_method();

		if ( ! $delivery->check_setup() ) {
			WC_Stripe_Logger::error( 'Agentic Commerce: Sync skipped - Stripe API key not configured' );
			return;
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
				return;
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
