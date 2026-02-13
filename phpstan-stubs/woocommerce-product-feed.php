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
