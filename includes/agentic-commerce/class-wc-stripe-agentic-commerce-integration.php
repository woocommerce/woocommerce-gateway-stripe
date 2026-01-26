<?php
/**
 * Stripe Agentic Commerce Integration
 *
 * Main integration class that ties together CSV feed, product mapper, validator,
 * and Stripe Files API delivery. Registers with WooCommerce's product feed system
 * and sets up automated synchronization via Action Scheduler.
 *
 * @package WooCommerce_Stripe
 * @since 10.4.0
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
use Automattic\WooCommerce\ProductFeedForOpenAI\Integrations\FileDeliveryInterface;

/**
 * Stripe Agentic Commerce Product Feed Integration
 *
 * @since 10.4.0
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
	 * Sync interval in seconds (15 minutes).
	 *
	 * @var int
	 */
	const SYNC_INTERVAL = 15 * MINUTE_IN_SECONDS; // 15 * 60

	/**
	 * Get integration ID.
	 *
	 * @since 10.4.0
	 * @return string Integration identifier.
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::SCHEDULED_ACTION, [ $this, 'sync_feed' ] );
	}

	/**
	 * Activate integration - schedule recurring sync.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function activate(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
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

			WC_Stripe_Logger::log( 'Agentic Commerce: Scheduled recurring feed sync every ' . ( self::SYNC_INTERVAL / 60 ) . ' minutes' );
		}
	}

	/**
	 * Deactivate integration - cancel scheduled sync.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function deactivate(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::SCHEDULED_ACTION, [], 'wc-stripe' );

		WC_Stripe_Logger::log( 'Agentic Commerce: Canceled all scheduled feed syncs' );
	}

	/**
	 * Get product feed query arguments.
	 *
	 * @since 10.4.0
	 * @return array WP_Query arguments for product selection.
	 */
	public function get_product_feed_query_args(): array {
		/**
		 * Filter product feed query arguments.
		 *
		 * @since 10.4.0
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
	 * @since 10.4.0
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
	 * @since 10.4.0
	 * @return ProductMapperInterface Product mapper instance.
	 */
	public function get_product_mapper(): ProductMapperInterface {
		return new WC_Stripe_Agentic_Commerce_Product_Mapper();
	}

	/**
	 * Get feed validator instance.
	 *
	 * @since 10.4.0
	 * @return FeedValidatorInterface Feed validator instance.
	 */
	public function get_feed_validator(): FeedValidatorInterface {
		return new WC_Stripe_Agentic_Commerce_Feed_Validator();
	}

	/**
	 * Get push delivery method for uploading to Stripe.
	 *
	 * @since 10.4.0
	 * @return FileDeliveryInterface Stripe Files API delivery method.
	 * @throws RuntimeException If delivery method class doesn't exist.
	 */
	public function get_push_delivery_method(): FileDeliveryInterface {
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Files_Api_Delivery' ) ) {
			throw new RuntimeException(
				esc_html__( 'Stripe Files API delivery class not found. Please ensure all required files are loaded.', 'woocommerce-gateway-stripe' )
			);
		}

		$settings   = WC_Stripe_Helper::get_stripe_settings();
		$secret_key = $this->get_secret_key();
		$account_id = $settings['agentic_commerce_account_id'] ?? '';

		return new WC_Stripe_Agentic_Commerce_Files_Api_Delivery( $secret_key, $account_id );
	}

	/**
	 * Check if integration is enabled.
	 *
	 * @since 10.4.0
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return WC_Stripe_Feature_Flags::is_agentic_commerce_enabled();
	}

	/**
	 * Execute feed sync process.
	 *
	 * Generates product feed using ProductWalker, validates it, and uploads to Stripe.
	 *
	 * @since 10.4.0
	 * @return void
	 */
	public function sync_feed(): void {
		if ( ! $this->is_enabled() ) {
			WC_Stripe_Logger::log( 'Agentic Commerce: Sync skipped - feature not enabled' );
			return;
		}

		WC_Stripe_Logger::log( 'Agentic Commerce: Starting feed sync' );

		$start_time = microtime( true );

		try {
			// Create feed and walker.
			$feed   = $this->create_feed();
			$walker = ProductWalker::from_integration( $this, $feed );

			// Walk through products and generate feed.
			$total_products = $walker->walk(
				function ( WalkerProgress $progress ) {
					WC_Stripe_Logger::log(
						'Agentic Commerce: Feed generation progress',
						[
							'batch'            => $progress->processed_batches,
							'total_batches'    => $progress->total_batch_count,
							'processed_items'  => $progress->processed_items,
							'total_items'      => $progress->total_count,
							'memory_available' => $progress->memory_manager->get_available_memory() . '%',
						]
					);
				}
			);

			$generation_time = microtime( true ) - $start_time;

			// Get feed file info.
			$file_path = $feed->get_file_path();
			$file_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;

			WC_Stripe_Logger::log(
				'Agentic Commerce: Feed generated successfully',
				[
					'total_products'   => $total_products,
					'generation_time'  => round( $generation_time, 2 ) . 's',
					'file_path'        => $file_path,
					'file_size_mb'     => round( $file_size / 1024 / 1024, 2 ),
				]
			);

			// Push to Stripe if delivery method is available.
			try {
				$delivery = $this->get_push_delivery_method();

				if ( ! $delivery->check_setup() ) {
					WC_Stripe_Logger::log( 'Agentic Commerce: Push delivery not configured, skipping upload' );
					return;
				}

				$upload_start = microtime( true );
				$result       = $delivery->deliver( $feed );
				$upload_time  = microtime( true ) - $upload_start;

				WC_Stripe_Logger::log(
					'Agentic Commerce: Feed uploaded successfully',
					[
						'file_id'        => $result['file_id'] ?? null,
						'import_set_id'  => $result['import_set_id'] ?? null,
						'upload_time'    => round( $upload_time, 2 ) . 's',
						'total_time'     => round( microtime( true ) - $start_time, 2 ) . 's',
					]
				);

			} catch ( Exception $e ) {
				WC_Stripe_Logger::log(
					'Agentic Commerce: Feed upload failed',
					[
						'error' => $e->getMessage(),
						'code'  => $e->getCode(),
						'file'  => $e->getFile(),
						'line'  => $e->getLine(),
					]
				);
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log(
				'Agentic Commerce: Feed generation failed',
				[
					'error'   => $e->getMessage(),
					'code'    => $e->getCode(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
				]
			);
		}
	}

	/**
	 * Get Stripe secret key from settings.
	 *
	 * @since 10.4.0
	 * @return string Stripe secret key.
	 */
	private function get_secret_key(): string {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$test_mode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];

		if ( $test_mode ) {
			return $settings['test_secret_key'] ?? '';
		}

		return $settings['secret_key'] ?? '';
	}
}
