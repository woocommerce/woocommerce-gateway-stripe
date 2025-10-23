<?php

/**
 * Helper class to mimic the WCS_Staging class from WooCommerce Subscriptions.
 * ONLY for use in unit tests!
 */
class WCS_Staging {

	private static bool $is_staging_site = false;

	/**
	 * Helper function to set the value of $is_staging_site for tests.
	 */
	public static function set_is_staging_site( bool $is_staging_site ): void {
		self::$is_staging_site = $is_staging_site;
	}

	/**
	 * Mimic WCS_Staging::is_staging_site().
	 *
	 * @return bool
	 */
	public static function is_staging_site(): bool {
		return self::$is_staging_site;
	}
}
