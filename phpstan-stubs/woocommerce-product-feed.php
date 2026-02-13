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
