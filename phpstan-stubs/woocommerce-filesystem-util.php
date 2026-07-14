<?php
/**
 * PHPStan stub for WooCommerce 10.5.0+ Utilities classes.
 *
 * This file provides type information to PHPStan for WooCommerce classes
 * that may not be available in older versions but are used in this plugin.
 *
 * @package WooCommerce_Stripe
 */

namespace Automattic\WooCommerce\Internal\Utilities;

/**
 * FilesystemUtil class.
 *
 * @since 9.3.0
 */
class FilesystemUtil {
	/**
	 * Recursively creates a directory (if it doesn't exist) and adds an empty index.html and a .htaccess to prevent
	 * directory listing.
	 *
	 * @since 9.3.0
	 *
	 * @param string $path Directory to create.
	 * @param bool   $allow_file_access Whether to allow file access while preventing directory listing. Default false (deny all access).
	 * @throws \Exception In case of error.
	 * @return void
	 */
	public static function mkdir_p_not_indexable( string $path, bool $allow_file_access = false ): void {
	}
}
