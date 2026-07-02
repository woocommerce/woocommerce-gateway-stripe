<?php
/**
 * Stub definitions for WooCommerce ProductFeed interfaces and classes.
 *
 * These stubs are only defined when the real WooCommerce ProductFeed package is
 * not present (e.g. older WooCommerce versions in CI). They provide the minimum
 * surface area required for the plugin's agentic-commerce classes to load, so
 * that PHPUnit tests can boot and individual test cases can call markTestSkipped()
 * when they need the real implementation.
 *
 * @package WooCommerce\Stripe\Tests
 */

// phpcs:disable

namespace Automattic\WooCommerce\Internal\ProductFeed\Feed;

if ( ! interface_exists( FeedInterface::class ) ) {
	interface FeedInterface {
		public function set_columns( array $headers ): self;
		public function start(): void;
		public function add_entry( array $entry ): void;
		public function end(): void;
		public function get_file_path(): ?string;
		public function get_file_url(): ?string;
	}
}

if ( ! interface_exists( ProductMapperInterface::class ) ) {
	interface ProductMapperInterface {
		public function map_product( \WC_Product $product ): array;
	}
}

if ( ! interface_exists( FeedValidatorInterface::class ) ) {
	interface FeedValidatorInterface {
		public function validate_entry( array $row, \WC_Product $product ): array;
	}
}

if ( ! class_exists( WalkerProgress::class ) ) {
	class WalkerProgress {
		public int $processed_batches = 0;
		public int $total_batch_count = 0;
		public int $processed_items   = 0;
		public int $total_count       = 0;
	}
}

if ( ! class_exists( ProductWalker::class ) ) {
	class ProductWalker {
		public static function from_integration( object $integration, object $feed ): self {
			return new self();
		}
		public function walk( ?callable $progress_callback = null ): int {
			return 0;
		}
	}
}

namespace Automattic\WooCommerce\Internal\ProductFeed\Integrations;

if ( ! interface_exists( IntegrationInterface::class ) ) {
	interface IntegrationInterface {
		public function get_id(): string;
		public function activate(): void;
		public function deactivate(): void;
		public function get_product_feed_query_args(): array;
		public function create_feed(): \Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;
		public function get_product_mapper(): \Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductMapperInterface;
		public function is_enabled(): bool;
	}
}

namespace Automattic\WooCommerce\Internal\ProductFeed\Utils;

if ( ! class_exists( StringHelper::class ) ) {
	class StringHelper {
		public static function truncate( string $value, int $max_length ): string {
			if ( $max_length <= 0 || mb_strlen( $value ) <= $max_length ) {
				return $value;
			}
			return mb_substr( $value, 0, $max_length );
		}
	}
}

namespace Automattic\WooCommerce\Internal\Utilities;

if ( ! class_exists( FilesystemUtil::class ) ) {
	class FilesystemUtil {
		public static function mkdir_p_not_indexable( string $path ): void {
			wp_mkdir_p( $path );
		}
	}
}

// phpcs:enable
