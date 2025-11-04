<?php

/**
 * Helper class to mimic the WCS_Staging class from WooCommerce Subscriptions.
 * ONLY for use in unit tests!
 */
class WCS_Staging {

	/**
	 * Local flag to indicate whether the site is a duplicate site.
	 *
	 * @var bool
	 */
	private static bool $is_duplicate_site = false;

	/**
	 * Helper function to set the value of $is_duplicate_site for tests.
	 *
	 * @param bool $is_duplicate_site Whether the site is a duplicate site.
	 * @return void
	 */
	public static function set_is_duplicate_site( bool $is_duplicate_site ): void {
		self::$is_duplicate_site = $is_duplicate_site;
	}

	/**
	 * Mimic WCS_Staging::is_duplicate_site().
	 *
	 * @return bool
	 */
	public static function is_duplicate_site(): bool {
		return self::$is_duplicate_site;
	}
}
