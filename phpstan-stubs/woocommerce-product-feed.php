<?php
/**
 * PHPStan stub for WooCommerce 10.5.0+ Product Feed classes.
 *
 * This file provides type information to PHPStan for WooCommerce classes
 * that may not be available in older versions but are used in this plugin.
 *
 * @package WooCommerce_Stripe
 */

namespace Automattic\WooCommerce\Internal\ProductFeed\Feed;

/**
 * Feed Interface.
 *
 * @since 10.5.0
 */
interface FeedInterface {
	/**
	 * Start the feed.
	 * This can create an empty file, eventually put something in it, or add a database entry.
	 *
	 * @return void
	 */
	public function start(): void;

	/**
	 * Add an entry to the feed.
	 *
	 * @param array $entry The entry to add.
	 * @return void
	 */
	public function add_entry( array $entry ): void;

	/**
	 * End the feed.
	 *
	 * @return void
	 */
	public function end(): void;

	/**
	 * Get the file path of the feed.
	 *
	 * @return string|null The path to the feed file, null if not ready.
	 */
	public function get_file_path(): ?string;

	/**
	 * Get the URL of the feed file.
	 *
	 * @return string|null The URL of the feed file, null if not ready.
	 */
	public function get_file_url(): ?string;
}

/**
 * Product Mapper Interface.
 *
 * @since 10.5.0
 */
interface ProductMapperInterface {
	/**
	 * Map a WooCommerce product to feed entry format.
	 *
	 * @param \WC_Product $product The product to map.
	 * @return array The mapped product data.
	 */
	public function map_product( \WC_Product $product ): array;
}

/**
 * Feed Validator Interface.
 *
 * @since 10.5.0
 */
interface FeedValidatorInterface {
	/**
	 * Validate a feed entry.
	 *
	 * @param array       $row     The feed entry to validate.
	 * @param \WC_Product $product The product being validated.
	 * @return array Array of validation error messages (empty if valid).
	 */
	public function validate_entry( array $row, \WC_Product $product ): array;
}

/**
 * Walker Progress value object.
 *
 * @since 10.5.0
 */
class WalkerProgress {
	/** @var int */
	public int $total_count;
	/** @var int */
	public int $total_batch_count;
	/** @var int */
	public int $processed_items = 0;
	/** @var int */
	public int $processed_batches = 0;

	/**
	 * @param \stdClass $result
	 * @return self
	 */
	public static function from_wc_get_products_result( \stdClass $result ): self {
		return new self();
	}
}

/**
 * Product Walker.
 *
 * @since 10.5.0
 */
class ProductWalker {
	/**
	 * @param \Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface $integration
	 * @param FeedInterface $feed
	 * @return self
	 */
	public static function from_integration(
		\Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface $integration,
		FeedInterface $feed
	): self {
		return new self();
	}

	/**
	 * @param int $batch_size
	 * @return self
	 */
	public function set_batch_size( int $batch_size ): self {
		return $this;
	}

	/**
	 * @param int $time_limit
	 * @return self
	 */
	public function add_time_limit( int $time_limit ): self {
		return $this;
	}

	/**
	 * @param callable|null $callback
	 * @return int
	 */
	public function walk( ?callable $callback = null ): int {
		return 0;
	}
}

namespace Automattic\WooCommerce\Internal\ProductFeed\Integrations;

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductMapperInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedValidatorInterface;

/**
 * Integration Interface.
 *
 * @since 10.5.0
 */
interface IntegrationInterface {
	/** @return string */
	public function get_id(): string;
	/** @return void */
	public function register_hooks(): void;
	/** @return void */
	public function activate(): void;
	/** @return void */
	public function deactivate(): void;
	/** @return array */
	public function get_product_feed_query_args(): array;
	/** @return FeedInterface */
	public function create_feed(): FeedInterface;
	/** @return ProductMapperInterface */
	public function get_product_mapper(): ProductMapperInterface;
	/** @return FeedValidatorInterface */
	public function get_feed_validator(): FeedValidatorInterface;
}

namespace Automattic\WooCommerce\Internal\ProductFeed;

/**
 * Product Feed service.
 *
 * @since 10.5.0
 */
class ProductFeed {
	/**
	 * @param \Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface $integration
	 * @return void
	 */
	public function register_integration( \Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface $integration ): void {}
}

namespace Automattic\WooCommerce\Internal\ProductFeed\Utils;

/**
 * String Helper Utility.
 *
 * @since 10.5.0
 */
class StringHelper {
	/**
	 * Truncate a string to a maximum length.
	 *
	 * @param string $string    The string to truncate.
	 * @param int    $max_length Maximum length.
	 * @return string Truncated string.
	 */
	public static function truncate( string $string, int $max_length ): string {
		return $string;
	}
}
